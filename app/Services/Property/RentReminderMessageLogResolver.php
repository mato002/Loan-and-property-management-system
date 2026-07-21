<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmMessageLog;
use App\Models\PmTenant;
use App\Services\BulkSmsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Rebuild rent reminder SMS/email bodies from current templates when resending log rows.
 */
final class RentReminderMessageLogResolver
{
    public function __construct(
        private readonly PropertyCommunicationTemplateService $templates,
        private readonly TenantCommunicationStageService $stages,
        private readonly PropertyAgentContactResolver $agentContacts,
        private readonly BulkSmsService $sms,
    ) {
    }

    public function isRentReminder(PmMessageLog $log): bool
    {
        if ((string) $log->template_category === 'rent_reminder') {
            return true;
        }

        if (filled($log->internal_stage)) {
            return true;
        }

        $subject = strtoupper((string) $log->subject);

        return str_contains($subject, '[ARREARS]')
            || str_contains($subject, '[STAFF|')
            || str_contains($subject, '[RENT]');
    }

    public function resolveSmsBody(PmMessageLog $log): ?string
    {
        $context = $this->buildContext($log);

        return $context !== null
            ? $this->templates->resolveRentReminderSms($context)
            : null;
    }

    /**
     * @return array{subject: string, internal_stage: string, display_stage: string}|null
     */
    public function resolveStaffMeta(PmMessageLog $log): ?array
    {
        $context = $this->buildContext($log);
        if ($context === null) {
            return null;
        }

        $stage = (array) ($context['stage'] ?? []);
        $invoiceNo = (string) ($context['invoice_no'] ?? '');

        return [
            'subject' => $this->stages->staffSubjectLine([
                'internal_stage' => (string) ($stage['internal_stage'] ?? ''),
                'display_label' => (string) ($stage['display_label'] ?? ''),
                'invoice_no' => $invoiceNo,
            ]),
            'internal_stage' => (string) ($stage['internal_stage'] ?? ''),
            'display_stage' => (string) ($stage['display_label'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildContext(PmMessageLog $log): ?array
    {
        if (! $this->isRentReminder($log)) {
            return null;
        }

        $invoice = $this->resolveInvoice($log);
        if ($invoice === null) {
            return null;
        }

        $invoice->loadMissing(['tenant:id,name,email,phone', 'unit:id,label,property_id', 'unit.property:id,name']);
        $invoice->syncAmountPaidFromAllocations();
        $invoice->refresh();

        $stage = $this->resolveStage($invoice, $log);
        if ($stage === null) {
            return null;
        }

        $unitName = trim((string) (($invoice->unit?->property?->name ?? '').'/'.($invoice->unit?->label ?? '')), '/');

        return $this->agentContacts->mergeIntoContext([
            'tenant_name' => (string) ($invoice->tenant?->name ?? 'Tenant'),
            'invoice_no' => (string) $invoice->invoice_no,
            'unit_name' => $unitName !== '' ? $unitName : '—',
            'balance' => number_format($invoice->balanceFloat(), 2),
            'due_date' => $invoice->due_date?->toDateString() ?? '',
            'stage' => $stage,
        ], $invoice);
    }

    private function resolveInvoice(PmMessageLog $log): ?PmInvoice
    {
        $invoiceNo = $this->extractInvoiceNo($log);
        if ($invoiceNo !== '') {
            $byNo = PmInvoice::query()
                ->withoutGlobalScopes()
                ->where('invoice_no', $invoiceNo)
                ->first();
            if ($byNo !== null) {
                return $byNo;
            }
        }

        return $this->resolveInvoiceByRecipient($log);
    }

    private function resolveInvoiceByRecipient(PmMessageLog $log): ?PmInvoice
    {
        $phones = $this->sms->normalizeRecipientList((string) $log->to_address);
        $email = strtolower(trim((string) $log->to_address));

        $tenantQuery = PmTenant::query()->withoutGlobalScopes();

        if ($phones !== []) {
            $tenantQuery->where(function ($q) use ($phones) {
                foreach ($phones as $phone) {
                    $suffix = substr($phone, -9);
                    if ($suffix !== '') {
                        $q->orWhere('phone', 'like', '%'.$suffix);
                    }
                }
            });
        } elseif ($email !== '' && str_contains($email, '@')) {
            $tenantQuery->whereRaw('LOWER(email) = ?', [$email]);
        } else {
            return null;
        }

        $tenant = $tenantQuery->orderByDesc('id')->first();
        if ($tenant === null) {
            return null;
        }

        return PmInvoice::query()
            ->withoutGlobalScopes()
            ->openBillable()
            ->where('pm_tenant_id', $tenant->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStage(PmInvoice $invoice, PmMessageLog $log): ?array
    {
        $internal = trim((string) ($log->internal_stage ?? ''));
        if ($internal === '') {
            $parsed = $this->stages->parseStaffSubject($log->subject);
            $internal = trim((string) ($parsed['internal_stage'] ?? ''));
        }

        if ($this->isCanonicalInternalStage($internal)) {
            return $this->stageFromInternalCode($internal, (string) ($log->display_stage ?? ''));
        }

        if ($invoice->due_date !== null) {
            $fromDue = $this->stages->resolveFromDueDate(
                $invoice->due_date->copy()->startOfDay(),
                Carbon::now()->startOfDay()
            );

            if ($fromDue !== null) {
                return $fromDue;
            }

            // Resend may target invoices outside D-3/D-1 windows; still rebuild with current templates.
            return $this->fallbackStageForTemplate($invoice);
        }

        return $this->stageFromInternalCode('D-3', 'Rent reminder');
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackStageForTemplate(PmInvoice $invoice): array
    {
        $due = $invoice->due_date?->copy()->startOfDay();
        $asOf = Carbon::now()->startOfDay();

        if ($due === null || $due->gte($asOf)) {
            return $this->stageFromInternalCode('D-3', 'Upcoming rent');
        }

        $daysOverdue = (int) $due->diffInDays($asOf);
        $bucket = $this->stages->bucketStageKeyForDaysOverdue($daysOverdue);
        $def = $this->stages->stageDefinition($bucket) ?? [];

        return $this->stageFromInternalCode(
            'D+'.$daysOverdue,
            (string) Arr::get($def, 'display_label', $bucket)
        );
    }

    private function isCanonicalInternalStage(string $internal): bool
    {
        return (bool) preg_match('/^D[+-]\d+$/', $internal)
            || in_array($internal, ['LEGAL', 'COLLECTIONS', 'FINAL_DEMAND'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function stageFromInternalCode(string $internal, string $displayOverride): array
    {
        $bucket = str_starts_with($internal, 'D-') || $internal === 'D+0'
            ? $internal
            : (in_array($internal, ['LEGAL', 'COLLECTIONS', 'FINAL_DEMAND'], true)
                ? $internal
                : $this->stages->bucketStageKeyForDaysOverdue((int) substr($internal, 2)));

        $def = $this->stages->stageDefinition($bucket) ?? [];

        return [
            'stage_key' => $bucket,
            'internal_stage' => $internal,
            'display_label' => $displayOverride !== ''
                ? $displayOverride
                : (string) Arr::get($def, 'display_label', $internal),
            'stage_number' => (int) Arr::get($def, 'number', 0),
            'sms_header' => (string) Arr::get($def, 'sms_header', 'RENT REMINDER'),
            'email_subject' => (string) Arr::get($def, 'email_subject', 'Rent reminder'),
            'stage_message' => (string) Arr::get($def, 'stage_message', ''),
            'days_until_due' => null,
            'days_overdue' => str_starts_with($internal, 'D+') && $internal !== 'D+0'
                ? (int) substr($internal, 2)
                : 0,
        ];
    }

    private function extractInvoiceNo(PmMessageLog $log): string
    {
        $haystack = trim((string) $log->subject).' '.trim((string) $log->body);
        if (preg_match('/\b(INV-[\w-]+)\b/i', $haystack, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }
}
