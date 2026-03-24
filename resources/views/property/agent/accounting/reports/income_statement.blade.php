<x-property.workspace
    title="Income statement"
    subtitle="Summary from property accounting records."
    back-route="property.accounting.index"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.accounting.reports.income_statement.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null]) }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto">Export CSV</a>
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.reports.income_statement') }}" class="flex gap-2 w-full sm:w-auto">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
    </x-slot>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Income</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $income }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200/70 dark:border-rose-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Expenses</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $expenses }}</p>
        </div>
        <div class="rounded-2xl border border-blue-200/70 dark:border-blue-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Net</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $net }}</p>
        </div>
    </div>
</x-property.workspace>

