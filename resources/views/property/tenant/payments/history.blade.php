<x-property.workspace
    title="Payment history"
    subtitle="Every attempt, success, and allocation — same data agents see, filtered to you."
    back-route="property.tenant.payments.index"
    :stats="[
        ['label' => 'Successful', 'value' => '0', 'hint' => 'All time'],
        ['label' => 'Last payment', 'value' => '—', 'hint' => 'Date'],
    ]"
    :columns="['Date', 'Channel', 'Amount', 'Reference', 'Invoice', 'Status', 'Receipt']"
    empty-title="No payments yet"
    empty-hint="STK and bank entries will list here with downloadable confirmations."
>
    <x-slot name="toolbar">
        <input type="month" data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
    </x-slot>
</x-property.workspace>
