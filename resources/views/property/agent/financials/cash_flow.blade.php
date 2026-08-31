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
    :table-footer-row="$tableFooterRow ?? null"
    :show-search="false"
    empty-title="No cash movements"
    empty-hint="Completed payments appear as inflows; completed maintenance jobs with quotes count as outflows."
>
    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.financials_period', [
            'financialsRoute' => 'property.financials.cash_flow',
            'exportRoute' => 'property.financials.cash_flow',
            'drawerLabel' => 'Cash flow filters',
        ])
    </x-slot>

    <x-slot name="above">
        <details class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 shadow-sm group">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-indigo-600 dark:text-indigo-400" aria-hidden="true"></i>
                    Monthly trend (optional)
                </span>
                <span class="text-xs font-normal text-slate-500 group-open:hidden">Show</span>
                <span class="hidden text-xs font-normal text-slate-500 group-open:inline">Hide</span>
            </summary>
            <div class="border-t border-slate-100 dark:border-slate-700 p-3">
                <x-property.chart-line-dual
                    title="Monthly cash in vs maintenance out ({{ $periodLabel ?? now()->format('M Y') }})"
                    label-a="Collections"
                    label-b="Maint. (completed)"
                    :series="$dual"
                />
            </div>
        </details>
    </x-slot>
</x-property.workspace>
