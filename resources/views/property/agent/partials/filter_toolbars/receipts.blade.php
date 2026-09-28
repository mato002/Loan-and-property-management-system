@php
    $receiptsUrl = route('property.revenue.receipts', absolute: false);
    $drawerLabel = 'Receipt filters';
    $isEzenRegister = (bool) ($ezenReceiptRegister ?? false);
@endphp

<x-property.filter-toolbar
    :action="$receiptsUrl"
    :reset-url="$receiptsUrl"
    :drawer-label="$drawerLabel"
    data-filter-cascade="property-unit-tenant"
    data-filter-cascade-catalog="{!! \Illuminate\Support\Js::from($filterCascadeCatalog ?? ['units' => [], 'tenants' => []]) !!}"
    data-filter-cascade-auto-apply="true"
    :chip-labels="array_merge([
        'q' => 'Search',
        'property_id' => 'Property',
        'unit_id' => 'Unit',
        'tenant_id' => 'Tenant',
        'from' => 'From',
        'to' => 'To',
    ], $isEzenRegister ? ['match' => 'Match'] : [])"
>
    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" :placeholder="$isEzenRegister ? 'Search receipt #, ref, tenant, phone…' : 'Search receipt, invoice, tenant…'" :value="$filters['q'] ?? ''" wide />
        @include('property.agent.partials.filter_toolbars.partials.property_unit_tenant_fields', [
            'filters' => $filters,
            'properties' => $properties ?? [],
            'units' => $units ?? [],
            'tenantsForFilter' => $tenantsForFilter ?? [],
        ])
        @if ($isEzenRegister)
            <x-property.filter-field type="select"
                name="match"
                label="Match"
                empty-option="Match: All"
                :options="[
                    ['value' => 'linked', 'label' => 'Linked to payment'],
                    ['value' => 'unmatched', 'label' => 'Unmatched payment'],
                    ['value' => 'tenant_unlinked', 'label' => 'In system, no payment'],
                    ['value' => 'tenant_missing', 'label' => 'Tenant not in system'],
                    ['value' => 'in_system', 'label' => 'Tenant in system'],
                ]"
                :value="$filters['match'] ?? ''"
            />
        @endif
        <x-property.filter-field type="date" name="from" label="From" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="date" name="to" label="To" :value="$filters['to'] ?? ''" />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="$isEzenRegister ? [
                ['value' => 'banking_date', 'label' => 'Sort: Banking date'],
                ['value' => 'amount', 'label' => 'Sort: Amount'],
                ['value' => 'ezen_receipt_no', 'label' => 'Sort: Receipt #'],
                ['value' => 'ref_no', 'label' => 'Sort: Ref. no'],
                ['value' => 'link_status', 'label' => 'Sort: Link status'],
                ['value' => 'id', 'label' => 'Sort: ID'],
            ] : [
                ['value' => 'updated_at', 'label' => 'Sort: Submitted'],
                ['value' => 'amount', 'label' => 'Sort: Amount'],
                ['value' => 'invoice_no', 'label' => 'Sort: Invoice'],
                ['value' => 'id', 'label' => 'Sort: ID'],
            ]"
            :value="$filters['sort'] ?? ($isEzenRegister ? 'banking_date' : 'updated_at')"
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
