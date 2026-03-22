<x-loan-layout>
    <x-loan.page title="Chart of accounts & rules" subtitle="Policies that govern how books are kept in this system.">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.chart.index') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Open chart of accounts</a>
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Books hub</a>
        </x-slot>

        <div class="space-y-6 max-w-3xl">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900">Double entry</h2>
                <p class="text-sm text-slate-600 mt-2">Every journal entry must balance: total debits equal total credits. Use the chart of accounts to classify lines; cash and bank accounts should be flagged as cash accounts for cashflow and reconciliation.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900">Account types</h2>
                <ul class="text-sm text-slate-600 mt-2 list-disc pl-5 space-y-1">
                    <li><span class="font-medium text-slate-800">Assets & expenses</span> — normal debit balances.</li>
                    <li><span class="font-medium text-slate-800">Liabilities, equity, income</span> — normal credit balances.</li>
                </ul>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900">Reports</h2>
                <p class="text-sm text-slate-600 mt-2">Trial balance, income statement, and balance sheet are derived from posted journal lines. Operational modules (utilities, petty cash, etc.) are separate; avoid duplicating the same spend in the journal unless you intend to reconcile that way.</p>
                <a href="{{ route('loan.accounting.reports.hub') }}" class="inline-block mt-3 text-sm font-semibold text-blue-600 hover:text-blue-800">Open accruals &amp; reports →</a>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
