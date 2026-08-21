@php
    $invoicesUrl = route('property.revenue.invoices', absolute: false);
    $drawerLabel = 'Invoice filters';
    $filterFormId = 'property-filter-form-'.substr(md5($invoicesUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$invoicesUrl"
    :reset-url="$invoicesUrl"
    :drawer-label="$drawerLabel"
    revenue-date-filter="invoices"
    data-filter-cascade="property-unit-tenant"
    data-filter-cascade-catalog="{!! \Illuminate\Support\Js::from($filterCascadeCatalog ?? ['units' => [], 'tenants' => []]) !!}"
    data-filter-cascade-auto-apply="true"
    :chip-labels="[
        'q' => 'Search',
        'property_id' => 'Property',
        'unit_id' => 'Unit',
        'tenant_id' => 'Tenant',
        'status' => 'Status',
        'type' => 'Type',
        'period' => 'Bill month',
        'due_from' => 'Due from',
        'due_to' => 'Due to',
        'range_months' => 'Range',
        'range_end' => 'Ending month',
        'from' => 'From',
        'to' => 'To',
    ]"
>
    <x-slot name="dateRange">
        @include('property.agent.partials.filter_toolbars.revenue_date_range', [
            'formId' => $filterFormId,
            'mode' => 'invoices',
            'filters' => $filters,
            'billingRangeLabel' => $billingRangeLabel ?? null,
        ])
    </x-slot>

    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search invoice, tenant, unit…" :value="$filters['q'] ?? ''" wide />
        @include('property.agent.partials.filter_toolbars.partials.property_unit_tenant_fields', [
            'filters' => $filters,
            'properties' => $properties ?? [],
            'units' => $units ?? [],
            'tenantsForFilter' => $tenantsForFilter ?? [],
        ])
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="Status: All"
            :options="[
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'sent', 'label' => 'Sent'],
                ['value' => 'partial', 'label' => 'Partial'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'overdue', 'label' => 'Overdue'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ]"
            :value="$filters['status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="type"
            label="Type"
            empty-option="Type: All"
            :options="collect(\App\Models\PmInvoice::createTypeOptions())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
            :value="$filters['type'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'issue_date', 'label' => 'Issued'],
                ['value' => 'due_date', 'label' => 'Due'],
                ['value' => 'amount', 'label' => 'Amount'],
                ['value' => 'balance', 'label' => 'Balance'],
                ['value' => 'status', 'label' => 'Status'],
                ['value' => 'invoice_no', 'label' => 'Invoice #'],
                ['value' => 'id', 'label' => 'ID'],
            ]"
            :value="$filters['sort'] ?? 'issue_date'"
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

    <x-slot name="secondary">
        <x-property.filter-field type="month" name="period" label="Bill month on invoice" :value="$filters['period'] ?? ''" />
        <x-property.filter-field type="date" name="due_from" label="Due from" :value="$filters['due_from'] ?? ''" />
        <x-property.filter-field type="date" name="due_to" label="Due to" :value="$filters['due_to'] ?? ''" />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.revenue.invoices', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.invoices', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.invoices', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
