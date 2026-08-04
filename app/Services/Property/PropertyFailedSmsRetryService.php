<?php

namespace App\Services\Property;

use App\Jobs\SendSmsJob;
use App\Models\PmMessageLog;
use App\Models\PmMessageRecipient;
use App\Services\BulkSmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class PropertyFailedSmsRetryService
{
    public function __construct(
        private readonly BulkSmsService $sms,
        private readonly PropertySmsResendService $resend,
        private readonly RentReminderEligibilityService $eligibility,
        private readonly SmsHealthService $smsHealth,
    ) {
    }

    /**
     * @return array{attempted: int, sent: int, skipped: int, failed: int}
     */
    public function retryDue(?int $limit = null): array
    {
        if (! $this->enabled()) {
            return ['attempted' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $limit = max(1, $limit ?? (int) config('property_communication.sms_auto_retry.batch_limit', 25));
        $summary = ['attempted' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        $recipientBudget = (int) ceil($limit / 2);
        $recipientResult = $this->retryFailedRecipients($recipientBudget);
        $this->mergeSummary($summary, $recipientResult);

        $remaining = max(0, $limit - $summary['attempted']);
        if ($remaining > 0) {
            $logResult = $this->retryFailedLogs($remaining);
            $this->mergeSummary($summary, $logResult);
        }

        return $summary;
    }

    public function enabled(): bool
    {
        return filter_var(
            config('property_communication.sms_auto_retry.enabled', true),
            FILTER_VALIDATE_BOOL
        );
    }

    /**
     * @return array{attempted: int, sent: int, skipped: int, failed: int}
     */
    public function retryFailedRecipients(int $limit): array
    {
        $summary = ['attempted' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        if ($limit <= 0) {
            return $summary;
        }

        $cooldown = $this->cooldownSince();
        $maxAge = $this->maxAgeSince();

        $recipients = PmMessageRecipient::query()
            ->with('message')
            ->where('channel', 'sms')
            ->where('status', 'failed')
            ->where('updated_at', '<=', $cooldown)
            ->where('updated_at', '>=', $maxAge)
            ->whereHas('message', static function (Builder $query): void {
                $query->where('status', '!=', 'cancelled');
            })
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($recipients->isEmpty()) {
            return $summary;
        }

        $afford = $this->sms->canAffordRecipients($recipients->count());
        if (! ($afford['ok'] ?? false)) {
            return $summary;
        }

        foreach ($recipients as $recipient) {
            if (! $this->recipientStillNeedsRetry($recipient)) {
                $this->cancelRecipientBecauseSettled($recipient);
                $summary['skipped']++;

                continue;
            }

            $summary['attempted']++;

            $recipient->update([
                'status' => 'queued',
                'queued_at' => now(),
                'sending_at' => null,
                'sent_at' => null,
                'failed_at' => null,
                'last_error' => null,
                'next_retry_at' => null,
            ]);

            SendSmsJob::dispatch($recipient->id);
            $summary['sent']++;
        }

        return $summary;
    }

    /**
     * @return array{attempted: int, sent: int, skipped: int, failed: int}
     */
    public function retryFailedLogs(int $limit): array
    {
        $summary = ['attempted' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        if ($limit <= 0) {
            return $summary;
        }

        $table = (new PmMessageLog)->getTable();
        $cooldown = $this->cooldownSince();
        $maxAge = $this->maxAgeSince();

        $query = PmMessageLog::query()->where("{$table}.channel", 'sms');
        $this->smsHealth->applyUnresolvedFailedSmsScope($query, $table);
        $logs = $query
            ->where("{$table}.updated_at", '<=', $cooldown)
            ->where("{$table}.created_at", '>=', $maxAge)
            ->orderBy("{$table}.id")
            ->limit($limit * 3)
            ->get();

        if ($logs->isEmpty()) {
            return $summary;
        }

        $actions = $this->eligibility->resendActionsForLogs($logs);
        $eligible = $logs->filter(function (PmMessageLog $log) use ($actions): bool {
            if (! (bool) (($actions[(int) $log->id]['can_resend'] ?? false))) {
                if (! $this->eligibility->logStillBillableForResend($log)) {
                    $this->eligibility->supersedeFailedLogBecauseSettled($log);
                }

                return false;
            }

            return true;
        });
        $deduped = $this->resend->dedupeLogsForResend($eligible)->take($limit);

        if ($deduped->isEmpty()) {
            return $summary;
        }

        $sendable = $deduped->filter(
            fn (PmMessageLog $log): bool => $this->sms->normalizeRecipientList((string) $log->to_address) !== []
        )->count();

        if ($sendable <= 0) {
            return $summary;
        }

        $afford = $this->sms->canAffordRecipients($sendable);
        if (! ($afford['ok'] ?? false)) {
            return $summary;
        }

        $delayMs = max(0, (int) config('property_communication.sms_auto_retry.delay_between_sends_ms', 1500));
        $attemptIndex = 0;

        foreach ($deduped as $log) {
            $phones = $this->sms->normalizeRecipientList((string) $log->to_address);
            if ($phones === []) {
                $summary['skipped']++;

                continue;
            }

            if ($attemptIndex > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }
            $attemptIndex++;
            $summary['attempted']++;

            $result = $this->resend->resendLog($log, $phones, null);

            if (($result['ok'] ?? false) === true) {
                $summary['sent']++;
            } elseif (($result['skipped'] ?? false) === true) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function recipientStillNeedsRetry(PmMessageRecipient $recipient): bool
    {
        $message = $recipient->message;
        if (! $message || $message->status === 'cancelled') {
            return false;
        }

        if (($message->category ?? '') === 'rent_reminder') {
            $invoiceId = $this->eligibility->invoiceIdFromMessage($message);
            if ($invoiceId !== null) {
                $invoice = \App\Models\PmInvoice::query()->withoutGlobalScopes()->find($invoiceId);
                if ($invoice !== null && ! $this->eligibility->rentReminderStillBillable($invoice)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function cancelRecipientBecauseSettled(PmMessageRecipient $recipient): void
    {
        if (($recipient->message?->category ?? '') !== 'rent_reminder') {
            return;
        }

        $recipient->update([
            'status' => 'cancelled',
            'is_opted_out' => true,
            'opt_out_reason' => 'Invoice paid or settled before auto-retry.',
            'next_retry_at' => null,
        ]);
    }

    private function cooldownSince(): Carbon
    {
        $minutes = max(1, (int) config('property_communication.sms_auto_retry.min_minutes_between_attempts', 30));

        return now()->subMinutes($minutes);
    }

    private function maxAgeSince(): Carbon
    {
        $hours = max(1, (int) config('property_communication.sms_auto_retry.max_age_hours', 72));

        return now()->subHours($hours);
    }

    /**
     * @param  array{attempted: int, sent: int, skipped: int, failed: int}  $target
     * @param  array{attempted: int, sent: int, skipped: int, failed: int}  $delta
     */
    private function mergeSummary(array &$target, array $delta): void
    {
        foreach (['attempted', 'sent', 'skipped', 'failed'] as $key) {
            $target[$key] += (int) ($delta[$key] ?? 0);
        }
    }
}
