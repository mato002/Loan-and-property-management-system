<?php

namespace App\View\Components\Property;

use App\Support\Property\PropertyUiVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class FilterToolbar extends Component
{
    /**
     * @param  list<string>  $chipExclude
     * @param  array<string, string>  $chipLabels
     * @param  array<string, list<string|int>>  $chipIgnoreValues  param => values to treat as empty
     */
    public function __construct(
        public ?string $action = null,
        public string $method = 'get',
        public ?string $resetUrl = null,
        public string $turboFrame = 'property-main',
        public bool $submitFilters = true,
        public array $chipExclude = ['sort', 'dir', 'per_page', 'page', 'export'],
        public array $chipLabels = [],
        public array $chipIgnoreValues = [
            'tenant_id' => ['0', 0],
            'unit_id' => ['0', 0],
            'property_id' => ['0', 0],
        ],
        public string $drawerLabel = 'Filters',
        /** When set, adds data-revenue-date-filter on the GET form (invoices|payments). */
        public ?string $revenueDateFilter = null,
    ) {
        if ($this->resetUrl === null && $this->action !== null) {
            $this->resetUrl = $this->action;
        }
    }

    public function formId(): string
    {
        return 'property-filter-form-'.substr(md5((string) ($this->action ?? 'client').$this->drawerLabel), 0, 8);
    }

    /**
     * @return Collection<int, array{key: string, label: string, value: string, removeUrl: string}>
     */
    public function activeChips(): Collection
    {
        if (! $this->submitFilters) {
            return collect();
        }

        $query = collect(request()->query())
            ->except($this->chipExclude)
            ->filter(function ($value, $key) {
                if (is_null($value) || $value === '') {
                    return false;
                }

                $ignored = $this->chipIgnoreValues[$key] ?? null;
                if (is_array($ignored) && in_array($value, $ignored, true)) {
                    return false;
                }

                return true;
            });

        return $query->map(function ($value, $key) {
            $label = $this->chipLabels[$key] ?? Str::headline((string) $key);
            $display = is_scalar($value) ? (string) $value : json_encode($value);

            return [
                'key' => (string) $key,
                'label' => $label,
                'value' => $display,
                'removeUrl' => $this->removeChipUrl((string) $key),
            ];
        })->values();
    }

    public function activeFilterCount(): int
    {
        return $this->activeChips()->count();
    }

    protected function removeChipUrl(string $key): string
    {
        $params = request()->query();
        unset($params[$key]);

        $base = $this->action ?? url()->current();

        return $params === []
            ? $base
            : $base.'?'.http_build_query($params);
    }

    public function render(): View
    {
        return view(PropertyUiVersion::componentView('filter-toolbar'));
    }
}
