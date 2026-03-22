<x-property.workspace
    title="Income statements"
    subtitle="Downloadable period views — gross rent, vacancies, fees, and net to landlord."
    back-route="property.landlord.reports.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No invoices on your properties"
    empty-hint="Invoices raised against units on your linked properties appear here."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.landlord.reports.income.export') }}"
            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 w-full sm:w-auto"
        >Invoices (CSV)</a>
        <a
            href="{{ route('property.landlord.earnings.history.export') }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Ledger (CSV)</a>
    </x-slot>
    <x-slot name="toolbar">
        <input type="month" data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
    </x-slot>
</x-property.workspace>
