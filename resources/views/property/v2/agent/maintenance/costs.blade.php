<x-property.workspace
    title="Cost by category"
    subtitle="Roll-up of maintenance job quote amounts (all listed jobs), grouped by request category. YTD spend uses jobs marked done this calendar year."
    back-route="property.maintenance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No quoted jobs yet"
    empty-hint="Add quotes on maintenance jobs to see spend by category."
>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search category…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
    <x-slot name="actions">
        <a href="{{ route('property.exports.maintenance_costs') }}" data-turbo="false" class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto">Export jobs (CSV)</a>
    </x-slot>
</x-property.workspace>
