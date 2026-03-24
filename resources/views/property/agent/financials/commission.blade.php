<x-property.workspace
    title="Commission tracking"
    subtitle="Accrued vs collected management fees, leasing bonuses, and overrides by deal."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No commission lines"
    empty-hint="Support tiered fees and new-lease spikes; export for finance reconciliation."
>
    <x-slot name="actions">
        <form method="get" action="{{ route('property.financials.commission') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-full sm:w-28" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
        <a
            href="{{ route('property.workspace.form.show', 'financials-invoice-commission') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Invoice owners</a>
    </x-slot>
</x-property.workspace>
