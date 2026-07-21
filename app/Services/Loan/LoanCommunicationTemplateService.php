<?php

namespace App\Services\Loan;

use App\Models\LmMessageTemplate;
use App\Services\BulkSmsService;
use App\Services\Property\SmsSegmentEstimator;

class LoanCommunicationTemplateService
{
    public function __construct(
        private readonly LoanClientCommunicationStageService $stages,
        private readonly SmsSegmentEstimator $smsSegments,
    ) {
    }

    public function previewRentReminder(string $stageKey, string $channel = 'sms', array $overrides = []): array
    {
        $context = array_merge($this->previewSampleContext($stageKey), $overrides);
        $stage = $this->stages->stageDefinition($stageKey);
        $body = (string) ($overrides['stage_message'] ?? $stage['stage_message'] ?? '');
        $body = $this->render($body, $this->templateVariables($context));

        if (strtolower($channel) === 'sms') {
            $header = (string) ($stage['sms_header'] ?? 'LOAN REMINDER');
            $body = trim($header."\n\n".$body);
        }

        $estimate = $this->smsSegments->estimate($body);
        $bulk = app(BulkSmsService::class);

        return [
            'channel' => strtolower($channel),
            'stage_key' => $stageKey,
            'subject' => $this->render((string) ($stage['email_subject'] ?? 'Loan payment reminder'), $this->templateVariables($context)),
            'body' => $body,
            'sms_segments' => (int) ($estimate['segments'] ?? 0),
            'estimated_cost' => round(((int) ($estimate['segments'] ?? 0)) * $bulk->costPerSms(), 4),
            'currency' => $bulk->currency(),
        ];
    }

    /**
     * @param  array<string, string>  $messages keyed by stage
     */
    public function saveStageMessages(array $messages): void
    {
        foreach ($messages as $stageKey => $body) {
            $body = trim((string) $body);
            if ($body === '') {
                continue;
            }

            LmMessageTemplate::query()->updateOrCreate(
                [
                    'category' => 'payment_reminder',
                    'channel' => 'sms',
                    'name' => 'Payment reminder '.$stageKey,
                ],
                [
                    'body' => $body,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function previewSampleContext(string $stageKey): array
    {
        return [
            'stage_key' => $stageKey,
            'client_name' => 'Jane Client',
            'loan_number' => 'LN-1024',
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'amount_due' => '12,500.00',
            'branch' => 'Main branch',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function templateVariables(array $context): array
    {
        return [
            'client_name' => (string) ($context['client_name'] ?? 'Client'),
            'loan_number' => (string) ($context['loan_number'] ?? 'LN-0001'),
            'due_date' => (string) ($context['due_date'] ?? now()->addDay()->format('Y-m-d')),
            'amount_due' => (string) ($context['amount_due'] ?? '0.00'),
            'branch' => (string) ($context['branch'] ?? 'Main branch'),
        ];
    }

    private function render(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return $text;
    }
}
