<x-property.workspace
    title="Income vs expenses"
    subtitle="Period P&amp;L style rollups for management reporting — separate from operational Revenue screens."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No GL mapping yet"
    empty-hint="Rollups below use live invoices, maintenance quotes, and utility charges."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.exports.income_expenses_summary') }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Export summary (CSV)</a>
        <select class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="2026">FY 2026</option>
            <option value="2025">FY 2025</option>
        </select>
    </x-slot>
    <x-property.chart-bar
        title="Income stack (magnitudes)"
        value-format="kes"
        :series="$waterfallBars ?? []"
    />
</x-property.workspace>
