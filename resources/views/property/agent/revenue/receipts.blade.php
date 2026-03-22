<x-property.workspace
    title="Receipts (KRA eTIMS)"
    subtitle="Paid invoice stubs — eTIMS integration can extend this list later."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No paid-invoice receipts listed"
    empty-hint="Shows invoices marked paid; link eTIMS when your integration is ready."
>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search receipt or invoice…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
