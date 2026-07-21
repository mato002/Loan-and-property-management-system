<?php

namespace App\Services\Property;

use App\Models\PmMessageLog;
use App\Models\SmsLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for SMS delivery health metrics (unresolved failures, superseded rows, audit signals).
 */
final class SmsHealthService
{
    public function __construct(
        private readonly RentReminderEligibilityService $eligibility,
    ) {
    }

    public function applyUnresolvedFailedSmsScope(Builder $q, string $table): void
    {
        $this->eligibility->applyUnresolvedFailedSmsScope($q, $table);
    }

    /**
     * Failed SMS rows that still need agent action (excludes superseded and invoice+phone already sent successfully).
     */
    public function unresolvedFailedCount(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        bool $withoutAgentScope = false,
        string $dateColumn = 'created_at',
    ): int {
        return $this->unresolvedFailedQuery($from, $to, $withoutAgentScope, $dateColumn)->count();
    }

    public function unresolvedFailedQuery(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        bool $withoutAgentScope = false,
        string $dateColumn = 'created_at',
    ): Builder {
        $table = (new PmMessageLog)->getTable();
        $q = $withoutAgentScope
            ? PmMessageLog::query()->withoutGlobalScopes()
            : PmMessageLog::query();

        $this->applyUnresolvedFailedSmsScope($q, $table);
        $this->applyDateRange($q, $from, $to, $dateColumn);

        return $q;
    }

    /**
     * Unresolved SMS rent reminders for a calendar day (dashboard widget).
     */
    public function unresolvedRentReminderSmsCountForDate(CarbonInterface $date): int
    {
        $table = (new PmMessageLog)->getTable();
        $q = $this->unresolvedFailedQuery($date, $date);
        $this->applyRentReminderLogScope($q, $table);

        return $q->count();
    }

    /**
     * Failed email rent reminders for a calendar day (email has no supersede / retry-success pairing).
     */
    public function failedRentReminderEmailCountForDate(CarbonInterface $date): int
    {
        $table = (new PmMessageLog)->getTable();
        $q = PmMessageLog::query()
            ->whereDate("{$table}.created_at", $date->toDateString())
            ->where("{$table}.channel", 'email')
            ->where("{$table}.delivery_status", 'failed');

        $this->applyRentReminderLogScope($q, $table);

        return $q->count();
    }

    /**
     * Rent reminder failures needing action today (SMS unresolved + email failed).
     */
    public function rentReminderFailuresNeedingActionToday(): int
    {
        $today = now();

        return $this->unresolvedRentReminderSmsCountForDate($today)
            + $this->failedRentReminderEmailCountForDate($today);
    }

    /**
     * Communications workspace + advisor: unresolved SMS plus failed email logs.
     */
    public function unresolvedCommunicationCount(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        string $dateColumn = 'created_at',
    ): int {
        return $this->unresolvedFailedCount($from, $to, dateColumn: $dateColumn)
            + $this->failedEmailCount($from, $to, $dateColumn);
    }

    public function failedEmailCount(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        string $dateColumn = 'created_at',
    ): int {
        $q = PmMessageLog::query()
            ->where('channel', 'email')
            ->where('delivery_status', 'failed');

        $this->applyDateRange($q, $from, $to, $dateColumn);

        return $q->count();
    }

    public function supersededCount(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        bool $withoutAgentScope = false,
    ): int {
        $q = $withoutAgentScope
            ? PmMessageLog::query()->withoutGlobalScopes()
            : PmMessageLog::query();

        $q->where('channel', 'sms')
            ->where('delivery_status', 'superseded');

        $this->applyDateRange($q, $from, $to);

        return $q->count();
    }

    public function duplicateChargeCandidateCount(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): int {
        if (! Schema::hasTable('sms_logs')) {
            return 0;
        }

        $query = SmsLog::query()
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
                DB::raw('COUNT(*) as send_count')
            )
            ->groupBy('phone', DB::raw('LEFT(message, 120)'), DB::raw('DATE(created_at)'))
            ->havingRaw('COUNT(*) > 1');

        return $groups->get()->count();
    }

    public function retryStormCount(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        bool $withoutAgentScope = false,
    ): int {
        $query = $withoutAgentScope
            ? PmMessageLog::query()->withoutGlobalScopes()
            : PmMessageLog::query();

        $query->where('channel', 'sms')
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
            ->havingRaw('COUNT(*) >= 3');

        return $groups->get()->count();
    }

    /**
     * Count unresolved/failed rows on a communications message-log query (respects channel + status filters).
     *
     * @param  callable(array<string, mixed>): Builder  $messageLogsQuery
     */
    public function unresolvedFailedCountForCommunicationsFilters(
        array $filters,
        callable $messageLogsQuery,
    ): int {
        $filters = $this->normalizeCommunicationsPeriodFilters($filters);
        $table = (new PmMessageLog)->getTable();
        $channel = trim((string) ($filters['channel'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($status === 'failed_all') {
            return (clone $messageLogsQuery($filters))->where($table.'.delivery_status', 'failed')->count();
        }

        $failedFilters = array_merge($filters, ['status' => 'failed']);
        if ($channel === '' || $channel === 'sms') {
            return (clone $messageLogsQuery($failedFilters))->count();
        }

        return (clone $messageLogsQuery($failedFilters))->where($table.'.delivery_status', 'failed')->count();
    }

    public function applyRentReminderLogScope(Builder $q, string $table): void
    {
        $q->where(function (Builder $b) use ($table) {
            $b->where("{$table}.template_category", 'rent_reminder')
                ->orWhereNotNull("{$table}.internal_stage")
                ->orWhere("{$table}.subject", 'like', '[ARREARS]%')
                ->orWhere("{$table}.subject", 'like', '[STAFF|%')
                ->orWhere("{$table}.subject", 'like', '%[RENT]%');
        });
    }

    public function lastUnresolvedRentReminderSmsError(): string
    {
        $table = (new PmMessageLog)->getTable();
        $q = $this->unresolvedFailedQuery();
        $this->applyRentReminderLogScope($q, $table);

        return (string) ($q->orderByDesc("{$table}.id")->value("{$table}.delivery_error") ?? '');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeCommunicationsPeriodFilters(array $filters): array
    {
        $period = trim((string) ($filters['period'] ?? ''));
        if ($period !== '' && trim((string) ($filters['from'] ?? '')) === '' && trim((string) ($filters['to'] ?? '')) === '') {
            $now = now();
            if ($period === 'today') {
                $filters['from'] = $now->toDateString();
                $filters['to'] = $now->toDateString();
            } elseif ($period === 'week') {
                $filters['from'] = $now->copy()->startOfWeek()->toDateString();
                $filters['to'] = $now->toDateString();
            } elseif ($period === 'month') {
                $filters['from'] = $now->copy()->startOfMonth()->toDateString();
                $filters['to'] = $now->toDateString();
            }
        }

        return $filters;
    }

    private function applyDateRange(Builder $query, ?CarbonInterface $from, ?CarbonInterface $to, string $column = 'created_at'): void
    {
        if ($from !== null) {
            $query->whereDate($column, '>=', $from->toDateString());
        }
        if ($to !== null) {
            $query->whereDate($column, '<=', $to->toDateString());
        }
    }
}
