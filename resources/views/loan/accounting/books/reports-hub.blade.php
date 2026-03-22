<x-loan-layout>
    <x-loan.page title="Accruals & reports" subtitle="Financial statements from the general ledger.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books hub</a>
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-2 max-w-3xl">
            <a href="{{ route('loan.accounting.reports.trial_balance') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300 transition-colors">
                <h2 class="text-sm font-bold text-slate-900">Trial balance</h2>
                <p class="text-xs text-slate-500 mt-2">Debit and credit balances by account as of a date.</p>
            </a>
            <a href="{{ route('loan.accounting.reports.income_statement') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300 transition-colors">
                <h2 class="text-sm font-bold text-slate-900">Income statement</h2>
                <p class="text-xs text-slate-500 mt-2">Income and expense accounts for a period (P&amp;L).</p>
            </a>
            <a href="{{ route('loan.accounting.reports.balance_sheet') }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300 transition-colors">
                <h2 class="text-sm font-bold text-slate-900">Balance sheet</h2>
                <p class="text-xs text-slate-500 mt-2">Assets, liabilities, and equity as of a date (plus YTD result).</p>
            </a>
        </div>
    </x-loan.page>
</x-loan-layout>
