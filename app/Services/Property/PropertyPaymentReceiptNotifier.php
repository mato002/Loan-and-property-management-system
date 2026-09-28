<?php

namespace App\Services\Property;

use App\Models\PmMessageLog;
use App\Models\PmMessagePreference;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Services\BulkSmsService;
use App\Support\Property\MpesaIntegrationConfig;
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
        if (! MpesaIntegrationConfig::autoReceiptEnabled()) {
            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'Auto-receipt disabled.'];
        }

        if ($payment->status !== PmPayment::STATUS_COMPLETED) {
            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'Payment not completed.'];
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        if (! empty($meta['receipt_notified_at'])) {
            return ['sent_sms' => false, 'sent_email' => false, 'skipped' => true, 'message' => 'Already notified.'];
        }

        $payment->loadMissing(['tenant:id,name,phone,email,account_number']);
        $tenant = $payment->tenant;
        if (! $tenant) {
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
            $meta['receipt_notified_at'] = now()->toIso8601String();
            $meta['receipt_notify'] = [
                'sms' => $sentSms,
                'email' => $sentEmail,
                'channel' => $channel,
            ];
            $payment->update(['meta' => $meta]);
        }

        return [
            'sent_sms' => $sentSms,
            'sent_email' => $sentEmail,
            'skipped' => ! $sentSms && ! $sentEmail,
            'message' => $errors !== [] ? implode(' ', $errors) : ($sentSms || $sentEmail ? 'Receipt sent.' : 'No delivery channel available.'),
        ];
    }

    private function buildBody(PmPayment $payment, PmTenant $tenant): string
    {
        $amount = number_format((float) $payment->amount, 2);
        $ref = trim((string) ($payment->external_ref ?? ''));
        $account = trim((string) ($tenant->account_number ?? ''));
        $paidAt = $payment->paid_at?->format('d M Y H:i') ?? now()->format('d M Y H:i');
        $brand = trim((string) (\App\Support\Property\PropertyWorkspaceBranding::get('company_name', config('app.name')) ?? config('app.name')));

        $parts = [
            $brand.': payment of KES '.$amount.' received',
        ];
        if ($ref !== '') {
            $parts[] = 'Ref '.$ref;
        }
        if ($account !== '') {
            $parts[] = 'Acct '.$account;
        }
        $parts[] = $paidAt.'. Thank you.';

        return implode('. ', $parts);
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
