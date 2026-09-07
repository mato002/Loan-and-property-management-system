@php
    $filters = $filters ?? [];
    $preset = $reportFilterPreset ?? 'tenant';
    $showProperty = $showProperty ?? in_array($preset, ['tenant', 'landlord', 'expense', 'maintenance', 'financial'], true);
    $showUnit = $showUnit ?? in_array($preset, ['tenant', 'landlord', 'expense', 'maintenance'], true);
    $showTenant = $showTenant ?? in_array($preset, ['tenant', 'landlord'], true);
    $showLandlord = $showLandlord ?? in_array($preset, ['tenant', 'landlord', 'expense', 'financial'], true);
    $tenantField = $tenantField ?? 'tenant_id';
    $searchPlaceholder = $searchPlaceholder ?? 'Search this report…';
    $fromLabel = $fromLabel ?? 'From';
    $toLabel = $toLabel ?? 'To';
    $reportUrl = $reportFilterAction ?? url()->current();
    $drawerLabel = $reportFilterDrawerLabel ?? 'Report filters';
    $filterFormId = 'property-filter-form-'.substr(md5($reportUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$reportUrl"
    :reset-url="$reportUrl"
    :drawer-label="$drawerLabel"
    data-filter-cascade="property-unit-tenant"
    data-filter-cascade-tenant-field="{{ $tenantField }}"
    data-filter-cascade-catalog="{!! \Illuminate\Support\Js::from($filterCascadeCatalog ?? ['units' => [], 'tenants' => []]) !!}"
    data-filter-cascade-auto-apply="true"
    :chip-labels="[
        'q' => 'Search',
        'property_id' => 'Property',
        'unit_id' => 'Unit',
        'tenant_id' => 'Tenant',
        'pm_tenant_id' => 'Tenant',
        'landlord_id' => 'Landlord',
        'status' => 'Lease status',
        'deposit' => 'Deposit',
        'from' => $fromLabel,
        'to' => $toLabel,
        'sort' => 'Sort',
        'dir' => 'Order',
    ]"
    :chip-ignore-values="[
        'property_id' => ['0', 0, ''],
        'unit_id' => ['0', 0, ''],
        'tenant_id' => ['0', 0, ''],
        'pm_tenant_id' => ['0', 0, ''],
        'landlord_id' => ['0', 0, ''],
        'status' => [''],
        'deposit' => [''],
        'sort' => ['tenant', ''],
        'dir' => ['asc', ''],
        'per_page' => ['30', 30],
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" :placeholder="$searchPlaceholder" :value="$filters['q'] ?? ''" wide />
        @if ($showProperty && $showUnit && $showTenant)
            @include('property.agent.partials.filter_toolbars.partials.property_unit_tenant_fields', [
                'filters' => array_merge($filters, [$tenantField => $filters[$tenantField] ?? $filters['tenant_id'] ?? $filters['pm_tenant_id'] ?? '0']),
                'properties' => $properties ?? [],
                'units' => $units ?? [],
                'tenantsForFilter' => $tenantsForFilter ?? [],
                'tenantField' => $tenantField,
            ])
        @elseif ($showProperty && $showUnit)
            @include('property.agent.partials.filter_toolbars.partials.property_unit_fields', [
                'filters' => $filters,
                'properties' => $properties ?? [],
                'units' => $units ?? [],
            ])
        @elseif ($showProperty)
            <x-property.filter-field type="select"
                name="property_id"
                label="Property"
                :options="collect([['value' => '0', 'label' => 'Property: All']])
                    ->merge(collect($properties ?? [])->map(fn ($property) => ['value' => (string) $property->id, 'label' => (string) $property->name]))
                    ->all()"
                :value="(string) ($filters['property_id'] ?? '0')"
            />
        @endif
        @if ($showLandlord)
            <x-property.filter-field type="select"
                name="landlord_id"
                label="Landlord"
                :options="collect([['value' => '0', 'label' => 'Landlord: All']])
                    ->merge(collect($landlords ?? [])->map(fn ($landlord) => ['value' => (string) $landlord->id, 'label' => (string) $landlord->name]))
                    ->all()"
                :value="(string) ($filters['landlord_id'] ?? $selectedLandlordId ?? '0')"
            />
        @endif
        @isset($reportFilterExtrasView)
            @include($reportFilterExtrasView)
        @endisset
    </x-slot>

    <x-slot name="secondary">
        <x-property.filter-field type="select"
            name="per_page"
            label="Per page"
            :options="collect([10, 30, 50, 100, 200])->map(fn ($n) => ['value' => (string) $n, 'label' => (string) $n])->all()"
            :value="(string) ($filters['per_page'] ?? $perPage ?? 30)"
        />
    </x-slot>

    <x-slot name="dateRange">
        <x-property.filter-field type="date" name="from" :label="$fromLabel" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" :label="$toLabel" :value="$filters['to'] ?? ''" />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => url()->current().'?'.http_build_query(array_merge(request()->query(), ['export' => 'csv'])),
            'xlsUrl' => url()->current().'?'.http_build_query(array_merge(request()->query(), ['export' => 'xls'])),
            'pdfUrl' => url()->current().'?'.http_build_query(array_merge(request()->query(), ['export' => 'pdf'])),
        ])
    </x-slot>
</x-property.filter-toolbar>
