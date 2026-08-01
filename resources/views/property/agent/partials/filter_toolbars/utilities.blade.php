@php
    $utilitiesUrl = route('property.revenue.utilities', absolute: false);
    $drawerLabel = 'Utility charge filters';
    $filterFormId = 'property-filter-form-'.substr(md5($utilitiesUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$utilitiesUrl"
    :reset-url="$utilitiesUrl"
    :drawer-label="$drawerLabel"
    data-filter-cascade="property-unit"
    data-filter-cascade-catalog='@json($filterCascadeCatalog ?? ['units' => [], 'tenants' => []])'
    data-filter-cascade-auto-apply="true"
    :chip-labels="[
        'q' => 'Search',
        'property_id' => 'Property',
        'unit_id' => 'Unit',
        'charge_type' => 'Type',
        'month' => 'Billing month',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search label or unit…" :value="$filters['q'] ?? ''" wide />
        @include('property.agent.partials.filter_toolbars.partials.property_unit_fields', [
            'filters' => $filters,
            'properties' => $properties ?? [],
            'units' => $units ?? [],
        ])
        <x-property.filter-field type="select"
            name="charge_type"
            label="Type"
            empty-option="Type: All"
            :options="[
                ['value' => 'water', 'label' => 'Water'],
                ['value' => 'electricity', 'label' => 'Electricity'],
                ['value' => 'service', 'label' => 'Service'],
                ['value' => 'garbage', 'label' => 'Garbage'],
                ['value' => 'other', 'label' => 'Other'],
            ]"
            :value="$filters['charge_type'] ?? ''"
        />
        <x-property.filter-field type="month" name="month" label="Billing month" :value="$filters['month'] ?? ''" />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'id', 'label' => 'ID'],
                ['value' => 'created_at', 'label' => 'Added date'],
                ['value' => 'amount', 'label' => 'Amount'],
                ['value' => 'label', 'label' => 'Label'],
                ['value' => 'billing_month', 'label' => 'Billing month'],
            ]"
            :value="$filters['sort'] ?? 'id'"
        />
        <x-property.filter-field type="select"
            name="dir"
            label="Order"
            :options="[['value' => 'desc', 'label' => 'Desc'], ['value' => 'asc', 'label' => 'Asc']]"
            :value="$filters['dir'] ?? 'desc'"
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
            'csvUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
