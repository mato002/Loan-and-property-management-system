@php
    $fieldOfficersUrl = route('property.field_officers.index', absolute: false);
    $drawerLabel = 'Field officer filters';
@endphp

<x-property.filter-toolbar
    :action="$fieldOfficersUrl"
    :reset-url="$fieldOfficersUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'portfolio' => 'Portfolio',
        'portal_access' => 'Portal',
        'agent_user_id' => 'Agent',
        'sort' => 'Sort',
        'dir' => 'Direction',
    ]"
    :chip-ignore-values="[
        'portfolio' => ['all'],
        'portal_access' => ['all'],
        'agent_user_id' => ['0', 0],
        'sort' => ['name'],
        'dir' => ['asc'],
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Name or phone…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="portfolio"
            label="Portfolio"
            empty-option="Portfolio: All"
            :options="[
                ['value' => 'assigned', 'label' => 'With properties'],
                ['value' => 'unassigned', 'label' => 'No properties yet'],
            ]"
            :value="($filters['portfolio'] ?? 'all') === 'all' ? '' : ($filters['portfolio'] ?? '')"
        />
        <x-property.filter-field type="select"
            name="portal_access"
            label="Portal access"
            empty-option="Portal: All"
            :options="[
                ['value' => 'yes', 'label' => 'Portal enabled'],
                ['value' => 'no', 'label' => 'Portal not enabled'],
            ]"
            :value="($filters['portal_access'] ?? 'all') === 'all' ? '' : ($filters['portal_access'] ?? '')"
        />
        @if (($agents ?? []) !== [])
            <x-property.filter-field type="select"
                name="agent_user_id"
                label="Agent workspace"
                :options="collect([['value' => '0', 'label' => 'Agent: All']])
                    ->merge(collect($agents)->map(fn ($a) => ['value' => (string) $a['id'], 'label' => $a['name']]))
                    ->all()"
                :value="(string) ($filters['agent_user_id'] ?? '0')"
            />
        @endif
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'name', 'label' => 'Sort: Name'],
                ['value' => 'properties', 'label' => 'Sort: Properties'],
                ['value' => 'units', 'label' => 'Sort: Units'],
                ['value' => 'tenants', 'label' => 'Sort: Tenants'],
                ['value' => 'rent', 'label' => 'Sort: Rent portfolio'],
            ]"
            :value="$filters['sort'] ?? 'name'"
        />
        <x-property.filter-field type="select"
            name="dir"
            label="Direction"
            :options="[
                ['value' => 'asc', 'label' => 'Ascending'],
                ['value' => 'desc', 'label' => 'Descending'],
            ]"
            :value="$filters['dir'] ?? 'asc'"
        />
    </x-slot>
</x-property.filter-toolbar>
