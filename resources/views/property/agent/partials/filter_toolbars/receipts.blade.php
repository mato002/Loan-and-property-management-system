@php
    $receiptsUrl = route('property.revenue.receipts', absolute: false);
    $drawerLabel = 'Receipt filters';
@endphp

<x-property.filter-toolbar
    :action="$receiptsUrl"
    :reset-url="$receiptsUrl"
    :drawer-label="$drawerLabel"
    data-filter-cascade="property-unit-tenant"
    data-filter-cascade-catalog='@json($filterCascadeCatalog ?? ['units' => [], 'tenants' => []])'
    data-filter-cascade-auto-apply="true"
    :chip-labels="[
        'q' => 'Search',
        'property_id' => 'Property',
        'unit_id' => 'Unit',
        'tenant_id' => 'Tenant',
        'from' => 'From',
        'to' => 'To',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search receipt, invoice, tenant…" :value="$filters['q'] ?? ''" wide />
        @include('property.agent.partials.filter_toolbars.partials.property_unit_tenant_fields', [
            'filters' => $filters,
            'properties' => $properties ?? [],
            'units' => $units ?? [],
            'tenantsForFilter' => $tenantsForFilter ?? [],
        ])
        <x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'updated_at', 'label' => 'Sort: Submitted'],
                ['value' => 'amount', 'label' => 'Sort: Amount'],
                ['value' => 'invoice_no', 'label' => 'Sort: Invoice'],
                ['value' => 'id', 'label' => 'Sort: ID'],
            ]"
            :value="$filters['sort'] ?? 'updated_at'"
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
            'csvUrl' => route('property.revenue.receipts', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.receipts', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.receipts', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
