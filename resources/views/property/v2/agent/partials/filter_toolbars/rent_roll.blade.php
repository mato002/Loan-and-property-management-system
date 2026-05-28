@php
    $rentRollUrl = route('property.revenue.rent_roll', absolute: false);
    $drawerLabel = 'Rent roll filters';
    $filterFormId = 'property-filter-form-'.substr(md5($rentRollUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$rentRollUrl"
    :reset-url="$rentRollUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="['q' => 'Search']"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search unit, tenant…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'unit', 'label' => 'Unit'],
                ['value' => 'tenant', 'label' => 'Tenant'],
                ['value' => 'period', 'label' => 'Period'],
                ['value' => 'due', 'label' => 'Rent due'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'balance', 'label' => 'Balance'],
                ['value' => 'status', 'label' => 'Status'],
            ]"
            :value="$filters['sort'] ?? 'unit'"
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
            'csvUrl' => route('property.revenue.rent_roll', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.rent_roll', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.rent_roll', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
