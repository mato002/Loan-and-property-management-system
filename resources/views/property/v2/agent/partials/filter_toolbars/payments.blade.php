@php
    $paymentsUrl = route('property.revenue.payments', absolute: false);
    $drawerLabel = 'Payment filters';
    $filterFormId = 'property-filter-form-'.substr(md5($paymentsUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$paymentsUrl"
    :reset-url="$paymentsUrl"
    :drawer-label="$drawerLabel"
    revenue-date-filter="payments"
    :chip-labels="[
        'q' => 'Search',
        'status' => 'Status',
        'reversal_status' => 'Reversal',
        'channel' => 'Channel',
        'range_months' => 'Range',
        'range_end' => 'Ending month',
        'from' => 'From',
        'to' => 'To',
    ]"
>
    <x-slot name="dateRange">
        @include('property.agent.partials.filter_toolbars.revenue_date_range', [
            'formId' => $filterFormId,
            'mode' => 'payments',
            'filters' => $filters,
            'receivedRangeLabel' => $receivedRangeLabel ?? null,
        ])
    </x-slot>

    <x-slot name="primary">
        <x-property.filter-field type="search" name="q" placeholder="Search ref, tenant, phone…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="status"
            label="Status"
            empty-option="Status: All"
            :options="[
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'failed', 'label' => 'Failed'],
            ]"
            :value="$filters['status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="reversal_status"
            label="Reversal"
            empty-option="Reversal: All"
            :options="[
                ['value' => 'pending', 'label' => 'Pending approval'],
                ['value' => 'reversed', 'label' => 'Reversed'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ]"
            :value="$filters['reversal_status'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="channel"
            label="Channel"
            empty-option="Channel: All"
            :options="collect(['mpesa' => 'M-Pesa', 'equity_paybill' => 'Equity Paybill API', 'mpesa_sms_ingest' => 'SMS Forwarder', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque', 'mpesa_stk' => 'M-Pesa STK'])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
            :value="$filters['channel'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'paid_at', 'label' => 'Received at'],
                ['value' => 'created_at', 'label' => 'Created at'],
                ['value' => 'amount', 'label' => 'Amount'],
                ['value' => 'status', 'label' => 'Status'],
                ['value' => 'id', 'label' => 'ID'],
            ]"
            :value="$filters['sort'] ?? 'paid_at'"
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
            :value="(string) ($perPage ?? request('per_page', 30))"
        />
    </x-slot>

    <x-slot name="export">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.revenue.payments', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.payments', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.payments', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
