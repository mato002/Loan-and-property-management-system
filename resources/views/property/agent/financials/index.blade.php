<x-property-layout>
    <x-slot name="header">Financials</x-slot>

    <x-property.page
        title="Financials"
        subtitle="Clean reporting layer — separate from day-to-day Revenue operations (Yardi-style summaries)."
    >
        <x-property.module-status label="Financials" class="mb-4" />

        <div class="mb-4 grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Financial Reports</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('property.accounting.reports.income_statement', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Profit and Loss Summary</a>
                    <a href="{{ route('property.accounting.reports.income_statement', ['compare' => 1], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Profit and Loss Comparison</a>
                    <a href="{{ route('property.financials.income_expenses', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Profit and Loss by Department</a>
                    <a href="{{ route('property.financials.cash_flow', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Profit and Loss by Months</a>
                    <a href="{{ route('property.accounting.reports.trial_balance', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Trial Balance</a>
                    <a href="{{ route('property.accounting.reports.cash_book', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Cash Book</a>
                    @if (auth()->user()?->hasPmPermission('payments.settle'))
                        <a href="{{ route('property.equity.sync_status', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Equity Sync Status</a>
                    @endif
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Expense Reports</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('property.financials.income_expenses', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Income & Expenses Summary</a>
                    <a href="{{ route('property.maintenance.costs', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Maintenance Expense Report</a>
                    <a href="{{ route('property.revenue.utilities', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Utility Billing Expenses</a>
                    <a href="{{ route('property.vendors.work_records', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Vendor Expense Work Report</a>
                    <a href="{{ route('property.accounting.reports.cash_book', [], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Expense Cash Book View</a>
                </div>
            </div>
        </div>

        @php
            $items = [
            ['route' => 'property.financials.income_expenses', 'title' => 'Income vs expenses', 'description' => 'Period P&amp;L style views.'],
            ['route' => 'property.financials.cash_flow', 'title' => 'Cash flow', 'description' => 'Operating liquidity picture.'],
            ['route' => 'property.financials.owner_balances', 'title' => 'Owner balances', 'description' => 'Trust and remittance positions.'],
            ['route' => 'property.financials.commission', 'title' => 'Commission tracking', 'description' => 'Accrual vs paid.'],
            ];
            if (auth()->user()?->hasPmPermission('payments.settle')) {
                $items[] = ['route' => 'property.equity.sync_status', 'title' => 'Equity bank sync', 'description' => 'Live intake, matching, and reconciliation status.'];
            }
        @endphp
        <x-property.hub-grid :items="$items" />
    </x-property.page>
</x-property-layout>
