<x-property.workspace
    title="Income vs expenses"
    subtitle="Period P&amp;L style rollups for management reporting — separate from operational Revenue screens."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-footer-row="$tableFooterRow ?? null"
    :show-search="false"
    empty-title="No GL mapping yet"
    empty-hint="Rollups below use live invoices, maintenance quotes, and utility charges."
>
    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.financials_period', [
            'financialsRoute' => 'property.financials.income_expenses',
            'exportRoute' => 'property.financials.income_expenses',
            'drawerLabel' => 'Income & expense filters',
        ])
    </x-slot>

    <x-slot name="above">
        <details class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 shadow-sm group">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 [&::-webkit-details-marker]:hidden">
                <span class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-chart-column text-indigo-600 dark:text-indigo-400" aria-hidden="true"></i>
                    Income chart (optional)
                </span>
                <span class="text-xs font-normal text-slate-500 group-open:hidden">Show</span>
                <span class="hidden text-xs font-normal text-slate-500 group-open:inline">Hide</span>
            </summary>
            <div class="border-t border-slate-100 dark:border-slate-700 p-3">
                <x-property.chart-bar
                    title="Income stack ({{ $periodLabel ?? now()->format('M Y') }})"
                    value-format="kes"
                    :series="$waterfallBars ?? []"
                />
            </div>
        </details>
    </x-slot>
</x-property.workspace>
