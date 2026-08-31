<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmMessageLog;
use App\Models\PmMessagePreference;
use App\Models\User;
use App\Services\BulkSmsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class InvoiceTenantDeliveryService
{
    public function __construct(
        private readonly InvoicePdfService $pdf,
        private readonly BulkSmsService $sms,
    ) {}

    /**
     * Deliver an issued invoice to the tenant by email and/or SMS.
     *
     * @param  array{
     *     channel?: 'email'|'sms'|'both'|'auto',
     *     override_email?: string|null,
     *     override_phone?: string|null,
     *     message?: string|null,
     * }  $options
     */
    public function deliver(PmInvoice $invoice, ?User $actor = null, array $options = []): InvoiceDeliveryResult
    {
        if ((string) $invoice->status === PmInvoice::STATUS_DRAFT) {
            return new InvoiceDeliveryResult(errors: ['Draft invoices cannot be delivered. Issue the invoice first.']);
        }

        if ((string) $invoice->status === PmInvoice::STATUS_CANCELLED) {
            return new InvoiceDeliveryResult(errors: ['Cancelled invoices cannot be delivered.']);
        }

        $invoice->loadMissing(['tenant:id,name,phone,email', 'unit.property:id,name', 'events']);
        $tenant = $invoice->tenant;

        $channel = (string) ($options['channel'] ?? 'auto');
        if ($channel === 'auto') {
            $channel = $this->resolveAutoChannel($invoice);
        }

        if ($channel === '') {
            return new InvoiceDeliveryResult(errors: ['Tenant has no email or phone on file.']);
        }

        $skipIfDelivered = (bool) ($options['skip_if_delivered'] ?? false);

        $shareToken = $invoice->ensureShareToken();
        $publicUrl = URL::to(route('property.invoices.public.show', ['token' => $shareToken], false));

        $defaultMessage = sprintf(
            'Hello %s, your invoice %s for KES %s is due %s. View / pay: %s',
            $tenant?->name ?? 'Tenant',
            $invoice->invoice_no,
            number_format((float) $invoice->amount - (float) $invoice->amount_paid, 2),
            optional($invoice->due_date)->format('Y-m-d') ?? '',
            $publicUrl,
        );
        $body = trim((string) ($options['message'] ?? '')) !== '' ? (string) $options['message'] : $defaultMessage;

        $emailedCount = 0;
        $smsedCount = 0;
        $errors = [];

        if (in_array($channel, ['email', 'both'], true)) {
            $emailResult = $this->sendEmail($invoice, $actor, $body, (string) ($options['override_email'] ?? ''), $skipIfDelivered);
            $emailedCount += $emailResult['sent'];
            $errors = array_merge($errors, $emailResult['errors']);
        }

        if (in_array($channel, ['sms', 'both'], true)) {
            $smsResult = $this->sendSms($invoice, $actor, $body, (string) ($options['override_phone'] ?? ''), $skipIfDelivered);
            $smsedCount += $smsResult['sent'];
            $errors = array_merge($errors, $smsResult['errors']);
        }

        if (($emailedCount + $smsedCount) > 0 && empty($invoice->sent_at)) {
            $invoice->update([
                'sent_at' => now(),
                'sent_by_user_id' => $actor?->id,
            ]);
        }

        return new InvoiceDeliveryResult($emailedCount, $smsedCount, $errors);
    }

    public function resolveAutoChannel(PmInvoice $invoice): string
    {
        $tenant = $invoice->tenant;
        $email = trim((string) ($tenant?->email ?? ''));
        $phone = trim((string) ($tenant?->phone ?? ''));

        $canEmail = $email !== '' && $this->tenantAllowsChannel((int) ($invoice->pm_tenant_id ?? 0), 'email');
        $canSms = $phone !== '' && $this->tenantAllowsChannel((int) ($invoice->pm_tenant_id ?? 0), 'sms');

        if ($canEmail && $canSms) {
            return 'both';
        }
        if ($canEmail) {
            return 'email';
        }
        if ($canSms) {
            return 'sms';
        }

        return '';
    }

    public function tenantAllowsChannel(int $tenantId, string $channel): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        $pref = PmMessagePreference::query()
            ->where('subject_type', 'tenant')
            ->where('subject_id', $tenantId)
            ->where('category', 'invoice')
            ->first();

        if (! $pref) {
            return true;
        }

        return match (strtolower($channel)) {
            'sms' => (bool) $pref->allow_sms,
            'email' => (bool) $pref->allow_email,
            default => true,
        };
    }

    /**
     * @return array{sent: int, errors: list<string>}
     */
    private function sendEmail(PmInvoice $invoice, ?User $actor, string $body, string $overrideEmail, bool $skipIfDelivered = false): array
    {
        $tenantId = (int) ($invoice->pm_tenant_id ?? 0);
        if (! $this->tenantAllowsChannel($tenantId, 'email')) {
            return ['sent' => 0, 'errors' => ['Tenant opted out of invoice email.']];
        }

        if ($skipIfDelivered && $invoice->events->contains('event', PmInvoiceEvent::EVENT_EMAILED)) {
            return ['sent' => 0, 'errors' => []];
        }

        $emailTo = trim($overrideEmail !== '' ? $overrideEmail : (string) ($invoice->tenant?->email ?? ''));
        if ($emailTo === '') {
            return ['sent' => 0, 'errors' => ['Tenant has no email on file.']];
        }

        try {
            $pdf = $this->pdf->buildBinary($invoice);
            Mail::raw($body, function ($m) use ($emailTo, $invoice, $pdf) {
                $m->to($emailTo)
                    ->subject('Invoice '.$invoice->invoice_no)
                    ->attachData($pdf, 'invoice-'.$invoice->invoice_no.'.pdf', [
                        'mime' => 'application/pdf',
                    ]);
            });
            PmMessageLog::query()->create([
                'user_id' => $actor?->id,
                'channel' => 'email',
                'to_address' => $emailTo,
                'subject' => 'Invoice '.$invoice->invoice_no,
                'body' => $body,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);
            PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_EMAILED, $actor?->id, 'Email sent to '.$emailTo);

            return ['sent' => 1, 'errors' => []];
        } catch (\Throwable $e) {
            report($e);

            return ['sent' => 0, 'errors' => ['Email failed: '.$e->getMessage()]];
        }
    }

    /**
     * @return array{sent: int, errors: list<string>}
     */
    private function sendSms(PmInvoice $invoice, ?User $actor, string $body, string $overridePhone, bool $skipIfDelivered = false): array
    {
        $tenantId = (int) ($invoice->pm_tenant_id ?? 0);
        if (! $this->tenantAllowsChannel($tenantId, 'sms')) {
            return ['sent' => 0, 'errors' => ['Tenant opted out of invoice SMS.']];
        }

        if ($skipIfDelivered && $invoice->events->contains('event', PmInvoiceEvent::EVENT_SMS_SENT)) {
            return ['sent' => 0, 'errors' => []];
        }

        $phone = trim($overridePhone !== '' ? $overridePhone : (string) ($invoice->tenant?->phone ?? ''));
        if ($phone === '') {
            return ['sent' => 0, 'errors' => ['Tenant has no phone on file.']];
        }

        $phones = $this->sms->normalizeRecipientList($phone);
        if ($phones === []) {
            return ['sent' => 0, 'errors' => ['Invalid tenant phone number.']];
        }

        $result = $this->sms->sendNow($body, $phones, $actor?->id, null, 'property');
        if (($result['ok'] ?? false) === true) {
            PmMessageLog::query()->create([
                'user_id' => $actor?->id,
                'channel' => 'sms',
                'to_address' => implode(',', $phones),
                'body' => $body,
                'delivery_status' => 'sent',
                'sent_at' => now(),
            ]);
            PmInvoiceEvent::record((int) $invoice->id, PmInvoiceEvent::EVENT_SMS_SENT, $actor?->id, 'SMS sent to '.implode(',', $phones));

            return ['sent' => 1, 'errors' => []];
        }

        return ['sent' => 0, 'errors' => ['SMS failed: '.($result['error'] ?? 'unknown')]];
    }
}
