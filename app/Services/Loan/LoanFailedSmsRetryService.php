<?php

namespace App\Services\Loan;

use App\Jobs\SendLoanSmsJob;
use App\Models\LmMessageRecipient;
use App\Services\BulkSmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class LoanFailedSmsRetryService
{
    public function __construct(private readonly BulkSmsService $sms)
    {
    }

    /**
     * @return array{attempted: int, sent: int, skipped: int, failed: int}
     */
    public function retryDue(?int $limit = null): array
    {
        if (! $this->enabled()) {
            return ['attempted' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $limit = max(1, $limit ?? (int) config('loan_communication.sms_auto_retry.batch_limit', 25));

        return $this->retryFailedRecipients($limit);
    }

    public function enabled(): bool
    {
        return filter_var(
            config('loan_communication.sms_auto_retry.enabled', true),
            FILTER_VALIDATE_BOOL
        );
    }

    /**
     * @return array{attempted: int, sent: int, skipped: int, failed: int}
     */
    public function retryFailedRecipients(int $limit): array
    {
        $summary = ['attempted' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        if ($limit <= 0 || ! Schema::hasTable('lm_message_recipients') || ! Schema::hasTable('lm_messages')) {
            return $summary;
        }

        $recipients = LmMessageRecipient::query()
            ->with('message')
            ->where('channel', 'sms')
            ->where('status', 'failed')
            ->where('updated_at', '<=', $this->cooldownSince())
            ->where('updated_at', '>=', $this->maxAgeSince())
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
            if ($recipient->retry_count >= max(1, (int) ($recipient->max_retries ?? 3))) {
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

            SendLoanSmsJob::dispatch($recipient->id);
            $summary['sent']++;
        }

        return $summary;
    }

    private function cooldownSince(): Carbon
    {
        $minutes = max(1, (int) config('loan_communication.sms_auto_retry.min_minutes_between_attempts', 30));

        return now()->subMinutes($minutes);
    }

    private function maxAgeSince(): Carbon
    {
        $hours = max(1, (int) config('loan_communication.sms_auto_retry.max_age_hours', 72));

        return now()->subHours($hours);
    }
}
