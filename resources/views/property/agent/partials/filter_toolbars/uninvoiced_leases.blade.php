@php
    $uninvoicedUrl = route('property.revenue.uninvoiced_leases', absolute: false);
    $drawerLabel = 'Uninvoiced lease filters';
    $filterFormId = 'property-filter-form-'.substr(md5($uninvoicedUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$uninvoicedUrl"
    :reset-url="$uninvoicedUrl"
    :drawer-label="$drawerLabel"
    data-filter-cascade="property-unit-tenant"
    data-filter-cascade-catalog="{!! \Illuminate\Support\Js::from($filterCascadeCatalog ?? ['units' => [], 'tenants' => []]) !!}"
    data-filter-cascade-auto-apply="true"
    :chip-labels="[
        'month' => 'Billing month',
        'filter' => 'Show',
        'property_id' => 'Property',
        'unit_id' => 'Unit',
        'tenant_id' => 'Tenant',
        'q' => 'Search',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="month" name="month" label="Billing month" :value="$filters['month'] ?? ($month ?? now()->format('Y-m'))" />
        <x-property.filter-field type="select"
            name="filter"
            label="Show"
            :options="[
                ['value' => 'missing', 'label' => 'Not invoiced only'],
                ['value' => 'underbilled', 'label' => 'Rent increase due'],
                ['value' => 'all', 'label' => 'All active'],
                ['value' => 'blocked', 'label' => 'Blocked (no unit / zero rent)'],
                ['value' => 'invoiced', 'label' => 'Already invoiced'],
            ]"
            :value="$filters['filter'] ?? 'missing'"
        />
        @include('property.agent.partials.filter_toolbars.partials.property_unit_tenant_fields', [
            'filters' => $filters,
            'properties' => $properties ?? [],
            'units' => $units ?? [],
            'tenantsForFilter' => $tenantsForFilter ?? [],
        ])
        <x-property.filter-field type="search" name="q" placeholder="Search tenant, unit…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 30, 50, 100, 200])->map(fn ($n) => ['value' => (string) $n, 'label' => (string) $n])->all()"
            :value="(string) ($filters['per_page'] ?? 30)"
        />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.revenue.uninvoiced_leases', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.uninvoiced_leases', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.uninvoiced_leases', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
