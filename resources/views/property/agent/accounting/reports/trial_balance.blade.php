<x-property.workspace
    title="Trial balance"
    subtitle="Debit and credit totals by account from property accounting entries."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No balances yet"
    empty-hint="Add entries first to generate a trial balance."
>
    <x-slot name="actions">
        <a href="{{ route('property.accounting.reports.trial_balance.export', ['q' => $filters['q'] ?? null]) }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto">Export CSV</a>
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.reports.trial_balance') }}" class="flex gap-2 w-full sm:w-auto">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search account…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-72" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Search</button>
        </form>
    </x-slot>
</x-property.workspace>

