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
        <a href="{{ route('property.accounting.payroll') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back to payroll</a>
    </x-slot>

    <x-slot name="toolbar">
        <span class="text-xs text-slate-500 dark:text-slate-400">Open a payslip using the reference link in the table.</span>
    </x-slot>
</x-property.workspace>
