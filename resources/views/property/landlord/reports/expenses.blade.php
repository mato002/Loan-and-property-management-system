<x-property.workspace
    title="Expense reports"
    subtitle="Maintenance, management fees, utilities, and capex — allocated per property."
    back-route="property.landlord.reports.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No expenses in period"
    empty-hint="Pull from approved vendor bills and management fee invoices."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.landlord.reports.expenses.export') }}"
            data-turbo="false"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Download (CSV)</a>
    </x-slot>
</x-property.workspace>
