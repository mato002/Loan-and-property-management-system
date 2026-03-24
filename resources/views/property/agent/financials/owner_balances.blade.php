<x-property.workspace
    title="Owner balances"
    subtitle="Trust positions, amounts held for landlords, and pending remittances — ledger-backed only."
    back-route="property.financials.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No owner balance lines"
    empty-hint="Every movement posts a journal entry; landlords see read-only mirrors in their portal."
>
    <x-slot name="actions">
        <form method="get" action="{{ route('property.financials.owner_balances') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-full sm:w-28" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
        <a
            href="{{ route('property.workspace.form.show', 'financials-remittance') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >Run remittance</a>
    </x-slot>
</x-property.workspace>
