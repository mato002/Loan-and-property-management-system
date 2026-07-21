<?php

namespace App\Services\Property;

use App\Models\PropertyPortalSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

class TenantCommunicationStageService
{
    /**
     * @return array{
     *   stage_key:string,
     *   internal_stage:string,
     *   display_label:string,
     *   stage_number:int,
     *   sms_header:string,
     *   email_subject:string,
     *   stage_message:string,
     *   days_until_due:?int,
     *   days_overdue:int
     * }|null
     */
    public function resolveFromDueDate(CarbonInterface $dueDate, CarbonInterface $asOf): ?array
    {
        $due = $dueDate->copy()->startOfDay();
        $asOf = $asOf->copy()->startOfDay();

        if ($due->gt($asOf)) {
            $daysUntil = (int) $asOf->diffInDays($due);
            $internalStage = 'D-'.$daysUntil;
            $stageKey = match ($daysUntil) {
                3 => 'D-3',
                1 => 'D-1',
                default => null,
            };

            if ($stageKey === null) {
                return null;
            }

            return $this->buildStagePayload($stageKey, $internalStage, $daysUntil, 0);
        }

        if ($due->eq($asOf)) {
            return $this->buildStagePayload('D+0', 'D+0', 0, 0);
        }

        $daysOverdue = (int) $due->diffInDays($asOf);
        $internalStage = 'D+'.$daysOverdue;
        $stageKey = $this->bucketStageKeyForDaysOverdue($daysOverdue);

        return $this->buildStagePayload($stageKey, $internalStage, 0, $daysOverdue);
    }

    /**
     * Map exact overdue days to the tenant-facing bucket template key.
     */
    public function bucketStageKeyForDaysOverdue(int $daysOverdue): string
    {
        if ($daysOverdue >= 90) {
            return 'LEGAL';
        }
        if ($daysOverdue >= 60) {
            return 'COLLECTIONS';
        }
        if ($daysOverdue >= 45) {
            return 'FINAL_DEMAND';
        }
        if ($daysOverdue >= 30) {
            return 'D+30';
        }
        if ($daysOverdue >= 14) {
            return 'D+14';
        }
        if ($daysOverdue >= 7) {
            return 'D+7';
        }
        if ($daysOverdue >= 3) {
            return 'D+3';
        }
        if ($daysOverdue >= 1) {
            return 'D+1';
        }

        return 'D+0';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stageDefinition(string $stageKey): ?array
    {
        $def = config('property_communication.stages.'.$stageKey);

        return is_array($def) ? $def : null;
    }

    /**
     * @return list<string>
     */
    public function editableStageKeys(): array
    {
        return ['D-3', 'D-1', 'D+0', 'D+1', 'D+3', 'D+7', 'D+14', 'D+30'];
    }

    public function stageMessageSettingKey(string $stageKey): string
    {
        return 'comm_stage_message__'.$stageKey;
    }

    public function stageMessageTemplate(string $stageKey): string
    {
        $custom = trim((string) PropertyPortalSetting::getValue($this->stageMessageSettingKey($stageKey), ''));
        if ($custom !== '') {
            return $custom;
        }

        return (string) Arr::get($this->stageDefinition($stageKey) ?? [], 'stage_message', '');
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function renderStageMessage(string $stageKey, array $variables): string
    {
        return $this->renderMessageText($this->stageMessageTemplate($stageKey), $variables);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function renderMessageText(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) ($value ?? '');
        }

        return trim(strtr($template, $replacements));
    }

    /**
     * @return array<string, string>
     */
    public function editableStageMessagesForForm(): array
    {
        $out = [];
        foreach ($this->editableStageKeys() as $stageKey) {
            $out[$stageKey] = $this->stageMessageTemplate($stageKey);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $messages
     */
    public function saveEditableStageMessages(array $messages): void
    {
        foreach ($this->editableStageKeys() as $stageKey) {
            if (! array_key_exists($stageKey, $messages)) {
                continue;
            }
            PropertyPortalSetting::setValue(
                $this->stageMessageSettingKey($stageKey),
                trim((string) $messages[$stageKey])
            );
        }
    }

    /**
     * @return list<string>
     */
    public function internalWorkflowCodes(): array
    {
        return ['D-3', 'D-1', 'D+0', 'D+1', 'D+3', 'D+7', 'D+14', 'D+30'];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function staffSubjectLine(array $context): string
    {
        $internal = (string) ($context['internal_stage'] ?? '');
        $label = (string) ($context['display_label'] ?? '');
        $invoiceNo = (string) ($context['invoice_no'] ?? '');

        return trim(sprintf('[STAFF|%s|%s] %s', $internal, $label, $invoiceNo));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStagePayload(
        string $stageKey,
        string $internalStage,
        int $daysUntil,
        int $daysOverdue
    ): array {
        $def = $this->stageDefinition($stageKey) ?? [];

        return [
            'stage_key' => $stageKey,
            'internal_stage' => $internalStage,
            'display_label' => (string) ($def['display_label'] ?? $stageKey),
            'stage_number' => (int) ($def['number'] ?? 0),
            'sms_header' => (string) ($def['sms_header'] ?? 'RENT REMINDER'),
            'email_subject' => (string) ($def['email_subject'] ?? 'Rent reminder'),
            'stage_message' => (string) ($def['stage_message'] ?? ''),
            'days_until_due' => $daysUntil > 0 ? $daysUntil : null,
            'days_overdue' => $daysOverdue,
        ];
    }

    /**
     * @return array{internal_stage:?string,display_label:?string}
     */
    public function parseStaffSubject(?string $subject): array
    {
        $subject = trim((string) $subject);
        if ($subject === '') {
            return ['internal_stage' => null, 'display_label' => null];
        }

        if (preg_match('/^\[STAFF\|([^|]+)\|([^\]]+)\]/', $subject, $matches)) {
            return [
                'internal_stage' => trim($matches[1]),
                'display_label' => trim($matches[2]),
            ];
        }

        if (preg_match('/\b(D[+-]\d+)\b/', $subject, $matches)) {
            $internal = $matches[1];
            $bucket = str_starts_with($internal, 'D-') || $internal === 'D+0'
                ? $internal
                : $this->bucketStageKeyForDaysOverdue((int) substr($internal, 2));
            $def = $this->stageDefinition($bucket);

            return [
                'internal_stage' => $internal,
                'display_label' => (string) Arr::get($def, 'display_label', $internal),
            ];
        }

        return ['internal_stage' => null, 'display_label' => null];
    }
}
