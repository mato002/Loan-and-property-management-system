<x-property.workspace
    title="Properties"
    subtitle="Your assets under management — occupancy, rent roll summary, and performance snapshots (read-mostly)."
    back-route="property.landlord.portfolio"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No properties linked"
    empty-hint="When onboarding completes, each property appears here with drill-down to units and statements."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.landlord.properties.export') }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Download summary (CSV)</a>
    </x-slot>
    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search property…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
