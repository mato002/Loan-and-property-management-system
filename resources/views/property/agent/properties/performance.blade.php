<x-property.workspace
    title="Unit performance"
    subtitle="Asking rent on the unit vs contracted rent on the active lease (variance). Vacancy column shows current stretch for vacant units only — not full YTD history."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No units"
    empty-hint="Add units under Properties → Units."
>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search unit or property…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
    <x-slot name="actions">
        <a href="{{ route('property.exports.performance_snapshot') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto">Export (CSV)</a>
    </x-slot>
</x-property.workspace>
