@php
    $penaltiesUrl = route('property.revenue.penalties', absolute: false);
    $drawerLabel = 'Penalty filters';
@endphp

<x-property.filter-toolbar
    :action="$penaltiesUrl"
    :reset-url="$penaltiesUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'status' => 'Status',
        'scope' => 'Scope',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search rules…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="Status: All"
            :options="[
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'off', 'label' => 'Off'],
            ]"
            :value="$filters['status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="scope"
            label="Scope"
            empty-option="Scope: All"
            :options="collect($scopes ?? [])->map(fn ($scope) => ['value' => (string) $scope, 'label' => (string) $scope])->all()"
            :value="$filters['scope'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'name', 'label' => 'Sort: Name'],
                ['value' => 'scope', 'label' => 'Sort: Scope'],
                ['value' => 'trigger_event', 'label' => 'Sort: Trigger'],
                ['value' => 'effective_from', 'label' => 'Sort: Effective'],
                ['value' => 'id', 'label' => 'Sort: ID'],
            ]"
            :value="$filters['sort'] ?? 'name'"
        />
        <x-property.filter-field type="select"
            name="dir"
            label="Order"
            :options="[['value' => 'asc', 'label' => 'Asc'], ['value' => 'desc', 'label' => 'Desc']]"
            :value="$filters['dir'] ?? 'asc'"
        />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 30, 50, 100, 200])->map(fn ($n) => ['value' => (string) $n, 'label' => (string) $n])->all()"
            :value="(string) ($filters['per_page'] ?? 30)"
        />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.revenue.penalties', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.penalties', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.penalties', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
