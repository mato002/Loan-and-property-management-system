@php
    $depositsUrl = route('property.reports.tenant.deposits', absolute: false);
    $drawerLabel = 'Deposit filters';
@endphp

<x-property.filter-toolbar
    :action="$depositsUrl"
    :reset-url="$depositsUrl"
    :drawer-label="$drawerLabel"
    data-filter-cascade="property-unit-tenant"
    data-filter-cascade-tenant-field="pm_tenant_id"
    data-filter-cascade-catalog="{!! \Illuminate\Support\Js::from($filterCascadeCatalog ?? ['units' => [], 'tenants' => []]) !!}"
    data-filter-cascade-auto-apply="true"
    :chip-labels="[
        'q' => 'Search',
        'property_id' => 'Property',
        'unit_id' => 'Unit',
        'pm_tenant_id' => 'Tenant',
        'landlord_id' => 'Landlord',
        'status' => 'Lease status',
        'deposit' => 'Deposit',
        'from' => 'Lease from',
        'to' => 'Lease to',
        'sort' => 'Sort',
        'dir' => 'Order',
    ]"
    :chip-ignore-values="[
        'property_id' => ['0', 0, ''],
        'unit_id' => ['0', 0, ''],
        'pm_tenant_id' => ['0', 0, ''],
        'landlord_id' => ['0', 0, ''],
        'status' => [''],
        'deposit' => [''],
        'sort' => ['tenant'],
        'dir' => ['asc'],
        'per_page' => ['30', 30],
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search tenant, property, unit…" :value="$filters['q'] ?? ''" wide />
        @include('property.agent.partials.filter_toolbars.partials.property_unit_tenant_fields', [
            'filters' => $filters,
            'properties' => $properties ?? [],
            'units' => $units ?? [],
            'tenantsForFilter' => $tenantsForFilter ?? [],
            'tenantField' => 'pm_tenant_id',
        ])
        <x-property.filter-field type="select"
            name="landlord_id"
            label="Landlord"
            :options="collect([['value' => '0', 'label' => 'Landlord: All']])
                ->merge(collect($landlords ?? [])->map(fn ($landlord) => ['value' => (string) $landlord->id, 'label' => (string) $landlord->name]))
                ->all()"
            :value="(string) ($filters['landlord_id'] ?? '0')"
        />
        <x-property.filter-field type="select"
            name="deposit"
            label="Deposit"
            empty-option="Deposit: All"
            :options="[
                ['value' => 'held', 'label' => 'Held (balance > 0)'],
                ['value' => 'zero', 'label' => 'Zero / none'],
            ]"
            :value="$filters['deposit'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="status"
            label="Lease status"
            empty-option="Status: All"
            :options="[
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'expired', 'label' => 'Expired'],
                ['value' => 'terminated', 'label' => 'Terminated'],
                ['value' => 'draft', 'label' => 'Draft'],
            ]"
            :value="$filters['status'] ?? ''"
        />
    </x-slot>

    <x-slot name="secondary">
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'tenant', 'label' => 'Tenant'],
                ['value' => 'property', 'label' => 'Property'],
                ['value' => 'unit', 'label' => 'Unit'],
                ['value' => 'deposit', 'label' => 'Deposit paid'],
                ['value' => 'balance', 'label' => 'Balance'],
            ]"
            :value="$filters['sort'] ?? 'tenant'"
        />
        <x-property.filter-field type="select"
            name="dir"
            label="Order"
            :options="[
                ['value' => 'asc', 'label' => 'Ascending'],
                ['value' => 'desc', 'label' => 'Descending'],
            ]"
            :value="$filters['dir'] ?? 'asc'"
        />
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 30, 50, 100, 200])->map(fn ($n) => ['value' => (string) $n, 'label' => (string) $n])->all()"
            :value="(string) ($filters['per_page'] ?? 30)"
        />
    </x-slot>

    <x-slot name="dateRange">
        <x-property.filter-field type="date" name="from" label="Lease from" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" label="Lease to" :value="$filters['to'] ?? ''" />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.reports.tenant.deposits', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.reports.tenant.deposits', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.reports.tenant.deposits', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
