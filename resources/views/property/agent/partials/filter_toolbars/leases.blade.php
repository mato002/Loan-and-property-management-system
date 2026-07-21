@php
    $activeTab = $activeTab ?? 'leases';
    $filters = $filters ?? [];
    $filterOptions = $filterOptions ?? ['tenants' => [], 'properties' => []];
    $leasesUrl = route('property.tenants.leases', $activeTab === 'expiry' ? ['tab' => 'expiry'] : [], false);
    $drawerLabel = $activeTab === 'expiry' ? 'Expiry filters' : 'Lease filters';
    $filterFormId = 'property-filter-form-'.substr(md5($leasesUrl.$drawerLabel), 0, 8);

    $chipLabels = [
        'q' => 'Search',
        'status' => 'Status',
        'window' => 'Window',
        'pm_tenant_id' => 'Tenant',
        'property_id' => 'Property',
        'term' => 'Term type',
        'expiring' => 'Expiring',
        'carry_forward' => 'Carry-forward',
        'from' => 'From',
        'to' => 'To',
        'sort' => 'Sort',
        'dir' => 'Order',
    ];
@endphp

<x-property.filter-toolbar
    :action="$leasesUrl"
    :reset-url="$leasesUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="$chipLabels"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search tenant, unit, lease #…" :value="$filters['q'] ?? ''" wide />
        @if ($activeTab === 'expiry')
            <x-property.filter-field type="select"
                name="window"
                label="Window"
                empty-option="Window: All (90d)"
                :options="[
                    ['value' => 'within30', 'label' => '≤ 30 days'],
                    ['value' => 'within60', 'label' => '≤ 60 days'],
                    ['value' => 'within90', 'label' => '≤ 90 days'],
                ]"
                :value="$filters['window'] ?? ''"
            />
        @else
            <x-property.filter-field type="select"
                name="status"
                label="Status"
                empty-option="Status: All"
                :options="[
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'expired', 'label' => 'Expired'],
                    ['value' => 'terminated', 'label' => 'Terminated'],
                ]"
                :value="$filters['status'] ?? ''"
            />
            <x-property.filter-field type="select"
                name="expiring"
                label="Expiring"
                empty-option="Expiring: Any"
                :options="[
                    ['value' => 'within30', 'label' => 'Ending ≤ 30 days'],
                    ['value' => 'within60', 'label' => 'Ending ≤ 60 days'],
                    ['value' => 'within90', 'label' => 'Ending ≤ 90 days'],
                ]"
                :value="$filters['expiring'] ?? ''"
            />
        @endif
        <x-property.filter-field type="select"
            name="pm_tenant_id"
            label="Tenant"
            empty-option="Tenant: All"
            :options="$filterOptions['tenants'] ?? []"
            :value="(string) ($filters['pm_tenant_id'] ?? '')"
        />
        <x-property.filter-field type="select"
            name="property_id"
            label="Property"
            empty-option="Property: All"
            :options="$filterOptions['properties'] ?? []"
            :value="(string) ($filters['property_id'] ?? '')"
        />
    </x-slot>

    <x-slot name="secondary">
        @if ($activeTab === 'leases')
            <x-property.filter-field type="select"
                name="term"
                label="Term"
                empty-option="Term: All"
                :options="[
                    ['value' => 'open_ended', 'label' => 'Open-ended'],
                    ['value' => 'fixed', 'label' => 'Fixed end date'],
                ]"
                :value="$filters['term'] ?? ''"
            />
            <x-property.filter-field type="select"
                name="carry_forward"
                label="Carry-forward"
                empty-option="Carry-forward: All"
                :options="[
                    ['value' => 'yes', 'label' => 'Has carry-forward'],
                    ['value' => 'no', 'label' => 'No carry-forward'],
                ]"
                :value="$filters['carry_forward'] ?? ''"
            />
        @endif
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="$activeTab === 'expiry'
                ? [
                    ['value' => 'end_date', 'label' => 'End date'],
                    ['value' => 'rent', 'label' => 'Rent'],
                    ['value' => 'tenant', 'label' => 'Tenant'],
                    ['value' => 'lease', 'label' => 'Lease #'],
                ]
                : [
                    ['value' => 'start_date', 'label' => 'Start date'],
                    ['value' => 'end_date', 'label' => 'End date'],
                    ['value' => 'rent', 'label' => 'Rent'],
                    ['value' => 'tenant', 'label' => 'Tenant'],
                    ['value' => 'lease', 'label' => 'Lease #'],
                ]"
            :value="$filters['sort'] ?? ($activeTab === 'expiry' ? 'end_date' : 'start_date')"
        />
        <x-property.filter-field type="select"
            name="dir"
            label="Order"
            :options="[
                ['value' => 'desc', 'label' => 'Descending'],
                ['value' => 'asc', 'label' => 'Ascending'],
            ]"
            :value="$filters['dir'] ?? ($activeTab === 'expiry' ? 'asc' : 'desc')"
        />
    </x-slot>

    <x-slot name="dateRange">
        <x-property.filter-field type="date" name="from" :label="$activeTab === 'expiry' ? 'End from' : 'From'" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" :label="$activeTab === 'expiry' ? 'End to' : 'To'" :value="$filters['to'] ?? ''" />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.tenants.leases', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.tenants.leases', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.tenants.leases', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
