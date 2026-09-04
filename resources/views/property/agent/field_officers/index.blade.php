<x-property.workspace
    title="Field officers"
    subtitle="Portfolio managers assigned to properties — landlords, units, tenants, and rent under each officer."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    :column-config="$columnConfig"
    :responsive-cards="false"
    :legacy-toolbar="false"
    :show-search="false"
    table-min-width="960px"
    empty-title="No field officers yet"
    empty-hint="Run property:import-passion-field-officers after importing properties from the legacy register."
>
    <x-slot name="filters">
        <form method="get" action="{{ route('property.field_officers.index', absolute: false) }}" class="flex flex-wrap items-end gap-2">
            <div class="min-w-[14rem] flex-1">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input
                    type="search"
                    name="q"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Name or phone..."
                    class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                />
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.field_officers.index', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Reset</a>
        </form>
    </x-slot>
</x-property.workspace>
