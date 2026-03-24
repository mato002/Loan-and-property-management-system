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
        <form method="get" action="{{ route('property.financials.income_expenses') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-full sm:w-28" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
    </x-slot>
    <x-property.chart-bar
        title="Income stack ({{ $periodLabel ?? now()->format('M Y') }})"
        value-format="kes"
        :series="$waterfallBars ?? []"
    />
</x-property.workspace>
