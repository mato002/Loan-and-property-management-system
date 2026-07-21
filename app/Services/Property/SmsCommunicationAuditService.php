<?php

namespace App\Services\Property;

use App\Models\PmMessageLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class SmsCommunicationAuditService
{
    public function __construct(
        private readonly RentReminderEligibilityService $eligibility,
        private readonly SmsHealthService $smsHealth,
    ) {
    }

    /**
     * @return array{
     *   unresolved_failed: array{count: int, samples: list<array<string, mixed>>},
     *   duplicate_sent: array{count: int, groups: list<array<string, mixed>>},
     *   duplicate_charge_candidates: array{count: int, groups: list<array<string, mixed>>},
     *   retry_storms: array{count: int, groups: list<array<string, mixed>>}
     * }
     */
    public function runAudit(?CarbonInterface $from = null, ?CarbonInterface $to = null, int $sampleLimit = 25): array
    {
        return [
            'unresolved_failed' => $this->unresolvedFailed($from, $to, $sampleLimit),
            'duplicate_sent' => $this->duplicateSentGroups($from, $to, $sampleLimit),
            'duplicate_charge_candidates' => $this->duplicateChargeCandidates($from, $to, $sampleLimit),
            'retry_storms' => $this->retryStormGroups($from, $to, $sampleLimit),
        ];
    }

    /**
     * @return array{count: int, samples: list<array<string, mixed>>}
     */
    private function unresolvedFailed(?CarbonInterface $from, ?CarbonInterface $to, int $sampleLimit): array
    {
        $query = $this->smsHealth->unresolvedFailedQuery($from, $to, withoutAgentScope: true);

        $count = $this->smsHealth->unresolvedFailedCount($from, $to, withoutAgentScope: true);
        $samples = (clone $query)
            ->orderByDesc('id')
            ->limit($sampleLimit)
            ->get(['id', 'to_address', 'subject', 'internal_stage', 'delivery_status', 'created_at'])
            ->map(fn (PmMessageLog $log) => [
                'id' => (int) $log->id,
                'to' => (string) $log->to_address,
                'subject' => (string) $log->subject,
                'stage' => (string) ($log->internal_stage ?: $this->eligibility->extractInternalStageFromLogText((string) $log->subject)),
                'created_at' => optional($log->created_at)->toDateTimeString(),
            ])
            ->all();

        return ['count' => $count, 'samples' => $samples];
    }

    /**
     * @return array{count: int, groups: list<array<string, mixed>>}
     */
    private function duplicateSentGroups(?CarbonInterface $from, ?CarbonInterface $to, int $sampleLimit): array
    {
        $table = (new PmMessageLog)->getTable();
        $base = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->whereIn('delivery_status', ['sent', 'delivered']);

        $this->applyDateRange($base, $from, $to);

        $invoiceTokenSql = 'SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(subject, " ", COALESCE(body, "")), "INV-", -1), " ", 1)';

        $groups = (clone $base)
            ->select(
                'to_address',
                DB::raw('CONCAT("INV-", '.$invoiceTokenSql.') as invoice_key'),
                DB::raw('COALESCE(NULLIF(internal_stage, ""), "") as stage_key'),
                DB::raw('DATE(created_at) as send_day'),
                DB::raw('COUNT(*) as row_count')
            )
            ->whereRaw('(subject LIKE "%INV-%" OR body LIKE "%INV-%")')
            ->groupBy(
                'to_address',
                DB::raw('CONCAT("INV-", '.$invoiceTokenSql.')'),
                'stage_key',
                DB::raw('DATE(created_at)')
            )
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('row_count')
            ->limit($sampleLimit)
            ->get();

        $groupList = $groups->map(fn ($row) => [
            'to_address' => (string) $row->to_address,
            'invoice' => (string) $row->invoice_key,
            'stage' => (string) $row->stage_key,
            'day' => (string) $row->send_day,
            'row_count' => (int) $row->row_count,
        ])->all();

        return ['count' => count($groupList), 'groups' => $groupList];
    }

    /**
     * @return array{count: int, groups: list<array<string, mixed>>}
     */
    private function duplicateChargeCandidates(?CarbonInterface $from, ?CarbonInterface $to, int $sampleLimit): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sms_logs')) {
            return ['count' => 0, 'groups' => []];
        }

        $query = \App\Models\SmsLog::query()
            ->where('status', 'sent')
            ->where(function ($q) {
                $q->where('module', 'property')->orWhereNull('module');
            });

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }
        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to->toDateString());
        }

        $groups = (clone $query)
            ->select(
                'phone',
                DB::raw('LEFT(message, 120) as message_prefix'),
                DB::raw('DATE(created_at) as charge_day'),
                DB::raw('COUNT(*) as send_count'),
                DB::raw('SUM(COALESCE(charged_amount, 0)) as total_charged')
            )
            ->groupBy('phone', DB::raw('LEFT(message, 120)'), DB::raw('DATE(created_at)'))
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('send_count')
            ->limit($sampleLimit)
            ->get();

        $groupList = $groups->map(fn ($row) => [
            'phone' => (string) $row->phone,
            'message_prefix' => (string) $row->message_prefix,
            'day' => (string) $row->charge_day,
            'send_count' => (int) $row->send_count,
            'total_charged' => (float) $row->total_charged,
        ])->all();

        return [
            'count' => $this->smsHealth->duplicateChargeCandidateCount($from, $to),
            'groups' => $groupList,
        ];
    }

    /**
     * @return array{count: int, groups: list<array<string, mixed>>}
     */
    private function retryStormGroups(?CarbonInterface $from, ?CarbonInterface $to, int $sampleLimit): array
    {
        $query = PmMessageLog::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->where('delivery_status', 'failed');

        $this->applyDateRange($query, $from, $to);

        $groups = (clone $query)
            ->select(
                'to_address',
                'subject',
                DB::raw('DATE(created_at) as fail_day'),
                DB::raw('COUNT(*) as failed_count')
            )
            ->groupBy('to_address', 'subject', DB::raw('DATE(created_at)'))
            ->havingRaw('COUNT(*) >= 3')
            ->orderByDesc('failed_count')
            ->limit($sampleLimit)
            ->get();

        $groupList = $groups->map(fn ($row) => [
            'to_address' => (string) $row->to_address,
            'subject' => (string) $row->subject,
            'day' => (string) $row->fail_day,
            'failed_count' => (int) $row->failed_count,
        ])->all();

        return [
            'count' => $this->smsHealth->retryStormCount($from, $to, withoutAgentScope: true),
            'groups' => $groupList,
        ];
    }

    private function applyDateRange($query, ?CarbonInterface $from, ?CarbonInterface $to): void
    {
        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }
        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to->toDateString());
        }
    }
}
