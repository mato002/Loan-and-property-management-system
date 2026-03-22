<x-property.workspace
    title="Income vs expenses"
    subtitle="Period P&amp;L style rollups for management reporting — separate from operational Revenue screens."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No GL mapping yet"
    empty-hint="Map rent, fees, maintenance, and utilities to your chart of accounts; export summary as CSV."
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
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 h-56 min-h-[12rem] w-full min-w-0 flex items-center justify-center text-sm text-slate-500 px-4 text-center">
        Waterfall placeholder — income to NOI
    </div>
</x-property.workspace>
