<x-property.workspace
    title="Cash flow"
    subtitle="Operating inflows/outflows and timing of landlord settlements — cash vs accrual toggle later."
    back-route="property.financials.index"
    :stats="[
        ['label' => 'In (MTD)', 'value' => 'KES 0', 'hint' => 'Cash'],
        ['label' => 'Out (MTD)', 'value' => 'KES 0', 'hint' => 'Cash'],
        ['label' => 'Net', 'value' => 'KES 0', 'hint' => 'MTD'],
        ['label' => 'Runway signal', 'value' => '—', 'hint' => 'Trust balance'],
    ]"
    :columns="['Date', 'Type', 'Description', 'Property', 'In', 'Out', 'Balance']"
    empty-title="No cash movements"
    empty-hint="Include collections, payouts, refunds, and bank charges — tie each to a source document."
>
    <x-slot name="toolbar">
        <input type="month" data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
    </x-slot>
</x-property.workspace>
