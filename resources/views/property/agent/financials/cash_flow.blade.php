@php
    $dual = $cashDual ?? [];
@endphp

<x-property.workspace
    title="Cash flow"
    subtitle="Completed tenant payments vs maintenance outflows — illustrative operating cash picture."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No cash movements"
    empty-hint="Completed payments appear as inflows; completed maintenance jobs with quotes count as outflows."
>
    <x-slot name="toolbar">
        <input type="month" data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
    </x-slot>
    <x-property.chart-line-dual
        title="Monthly cash in vs maintenance out"
        label-a="Collections"
        label-b="Maint. (completed)"
        :series="$dual"
    />
</x-property.workspace>
