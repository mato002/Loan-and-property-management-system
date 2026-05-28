<?php

namespace App\View\Components\Property;

use App\Support\Property\PropertyUiVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class BulkActionBar extends Component
{
    /**
     * @param  list<array{value: string, label: string, permission?: string|null}>  $actions
     */
    public function __construct(
        public string $formId = 'property-bulk-form',
        public ?string $action = null,
        public string $method = 'POST',
        public string $turboFrame = 'property-main',
        public ?string $confirm = null,
        public string $rowCheckboxSelector = '.property-bulk-row-checkbox',
        /** submit: POST bulk form; selection: select-all/clear/count only (e.g. arrears reminders) */
        public string $mode = 'submit',
        public ?string $syncForm = null,
        public ?string $syncInput = null,
        public array $actions = [],
        public string $applyLabel = 'Apply',
        public bool $showWhenEmpty = false,
    ) {}

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function visibleActions(): Collection
    {
        $user = auth()->user();

        return collect($this->actions)->filter(function (array $action) use ($user) {
            $permission = $action['permission'] ?? null;
            if ($permission === null || $permission === '') {
                return true;
            }

            return $user?->hasPmPermission((string) $permission) ?? false;
        })->map(static fn (array $action) => [
            'value' => (string) ($action['value'] ?? ''),
            'label' => (string) ($action['label'] ?? ''),
        ])->values();
    }

    public function render(): View
    {
        return view(PropertyUiVersion::componentView('bulk-action-bar'));
    }
}
