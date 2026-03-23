<x-property.workspace
    title="Property accounting"
    subtitle="Record accounting entries for property operations and review core accounting reports."
    back-route="property.dashboard"
    :stats="$stats"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.accounting.entries') }}" class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Journal entries</a>
        <a href="{{ route('property.accounting.payroll') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Employee payroll</a>
        <a href="{{ route('property.accounting.audit_trail') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Audit trail</a>
        <a href="{{ route('property.accounting.reports.trial_balance') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Trial balance</a>
    </x-slot>

    <div class="grid gap-4 md:grid-cols-3">
        <a href="{{ route('property.accounting.entries') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm hover:border-blue-300 transition-colors">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Journal entries</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Capture debit/credit records with references and descriptions.</p>
        </a>
        <a href="{{ route('property.accounting.reports.income_statement') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm hover:border-blue-300 transition-colors">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Income statement</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Track income, expenses, and net position from your records.</p>
        </a>
        <a href="{{ route('property.accounting.reports.cash_book') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm hover:border-blue-300 transition-colors">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Cash book</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">View running balance for cash/bank tagged entries.</p>
        </a>
        <a href="{{ route('property.accounting.audit_trail') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm hover:border-blue-300 transition-colors">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Audit trail</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Track posting source, reversals, and references in one timeline.</p>
        </a>
        <a href="{{ route('property.accounting.payroll') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm hover:border-blue-300 transition-colors">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Employee payroll</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Post payroll batches, review payroll-linked entries, and manage payroll settings.</p>
        </a>
    </div>
</x-property.workspace>

