<x-property.workspace
    title="Commission tracking"
    subtitle="Accrued vs collected management fees, leasing bonuses, and overrides by deal."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No commission lines"
    empty-hint="Support tiered fees and new-lease spikes; export for finance reconciliation."
>
    <x-slot name="actions">
        <form method="get" action="{{ route('property.financials.commission') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search owner/property..." class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-56" />
            <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-full sm:w-28" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
            <a href="{{ route('property.financials.commission', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
        </form>
        <a
            href="{{ route('property.workspace.form.show', 'financials-invoice-commission') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Invoice owners</a>
    </x-slot>
</x-property.workspace>
