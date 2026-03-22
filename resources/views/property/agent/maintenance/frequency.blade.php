<x-property.workspace
    title="Issue frequency"
    subtitle="Maintenance requests grouped by calendar month for the last 12 months (ticket volume and category spread)."
    back-route="property.maintenance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No requests in range"
    empty-hint="Log maintenance requests to see a month-by-month trend here."
>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search months…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>

    <x-slot name="footer">
        <p>Use this view in monthly ops review: compare emergency spikes to planned spend on the Costs screen.</p>
    </x-slot>
</x-property.workspace>
