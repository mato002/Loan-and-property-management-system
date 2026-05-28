<?php

namespace App\View\Components\Property;

use App\Support\Property\PropertyEntityHub;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EntityHub extends Component
{
    public string $entity;

    public string $activeTab;

    /** @var list<array{key: string, label: string}> */
    public array $tabs;

    public string $routeName;

    /** @var array<string, mixed> */
    public array $routeParams;

    /** @var array<string, mixed> */
    public array $preserveQuery;

    /** @var list<array<string, mixed>> */
    public array $quickActions;

    /** @var list<array<string, mixed>> */
    public array $alerts;

    /**
     * @param  array<string, mixed>  $routeParams
     * @param  array<string, mixed>  $preserveQuery
     * @param  list<array<string, mixed>>  $quickActions
     * @param  list<array<string, mixed>>  $alerts
     */
    public function __construct(
        string $entity,
        string $routeName,
        array $routeParams = [],
        ?string $activeTab = null,
        array $preserveQuery = [],
        array $quickActions = [],
        array $alerts = [],
    ) {
        $this->entity = $entity;
        $this->routeName = $routeName;
        $this->routeParams = $routeParams;
        $this->preserveQuery = $preserveQuery;
        $this->activeTab = PropertyEntityHub::normalizeTab($entity, $activeTab);
        $this->tabs = PropertyEntityHub::tabsFor($entity);
        $this->quickActions = $quickActions;
        $this->alerts = $alerts;
    }

    public function tabUrl(string $tab): string
    {
        $query = array_filter(
            $this->preserveQuery,
            static fn ($value) => ! is_null($value) && $value !== ''
        );

        return PropertyEntityHub::tabUrl($this->routeName, $this->routeParams, $tab, $query);
    }

    public function render(): View
    {
        return view('components.property.entity-hub');
    }
}
