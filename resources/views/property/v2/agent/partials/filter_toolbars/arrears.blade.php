@php
    $arrearsUrl = route('property.revenue.arrears', absolute: false);
    $drawerLabel = 'Arrears filters';
    $filterFormId = 'property-filter-form-'.substr(md5($arrearsUrl.$drawerLabel), 0, 8);
@endphp

<x-property.filter-toolbar
    :action="$arrearsUrl"
    :reset-url="$arrearsUrl"
    :drawer-label="$drawerLabel"
    :chip-labels="[
        'q' => 'Search',
        'aging' => 'Aging',
        'workflow' => 'Workflow',
        'range_months' => 'Range',
        'range_end' => 'Ending month',
        'from' => 'Due from',
        'to' => 'Due to',
    ]"
>
    <x-slot name="primary">
        <x-property.filter-field type="hidden" name="range_months" :value="(string) ($filters['range_months'] ?? 0)" />
        <x-property.filter-field type="hidden" name="range_end" :value="$filters['range_end'] ?? now()->format('Y-m')" />
        <x-property.filter-field type="hidden" name="from" :value="$filters['from'] ?? ''" />
        <x-property.filter-field type="hidden" name="to" :value="$filters['to'] ?? ''" />

        <x-property.filter-field type="search" name="q" placeholder="Search tenant, unit, invoice…" :value="$filters['q'] ?? ''" wide />
        <x-property.filter-field type="select"
            name="aging"
            label="Aging"
            empty-option="Aging: All unpaid"
            :options="[
                ['value' => 'overdue', 'label' => 'Overdue only'],
                ['value' => 'not_due', 'label' => 'Not yet due'],
            ]"
            :value="$filters['aging'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="workflow"
            label="Workflow"
            empty-option="Workflow: All"
            :options="[
                ['value' => 'reminder', 'label' => 'Reminder (upcoming + 0–13d)'],
                ['value' => 'follow-up', 'label' => 'Follow-up (14–29d overdue)'],
                ['value' => 'escalated', 'label' => 'Escalated (30+ days overdue)'],
            ]"
            :value="$filters['workflow'] ?? ''"
        />
        <x-property.filter-field type="select"
            name="sort"
            label="Sort"
            :options="[
                ['value' => 'oldest_due', 'label' => 'Oldest due'],
                ['value' => 'days_late', 'label' => 'Aging'],
                ['value' => 'balance', 'label' => 'Balance'],
                ['value' => 'last_contact', 'label' => 'Last contact'],
                ['value' => 'invoice_count', 'label' => '# invoices'],
                ['value' => 'tenant', 'label' => 'Tenant'],
            ]"
            :value="$filters['sort'] ?? 'oldest_due'"
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
            'csvUrl' => route('property.revenue.arrears', array_merge(request()->query(), ['export' => 'csv']), false),
            'xlsUrl' => route('property.revenue.arrears', array_merge(request()->query(), ['export' => 'xls']), false),
            'pdfUrl' => route('property.revenue.arrears', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>
</x-property.filter-toolbar>
