<?php

namespace App\Services\Property;

use App\Models\PmMessageLog;
use App\Services\BulkSmsService;
use Illuminate\Support\Collection;

final class PropertySmsResendService
{
    public function __construct(
        private readonly BulkSmsService $sms,
        private readonly RentReminderEligibilityService $eligibility,
        private readonly RentReminderMessageLogResolver $resolver,
    ) {
    }

    /**
     * @param  list<string>  $phones
     * @return array{ok: bool, skipped?: bool, error?: string}
     */
    public function resendLog(PmMessageLog $log, array $phones, ?int $userId = null): array
    {
        if ($log->channel !== 'sms') {
            return ['ok' => false, 'error' => 'Only SMS logs can be resent.'];
        }

        if (! $this->eligibility->isLogEligibleForSmsResend($log)) {
            return [
                'ok' => false,
                'skipped' => true,
                'error' => 'This row is not a failed SMS that needs resending.',
            ];
        }

        if (! $this->eligibility->logStillBillableForResend($log)) {
            $this->eligibility->supersedeFailedLogBecauseSettled($log);

            return [
                'ok' => false,
                'skipped' => true,
                'error' => 'Invoice is paid or settled.',
            ];
        }

        $body = $this->resolver->resolveSmsBody($log);
        if ($body === null && $this->resolver->isRentReminder($log)) {
            return ['ok' => false, 'error' => 'Could not rebuild this rent reminder (invoice missing or inaccessible).'];
        }

        $body = $body ?? (string) $log->body;
        $meta = $this->resolver->resolveStaffMeta($log);
        $invoiceNo = $this->eligibility->extractInvoiceNoFromLogText((string) $log->subject, (string) $log->body);
        $subject = (string) ($meta['subject'] ?? $log->subject);
        $internalStage = (string) ($meta['internal_stage'] ?? $log->internal_stage);
        if ($internalStage === '') {
            $internalStage = $this->eligibility->extractInternalStageFromLogText($subject, (string) $log->internal_stage);
        }
        $messageHash = $this->eligibility->messageBodyHash($body);

        if ($this->eligibility->logShowsSuccessfulSmsForIntent(
            implode(',', $phones),
            $invoiceNo,
            $internalStage,
            $messageHash,
            (int) $log->id
        )) {
            return [
                'ok' => false,
                'skipped' => true,
                'error' => 'A successful SMS for this invoice, stage, and message is already logged.',
            ];
        }

        $displayStage = (string) ($meta['display_stage'] ?? $log->display_stage);
        $templateCategory = $this->resolver->isRentReminder($log) ? 'rent_reminder' : $log->template_category;

        $result = $this->sms->sendNow(
            $body,
            $phones,
            $userId,
            null,
            'property',
            verifyBalance: false
        );

        if (($result['ok'] ?? false) !== true && $this->isProviderRateLimitError((string) ($result['error'] ?? ''))) {
            sleep((int) config('property_communication.sms_auto_retry.rate_limit_wait_seconds', 60));
            $result = $this->sms->sendNow(
                $body,
                $phones,
                $userId,
                null,
                'property',
                verifyBalance: false
            );
        }

        if (($result['ok'] ?? false) === true) {
            $sentLog = PmMessageLog::query()->create([
                'user_id' => $userId,
                'channel' => 'sms',
                'to_address' => implode(',', $phones),
                'subject' => $subject,
                'internal_stage' => $internalStage !== '' ? $internalStage : null,
                'display_stage' => $displayStage !== '' ? $displayStage : null,
                'template_category' => $templateCategory,
                'body' => $body,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            if ($invoiceNo !== '') {
                $this->eligibility->supersedeFailedLogsForRecipientInvoice(
                    $phones,
                    $invoiceNo,
                    null,
                    $internalStage,
                    $messageHash,
                    (int) $sentLog->id
                );
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, PmMessageLog>  $logs
     * @return Collection<int, PmMessageLog>
     */
    public function dedupeLogsForResend(Collection $logs): Collection
    {
        $picked = [];

        foreach ($logs->sortBy('id') as $log) {
            $phones = $this->sms->normalizeRecipientList((string) $log->to_address);
            $invoiceNo = $this->eligibility->extractInvoiceNoFromLogText((string) $log->subject, (string) $log->body);
            $phoneKey = $phones[0] ?? trim((string) $log->to_address);
            $groupKey = strtolower($invoiceNo !== '' ? $invoiceNo.'|'.$phoneKey : 'log:'.$log->id);

            if (! isset($picked[$groupKey])) {
                $picked[$groupKey] = $log;
            }
        }

        return collect(array_values($picked));
    }

    public function isProviderRateLimitError(string $error): bool
    {
        return str_contains($error, '429')
            || str_contains(strtolower($error), 'rate_limit')
            || str_contains(strtolower($error), 'too many requests');
    }
}
