<x-property.workspace
    title="Income statement"
    subtitle="Summary from property accounting records."
    back-route="property.accounting.index"
    :columns="[]"
    :table-rows="[]"
>
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

