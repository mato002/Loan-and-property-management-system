<x-property.workspace
    title="Rent roll"
    subtitle="Active leases by unit — scheduled rent vs paid vs balance."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No rent roll lines yet"
    empty-hint="Add properties, units, active leases, invoices, and payments to populate this grid."
>
    <x-slot name="actions">
        <span class="inline-flex items-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">Live data</span>
    </x-slot>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search unit, tenant…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
