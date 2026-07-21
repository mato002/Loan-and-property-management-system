<?php

namespace App\Services\Property;

use App\Models\PmMessageTemplate;
use App\Models\PropertyPortalSetting;
use App\Services\BulkSmsService;

class PropertyCommunicationTemplateService
{
    public function __construct(
        private readonly TenantCommunicationStageService $stages,
        private readonly PropertyAgentContactResolver $agentContacts,
        private readonly SmsSegmentEstimator $smsSegments,
    ) {
    }

    /**
     * Unified rent reminder builder for tenant-facing channels.
     *
     * @param  array<string, mixed>  $context
     * @return array{subject:?string,body:string,channel:string,internal_stage:?string,display_label:?string,template_category:string}
     */
    public function buildRentReminder(array $context, string $channel = 'sms'): array
    {
        $channel = strtolower(trim($channel));
        $stage = (array) ($context['stage'] ?? []);
        $statusMessage = $this->statusMessage($context);

        return match ($channel) {
            'email' => $this->buildRentReminderEmailPayload($context, $stage, $statusMessage),
            'whatsapp' => $this->buildRentReminderWhatsappPayload($context, $stage, $statusMessage),
            'portal', 'system' => $this->buildRentReminderPortalPayload($context, $stage, $statusMessage),
            default => $this->buildRentReminderSmsPayload($context, $stage, $statusMessage),
        };
    }

    /** @param array<string, mixed> $context */
    public function buildRentReminderSms(array $context): string
    {
        return $this->buildRentReminder($context, 'sms')['body'];
    }

    /** @param array<string, mixed> $context */
    public function buildRentReminderEmail(array $context): array
    {
        $payload = $this->buildRentReminder($context, 'email');

        return [
            'subject' => (string) ($payload['subject'] ?? 'Rent reminder'),
            'body' => (string) $payload['body'],
            'internal_stage' => $payload['internal_stage'],
            'display_label' => $payload['display_label'],
            'template_category' => 'rent_reminder',
        ];
    }

    /** @param array<string, mixed> $context */
    public function resolveRentReminderSms(array $context): string
    {
        $template = PmMessageTemplate::query()
            ->where('category', 'rent_reminder')
            ->where('channel', 'sms')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($template && trim((string) $template->body) !== '') {
            $rendered = $this->renderTemplateBody((string) $template->body, $this->templateVariables($context));
            if (! $this->isLegacyRentSmsTemplate($rendered)) {
                return $rendered;
            }
        }

        return $this->buildRentReminderSms($context);
    }

    /**
     * Detect pre-2026-06 rent SMS layout still stored in some production DB rows.
     */
    public function isLegacyRentSmsTemplate(string $body): bool
    {
        $upper = strtoupper($body);

        if (str_contains($upper, 'STOP *456') || str_contains($upper, 'STOP *456*9*5#')) {
            return true;
        }

        if (preg_match('/\[RENT\]\s*INV-/i', $body)) {
            return true;
        }

        return preg_match('/\bINV-[\w-]+\b/i', $body) === 1
            && str_contains($upper, 'BAL:')
            && str_contains($upper, 'DUE:');
    }

