<x-property.workspace
    title="Payroll payslip ledger"
    subtitle="Payroll-linked accounting entries that support payslip and reconciliation checks."
    back-route="property.accounting.payroll"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No payroll ledger entries"
    empty-hint="Post a payroll batch to generate rows."
>
    <x-slot name="actions">
        <a href="{{ route('property.accounting.payroll.payslips.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'q' => $filters['q'] ?? null]) }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Export CSV</a>
        <a href="{{ route('property.accounting.payroll') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back to payroll</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payroll.payslips') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search reference/account…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-72" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
    </x-slot>
</x-property.workspace>
