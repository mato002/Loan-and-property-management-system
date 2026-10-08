<?php

namespace App\Services\Property;

use App\Models\PmMessageLog;
use App\Models\PmMessagePreference;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Services\BulkSmsService;
use App\Support\Property\MpesaIntegrationConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends automated SMS/email payment receipts after a rent payment settles.
 */
class PropertyPaymentReceiptNotifier
{
    public function __construct(private readonly BulkSmsService $sms) {}

    /**
     * @return array{sent_sms:bool,sent_email:bool,skipped:bool,message:string}
     */
    public function notify(PmPayment $payment): array
    {
        if (! MpesaIntegrationConfig::autoReceiptEnabled()
            || ! \App\Models\PropertyPortalSetting::isPaymentReceiptAutomationEnabled()) {
            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'Auto-receipt disabled.'];
        }

        if ($payment->status !== PmPayment::STATUS_COMPLETED) {
            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'Payment not completed.'];
        }

        if ($this->isBeforeCurrentMonth($payment)) {
            $this->suppressPastMonthReceipt($payment);

            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'Payment is from a previous month.'];
        }

        // Claim this payment for one receipt only (blocks on-payment + retry races).
        $claimed = $this->claimForNotification($payment);
        if ($claimed === null) {
            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'Already notified or claimed.'];
        }
        $payment = $claimed;

        $payment->loadMissing([
            'tenant:id,name,phone,email,account_number',
            'allocations.invoice:id,invoice_type,billing_period,issue_date,description,invoice_no',
        ]);
        $tenant = $payment->tenant;
        if (! $tenant) {
            $this->releaseClaim($payment, 'No tenant on payment.');

            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'No tenant on payment.'];
        }

        $channel = MpesaIntegrationConfig::autoReceiptChannel();
        $body = $this->buildBody($payment, $tenant);
        $sentSms = false;
        $sentEmail = false;
        $errors = [];

        if (in_array($channel, ['sms', 'both'], true)) {
            $result = $this->sendSms($tenant, $body);
            $sentSms = $result['ok'];
            if (! $result['ok'] && $result['message'] !== '') {
                $errors[] = $result['message'];
            }
        }

        if (in_array($channel, ['email', 'both'], true)) {
            $result = $this->sendEmail($tenant, $body, $payment);
            $sentEmail = $result['ok'];
            if (! $result['ok'] && $result['message'] !== '') {
                $errors[] = $result['message'];
            }
        }

        if ($sentSms || $sentEmail) {
            $this->markNotified($payment, $sentSms, $sentEmail, $channel);

            return [
                'sent_sms' => $sentSms,
                'sent_email' => $sentEmail,
                'skipped' => false,
                'message' => 'Receipt sent.',
            ];
        }

        $this->releaseClaim($payment, $errors !== [] ? implode(' ', $errors) : 'No delivery channel available.');

        return [
            'sent_sms' => false,
            'sent_email' => false,
            'skipped' => true,
            'message' => $errors !== [] ? implode(' ', $errors) : 'No delivery channel available.',
        ];
    }

    /**
     * Atomically claim one payment for receipt sending.
     * Returns the locked payment when this caller owns the claim; null otherwise.
     */
    private function claimForNotification(PmPayment $payment): ?PmPayment
    {
        return DB::transaction(function () use ($payment) {
            /** @var PmPayment|null $locked */
            $locked = PmPayment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }

            $meta = is_array($locked->meta) ? $locked->meta : [];
            if (! empty($meta['receipt_notified_at'])) {
                return null;
            }
            if (! empty($meta['skip_notification'])) {
                return null;
            }

            $claimAt = (string) ($meta['receipt_claim_at'] ?? '');
            if ($claimAt !== '') {
                try {
                    if (\Carbon\Carbon::parse($claimAt)->greaterThan(now()->subMinutes(15))) {
                        // Another worker claimed this payment within the last 15 minutes.
                        return null;
                    }
                } catch (Throwable) {
                    // stale/invalid claim — allow reclaim
                }
            }

            $meta['receipt_claim_at'] = now()->toIso8601String();
            $meta['receipt_claim_by'] = 'payment-receipt-job';
            $locked->update(['meta' => $meta]);

            return $locked->fresh();
        });
    }

    private function isBeforeCurrentMonth(PmPayment $payment): bool
    {
        $paidAt = $payment->paid_at ?? $payment->created_at;
        if ($paidAt === null) {
            return false;
        }

        return $paidAt->copy()->timezone(config('app.timezone'))->lt(now()->startOfMonth());
    }

    private function suppressPastMonthReceipt(PmPayment $payment): void
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        if (! empty($meta['skip_notification']) && ($meta['receipt_skip_reason'] ?? '') === 'past_month') {
            return;
        }

        $meta['skip_notification'] = true;
        $meta['receipt_skip_reason'] = 'past_month';
        unset($meta['receipt_claim_at'], $meta['receipt_claim_by']);
        $payment->update(['meta' => $meta]);
    }

    private function markNotified(PmPayment $payment, bool $sentSms, bool $sentEmail, string $channel): void
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['receipt_notified_at'] = now()->toIso8601String();
        $meta['receipt_notify'] = [
            'sms' => $sentSms,
            'email' => $sentEmail,
            'channel' => $channel,
        ];
        unset($meta['receipt_claim_at'], $meta['receipt_claim_by'], $meta['receipt_claim_error']);
        $payment->update(['meta' => $meta]);
    }

    private function releaseClaim(PmPayment $payment, string $error): void
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        unset($meta['receipt_claim_at'], $meta['receipt_claim_by']);
        $meta['receipt_claim_error'] = $error;
        $meta['receipt_claim_failed_at'] = now()->toIso8601String();
        $payment->update(['meta' => $meta]);
    }

    private function buildBody(PmPayment $payment, PmTenant $tenant): string
    {
        $amount = number_format((float) $payment->amount, 2);
        $ref = trim((string) ($payment->external_ref ?? ''));
        $account = trim((string) ($tenant->account_number ?? ''));
        $paidAt = $payment->paid_at?->format('d M Y H:i') ?? now()->format('d M Y H:i');
        $brand = trim((string) (\App\Support\Property\PropertyWorkspaceBranding::get('company_name', config('app.name')) ?? config('app.name')));
        $forWhat = $this->allocationSummary($payment);

        $parts = [
            $brand.': payment of KES '.$amount.' received',
        ];
        if ($forWhat !== '') {
            $parts[] = 'For '.$forWhat;
        }
        if ($ref !== '') {
            $parts[] = 'Ref '.$ref;
        }
        if ($account !== '') {
            $parts[] = 'Ac/No '.$account;
        }
        $parts[] = $paidAt.'. Thank you.';

        return implode('. ', $parts);
    }

    private function allocationSummary(PmPayment $payment): string
    {
        $lines = [];
        foreach ($payment->allocations ?? [] as $allocation) {
            if ((bool) ($allocation->is_reversed ?? false)) {
                continue;
            }
            $amount = (float) ($allocation->amount ?? 0);
            if ($amount <= 0.009) {
                continue;
            }

            $invoice = $allocation->invoice;
            if (! $invoice) {
                $lines[] = 'KES '.number_format($amount, 2);
                continue;
            }

            $label = method_exists($invoice, 'chargeCategoryLabel')
                ? (string) $invoice->chargeCategoryLabel()
                : ucfirst((string) ($invoice->invoice_type ?? 'charge'));
            $period = trim((string) ($invoice->billing_period ?? ''));
            if ($period === '' && $invoice->issue_date) {
                $period = $invoice->issue_date->format('M Y');
            } elseif (preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
                try {
                    $period = \Carbon\Carbon::createFromFormat('Y-m', $period)->format('M Y');
                } catch (\Throwable) {
                    // keep raw period
                }
            }

            $piece = $label;
            if ($period !== '') {
                $piece .= ' '.$period;
            }
            $piece .= ' KES '.number_format($amount, 2);
            $lines[] = $piece;
        }

        if ($lines === []) {
            $credit = (float) data_get($payment->meta, 'tenant_credit_amount', 0);
            if ($credit > 0.009) {
                return 'account credit KES '.number_format($credit, 2);
            }

            return '';
        }

        // Keep SMS short: at most 3 allocation lines.
        $lines = array_slice($lines, 0, 3);

        return implode('; ', $lines);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function sendSms(PmTenant $tenant, string $body): array
    {
        if (! $this->tenantAllowsChannel((int) $tenant->id, 'sms')) {
            return ['ok' => false, 'message' => 'Tenant opted out of SMS.'];
        }

        $phone = trim((string) ($tenant->phone ?? ''));
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Tenant has no phone.'];
        }

        $phones = $this->sms->normalizeRecipientList($phone);
        if ($phones === []) {
            return ['ok' => false, 'message' => 'Invalid tenant phone.'];
        }

        try {
            $result = $this->sms->sendNow($body, $phones, null, null, 'property');
            if (($result['ok'] ?? false) === true) {
                PmMessageLog::query()->create([
                    'user_id' => null,
                    'channel' => 'sms',
                    'to_address' => implode(',', $phones),
                    'subject' => 'payment_receipt',
                    'body' => $body,
                    'delivery_status' => 'sent',
                    'sent_at' => now(),
                ]);

                return ['ok' => true, 'message' => ''];
            }

            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'SMS send failed.')];
        } catch (Throwable $e) {
            Log::warning('Payment receipt SMS failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function sendEmail(PmTenant $tenant, string $body, PmPayment $payment): array
    {
        if (! $this->tenantAllowsChannel((int) $tenant->id, 'email')) {
            return ['ok' => false, 'message' => 'Tenant opted out of email.'];
        }

        $email = trim((string) ($tenant->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Tenant has no valid email.'];
        }

        try {
            $subject = 'Payment receipt — KES '.number_format((float) $payment->amount, 2);
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            PmMessageLog::query()->create([
                'user_id' => null,
                'channel' => 'email',
                'to_address' => $email,
                'subject' => $subject,
                'body' => $body,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);

            return ['ok' => true, 'message' => ''];
        } catch (Throwable $e) {
            Log::warning('Payment receipt email failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function tenantAllowsChannel(int $tenantId, string $channel): bool
    {
        if ($tenantId <= 0 || ! class_exists(PmMessagePreference::class)) {
            return true;
        }

        try {
            $pref = PmMessagePreference::query()->where('pm_tenant_id', $tenantId)->first();
            if (! $pref) {
                return true;
            }
            if ($channel === 'sms') {
                return (bool) ($pref->allow_sms ?? true);
            }
            if ($channel === 'email') {
                return (bool) ($pref->allow_email ?? true);
            }
        } catch (Throwable) {
            return true;
        }

        return true;
    }
}
