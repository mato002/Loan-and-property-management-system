<x-property.workspace
    title="Arrears management"
    subtitle="Overdue invoices with open balance — aging from due date."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No arrears cases"
    empty-hint="When due date passes and balance remains, rows appear here automatically."
>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Filter by tenant or unit…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
    <x-slot name="footer">
        <p class="font-medium text-slate-700 dark:text-slate-300">Workflow ideas</p>
        <p class="mt-1">Map states: Current → Reminder → Call → Plan → Notice → Legal. Each transition should log user, channel, and template used.</p>
    </x-slot>
</x-property.workspace>
