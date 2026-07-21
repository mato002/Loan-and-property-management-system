<?php

namespace App\Services\Loan;

use App\Models\LmMessage;
use App\Models\LmMessageLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LoanPaymentReminderEligibilityService
{
    public function messageBlocksRentReminderRetry(LmMessage $message): bool
    {
        return $message->recipients()->whereIn('status', ['sent', 'delivered', 'queued', 'sending'])->exists();
    }

    /**
     * @param  Collection<int, LmMessageLog>  $logs
     * @return array<int, array{label: string, enabled: bool, reason?: string}>
     */
    public function resendActionsForLogs(Collection $logs): array
    {
        $actions = [];
        foreach ($logs as $log) {
            $actions[(int) $log->id] = $this->smsResendActionForLog($log, []);
        }

        return $actions;
    }

    /**
     * @param  array<string, bool>  $deliveredKeys
     * @return array{label: string, enabled: bool, reason?: string}
     */
    public function smsResendActionForLog(LmMessageLog $log, array $deliveredKeys): array
    {
        if ((string) $log->channel !== 'sms') {
            return ['label' => 'Resend', 'enabled' => false, 'reason' => 'Only SMS rows can be resent from here.'];
        }
        if ((string) $log->delivery_status === 'sent') {
            return ['label' => 'Resend', 'enabled' => true];
        }
        if ((string) $log->delivery_status === 'failed') {
            return ['label' => 'Retry SMS', 'enabled' => true];
        }

        return ['label' => 'Resend', 'enabled' => false, 'reason' => 'Message is not in a resendable state.'];
    }

    /**
     * @param  list<string>  $invoiceNumbers
     * @return array<string, bool>
     */
    public function deliveredInvoiceKeysForInvoiceNumbers(array $invoiceNumbers): array
    {
        return [];
    }

    public function extractInvoiceNoFromLogText(string $subject, string $body): string
    {
        $haystack = $subject.' '.$body;
        if (preg_match('/\b(?:loan|account|ref)[:\s#-]*([A-Z0-9-]{4,})\b/i', $haystack, $m)) {
            return strtoupper((string) ($m[1] ?? ''));
        }

        return '';
    }

    public function extractInternalStageFromLogText(string $subject, string $fallback = ''): string
    {
        if (preg_match('/\[(D[+-]?\d+|FINAL_DEMAND|COLLECTIONS)\]/', $subject, $m)) {
            return (string) ($m[1] ?? '');
        }

        return trim($fallback);
    }

    public function messageBodyHash(string $body): string
    {
        return sha1(trim($body));
    }

    public function findSuccessfulLogForIntent(string $to, string $loanNo, string $stage, string $hash): ?LmMessageLog
    {
        return null;
    }

    public function supersedeFailedLogsForRecipientInvoice(
        string $to,
        string $loanNo,
        ?int $invoiceId,
        string $stage,
        string $hash,
        int $successLogId
    ): void {
        LmMessageLog::query()
            ->where('channel', 'sms')
            ->where('to_address', $to)
            ->where('delivery_status', 'failed')
            ->whereNull('superseded_at')
            ->where('id', '!=', $successLogId)
            ->update([
                'superseded_at' => now(),
                'superseded_by_log_id' => $successLogId,
            ]);
    }

    public function applyUnresolvedFailedSmsScope(Builder $q, string $table): void
    {
        $q->where("{$table}.channel", 'sms')
            ->where("{$table}.delivery_status", 'failed')
            ->whereNull("{$table}.superseded_at");
    }
}