    /**
     * @return array<string, mixed>
     */
    public function previewSampleContext(string $stageKey = 'D+7'): array
    {
        $def = $this->stages->stageDefinition($stageKey) ?? [];
        $asOf = now()->startOfDay();
        $due = match ($stageKey) {
            'D-3' => $asOf->copy()->addDays(3),
            'D-1' => $asOf->copy()->addDay(),
            'D+0' => $asOf->copy(),
            'D+3' => $asOf->copy()->subDays(3),
            'D+7' => $asOf->copy()->subDays(7),
            'D+14' => $asOf->copy()->subDays(14),
            'D+30' => $asOf->copy()->subDays(30),
            default => $asOf->copy()->subDays(7),
        };

        return [
            'tenant_name' => 'Mary Ndugu',
            'invoice_no' => 'INV-000242',
            'invoice_number' => 'INV-000242',
            'unit_name' => 'Sunset Apartments / A-12',
            'balance' => '12,500.00',
            'due_date' => $due->toDateString(),
            'stage' => [
                'stage_key' => $stageKey,
                'internal_stage' => $stageKey,
                'display_label' => (string) ($def['display_label'] ?? $stageKey),
                'email_subject' => (string) ($def['email_subject'] ?? 'Rent reminder'),
            ],
            'agent' => [
                'name' => 'Peter Kamau',
                'phone' => '0712345678',
                'email' => 'agent@example.com',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *   channel:string,
     *   stage_key:string,
     *   subject:?string,
     *   body:string,
     *   sms_segments:int,
     *   sms_chars:int,
     *   estimated_cost:float,
     *   currency:string
     * }
     */
    public function previewRentReminder(string $stageKey, string $channel = 'sms', array $overrides = []): array
    {
        $context = array_merge($this->previewSampleContext($stageKey), $overrides);

        if (! empty($overrides['stage_message'])) {
            $context['status_message'] = $this->stages->renderMessageText((string) $overrides['stage_message'], [
                'tenant_name' => $this->tenantDisplayName($context),
                'unit_name' => (string) ($context['unit_name'] ?? ''),
                'due_date' => (string) ($context['due_date'] ?? ''),
                'balance' => (string) ($context['balance'] ?? ''),
                'invoice_no' => (string) ($context['invoice_no'] ?? ''),
                'invoice_number' => (string) ($context['invoice_no'] ?? ''),
            ]);
        }

        $payload = $this->buildRentReminder($context, $channel);
        $body = (string) $payload['body'];
        $estimate = $this->smsSegments->estimate($body);
        $bulk = app(BulkSmsService::class);

        return [
            'channel' => strtolower($channel),
            'stage_key' => $stageKey,
            'subject' => $payload['subject'] ?? null,
            'body' => $body,
            'sms_segments' => (int) ($estimate['segments'] ?? 0),
            'sms_chars' => (int) ($estimate['chars'] ?? 0),
            'estimated_cost' => $this->smsSegments->estimatedCost($body, $bulk->costPerSms()),
            'currency' => $bulk->currency(),
        ];
    }

    public function renderTemplateBody(string $body, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return strtr($body, $replacements);
    }

    /** @param array<string, mixed> $context */
    public function templateVariables(array $context): array
    {
        $stage = (array) ($context['stage'] ?? []);
        $tenantName = $this->tenantDisplayName($context);
        $agent = (array) ($context['agent'] ?? $this->agentContacts->officeFallback());
        $statusMessage = $this->statusMessage($context);
        $invoiceNo = (string) ($context['invoice_no'] ?? $context['invoice_number'] ?? '');

        return [
            'tenant' => $tenantName,
            'tenant_name' => $tenantName,
            'invoice_no' => $invoiceNo,
            'invoice_number' => $invoiceNo,
            'unit_name' => (string) ($context['unit_name'] ?? ''),
            'property_unit' => (string) ($context['unit_name'] ?? ''),
            'balance' => (string) ($context['balance'] ?? ''),
            'balance_due' => (string) ($context['balance'] ?? ''),
            'due_date' => (string) ($context['due_date'] ?? ''),
            'days_overdue' => (string) ($stage['days_overdue'] ?? 0),
            'status_message' => $statusMessage,
            'stage_message' => $statusMessage,
            'display_label' => (string) ($stage['display_label'] ?? ''),
            'internal_stage' => (string) ($stage['internal_stage'] ?? ''),
            'sms_header' => (string) ($stage['sms_header'] ?? 'RENT REMINDER'),
            'system_name' => $this->systemName($context),
            'app_name' => $this->systemName($context),
            'agent_name' => (string) ($agent['name'] ?? ''),
            'agent_phone' => (string) ($agent['phone'] ?? ''),
            'agent_email' => (string) ($agent['email'] ?? ''),
            'agent_contact' => $this->agentContacts->formatSmsBlock($agent),
            'contact_phone' => (string) ($agent['phone'] ?? ''),
            'reply_link' => $this->replyLink($context),
            'unsubscribe_code' => $this->unsubscribeCode($context),
        ];
    }

    /** @return list<array{name:string,channel:string,category:string,subject:?string,body:string,supported_variables:list<string>}> */
    public function defaultTemplateDefinitions(): array
    {
        $sampleStage = $this->stages->stageDefinition('D+7') ?? [];
        $smsVars = '{system_name}, {tenant}, {status_message}, {invoice_no}, {invoice_number}, {unit_name}, {balance}, {agent_phone}, {contact_phone}';
        $smsBody = "{system_name}\n\nDear {tenant},\n\n{status_message}\n\nInvoice: {invoice_no}\nUnit: {unit_name}\nBalance: KES {balance}\n\nFor assistance, call {agent_phone}.";

        return [
            [
                'name' => 'Rent reminder — SMS',
                'channel' => 'sms',
                'category' => 'rent_reminder',
                'subject' => null,
                'body' => $smsBody,
                'supported_variables' => explode(', ', $smsVars),
            ],
            [
                'name' => 'Rent reminder — Email',
                'channel' => 'email',
                'category' => 'rent_reminder',
                'subject' => (string) ($sampleStage['email_subject'] ?? 'Rent reminder'),
                'body' => "{system_name}\n\nDear {tenant},\n\n{status_message}\n\nInvoice: {invoice_no}\nUnit: {unit_name}\nBalance due: KES {balance}\nDue date: {due_date}\n\n{agent_contact}\n\nIf you have already paid, please ignore this message.",
                'supported_variables' => ['system_name', 'tenant', 'status_message', 'invoice_no', 'unit_name', 'balance', 'due_date', 'agent_contact', 'agent_name', 'agent_phone', 'agent_email'],
            ],
            [
                'name' => 'Utility reminder — SMS',
                'channel' => 'sms',
                'category' => 'utility_reminder',
                'subject' => null,
                'body' => "[UTILITY REMINDER]\n\nInvoice: {invoice_no}\nUnit: {unit_name}\nBalance: KES {balance}\n\nYour utility bill is due. Please pay on time.\n\nNeed help?\n{reply_link}\n\n{unsubscribe_code}",
                'supported_variables' => ['invoice_no', 'unit_name', 'balance', 'reply_link', 'unsubscribe_code'],
            ],
            [
                'name' => 'Penalty notice — SMS',
                'channel' => 'sms',
                'category' => 'penalty_notice',
                'subject' => null,
                'body' => "[PENALTY NOTICE]\n\nUnit: {unit_name}\nBalance: KES {balance}\n\nA late payment penalty has been applied. Please settle promptly.\n\n{reply_link}",
                'supported_variables' => ['unit_name', 'balance', 'reply_link'],
            ],
            [
                'name' => 'Deposit balance — SMS',
                'channel' => 'sms',
                'category' => 'deposit_balance',
                'subject' => null,
                'body' => "[DEPOSIT NOTICE]\n\nUnit: {unit_name}\nOutstanding deposit: KES {balance}\n\nPlease contact the office if you have questions.\n\n{reply_link}",
                'supported_variables' => ['unit_name', 'balance', 'reply_link'],
            ],
            [
                'name' => 'Lease expiry — SMS',
                'channel' => 'sms',
                'category' => 'lease_expiry',
                'subject' => null,
                'body' => "[LEASE NOTICE]\n\nUnit: {unit_name}\n\nYour lease is nearing expiry. Contact management to discuss renewal.\n\n{reply_link}",
                'supported_variables' => ['unit_name', 'reply_link'],
            ],
            [
                'name' => 'Maintenance payment — SMS',
                'channel' => 'sms',
                'category' => 'maintenance_payment',
                'subject' => null,
                'body' => "[MAINTENANCE PAYMENT]\n\nUnit: {unit_name}\nAmount due: KES {balance}\n\nPlease settle the maintenance charge listed on your account.\n\n{reply_link}",
                'supported_variables' => ['unit_name', 'balance', 'reply_link'],
            ],
        ];
    }

    /** @param array<string, mixed> $context */
    private function statusMessage(array $context): string
    {
        if (! empty($context['status_message'])) {
            return trim((string) $context['status_message']);
        }

        $stage = (array) ($context['stage'] ?? []);
        $stageKey = (string) ($stage['stage_key'] ?? $stage['internal_stage'] ?? 'D+7');

        return $this->stages->renderStageMessage($stageKey, [
            'tenant_name' => $this->tenantDisplayName($context),
            'unit_name' => (string) ($context['unit_name'] ?? ''),
            'due_date' => (string) ($context['due_date'] ?? ''),
            'balance' => (string) ($context['balance'] ?? ''),
            'invoice_no' => (string) ($context['invoice_no'] ?? $context['invoice_number'] ?? ''),
            'invoice_number' => (string) ($context['invoice_no'] ?? $context['invoice_number'] ?? ''),
        ]);
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $stage */
    private function buildRentReminderSmsPayload(array $context, array $stage, string $statusMessage): array
    {
        $agent = (array) ($context['agent'] ?? $this->agentContacts->officeFallback());
        $lines = [
            $this->systemName($context),
            '',
            $this->tenantSalutation($context),
            '',
            $statusMessage,
            '',
            'Invoice: '.(string) ($context['invoice_no'] ?? $context['invoice_number'] ?? ''),
            'Unit: '.(string) ($context['unit_name'] ?? ''),
            'Balance: KES '.(string) ($context['balance'] ?? '0.00'),
            '',
            $this->agentContacts->formatSmsPhoneLine($agent),
        ];

        return [
            'subject' => null,
            'body' => trim(implode("\n", $lines)),
            'channel' => 'sms',
            'internal_stage' => $stage['internal_stage'] ?? null,
            'display_label' => $stage['display_label'] ?? null,
            'template_category' => 'rent_reminder',
        ];
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $stage */
    private function buildRentReminderEmailPayload(array $context, array $stage, string $statusMessage): array
    {
        $bodyLines = [
            $this->systemName($context),
            '',
            $this->tenantSalutation($context),
            '',
            $statusMessage,
            '',
            'Invoice: '.(string) ($context['invoice_no'] ?? $context['invoice_number'] ?? ''),
            'Unit: '.(string) ($context['unit_name'] ?? ''),
            'Balance due: KES '.(string) ($context['balance'] ?? '0.00'),
        ];

        if (! empty($context['due_date'])) {
            $bodyLines[] = 'Due date: '.(string) $context['due_date'];
        }

        $bodyLines[] = '';
        $bodyLines[] = $this->agentContacts->formatSmsBlock((array) ($context['agent'] ?? []));
        $bodyLines[] = '';
        $bodyLines[] = 'If you have already paid, please ignore this message.';

        return [
            'subject' => (string) ($stage['email_subject'] ?? 'Rent reminder'),
            'body' => trim(implode("\n", $bodyLines)),
            'channel' => 'email',
            'internal_stage' => $stage['internal_stage'] ?? null,
            'display_label' => $stage['display_label'] ?? null,
            'template_category' => 'rent_reminder',
        ];
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $stage */
    private function buildRentReminderWhatsappPayload(array $context, array $stage, string $statusMessage): array
    {
        $payload = $this->buildRentReminderSmsPayload($context, $stage, $statusMessage);
        $payload['channel'] = 'whatsapp';

        return $payload;
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $stage */
    private function buildRentReminderPortalPayload(array $context, array $stage, string $statusMessage): array
    {
        $lines = [
            $this->tenantSalutation($context),
            '',
            $statusMessage,
            '',
            'Invoice: '.(string) ($context['invoice_no'] ?? $context['invoice_number'] ?? ''),
            'Unit: '.(string) ($context['unit_name'] ?? ''),
            'Balance: KES '.(string) ($context['balance'] ?? '0.00'),
        ];

        return [
            'subject' => (string) ($stage['display_label'] ?? 'Rent reminder'),
            'body' => trim(implode("\n", $lines)),
            'channel' => 'portal',
            'internal_stage' => $stage['internal_stage'] ?? null,
            'display_label' => $stage['display_label'] ?? null,
            'template_category' => 'rent_reminder',
        ];
    }

    /** @param array<string, mixed> $context */
    private function tenantDisplayName(array $context): string
    {
        $name = trim((string) ($context['tenant_name'] ?? ''));

        return $name !== '' ? $name : 'Tenant';
    }

    /** @param array<string, mixed> $context */
    private function tenantSalutation(array $context): string
    {
        return 'Dear '.$this->tenantDisplayName($context).',';
    }

    /** @param array<string, mixed> $context */
    private function systemName(array $context = []): string
    {
        $override = trim((string) ($context['system_name'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        $company = trim((string) PropertyPortalSetting::getValue('company_name', ''));
        if ($company !== '') {
            return $company;
        }

        return trim((string) config('app.name', 'Property Management'));
    }

    /** @param array<string, mixed> $context */
    private function replyLink(array $context): string
    {
        $link = trim((string) ($context['reply_link'] ?? ''));
        if ($link !== '') {
            return $link;
        }

        return trim((string) PropertyPortalSetting::getValue(
            'tenant_portal_url',
            (string) config('property_communication.reply_link', config('app.url'))
        ));
    }

    /** @param array<string, mixed> $context */
    private function unsubscribeCode(array $context): string
    {
        $code = trim((string) ($context['unsubscribe_code'] ?? ''));

        return $code !== '' ? $code : (string) config('property_communication.sms_unsubscribe_code', 'STOP *456*9*5#');
    }
}
