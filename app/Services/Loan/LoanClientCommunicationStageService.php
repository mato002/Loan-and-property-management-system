<?php

namespace App\Services\Loan;

final class LoanClientCommunicationStageService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function stageCatalog(): array
    {
        return (array) config('loan_communication.stages', []);
    }

    /**
     * @return list<string>
     */
    public function editableStageKeys(): array
    {
        return array_keys($this->stageCatalog());
    }

    /**
     * @return array<string, string>
     */
    public function editableStageMessagesForForm(): array
    {
        $out = [];
        foreach ($this->stageCatalog() as $key => $stage) {
            $out[$key] = (string) ($stage['stage_message'] ?? '');
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function stageDefinition(string $stageKey): array
    {
        return (array) ($this->stageCatalog()[$stageKey] ?? []);
    }

    /**
     * @param  array<string, string>  $messages
     */
    public function saveEditableStageMessages(array $messages): void
    {
        app(LoanCommunicationTemplateService::class)->saveStageMessages($messages);
    }

    /**
     * @return array{internal_stage: ?string, display_label: ?string}
     */
    public function parseStaffSubject(?string $subject): array
    {
        $subject = trim((string) $subject);
        if ($subject === '') {
            return ['internal_stage' => null, 'display_label' => null];
        }

        if (preg_match('/\[(D[+-]?\d+|FINAL_DEMAND|COLLECTIONS)\]/', $subject, $m)) {
            $key = (string) ($m[1] ?? '');
            $stage = $this->stageDefinition($key);

            return [
                'internal_stage' => $key,
                'display_label' => (string) ($stage['display_label'] ?? $key),
            ];
        }

        return ['internal_stage' => null, 'display_label' => null];
    }
}
