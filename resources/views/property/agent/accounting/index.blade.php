<x-property.workspace
    title="Accounting dashboard"
    subtitle="Live trust accounting overview with actionable alerts and drill-downs."
    back-route="property.dashboard"
    :stats="$stats"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.accounting.entries') }}" class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-4 py-2.5 min-h-[44px] text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto">+ New Journal Entry</a>
        <a href="{{ route('property.revenue.payments') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 min-h-[44px] text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto">+ Record Payment</a>
        <a href="{{ route('property.accounting.payables.landlord_payouts') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 min-h-[44px] text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto">+ Create Landlord Payout</a>
        <a href="{{ route('property.accounting.payroll') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 min-h-[44px] text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto">+ Run Payroll</a>
    </x-slot>

    <x-property.responsive.compact-card-grid class="gap-3 sm:gap-4">
        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Revenue vs Expenses (last 6 months)</h3>
            <div class="mt-3 space-y-2">
                @foreach (($monthlyTrend ?? []) as $m)
                    @php
                        $max = max(1, collect($monthlyTrend)->flatMap(fn($x) => [(float) $x['income'], (float) $x['expense']])->max());
                        $incomePct = round(((float) $m['income'] / $max) * 100, 1);
                        $expensePct = round(((float) $m['expense'] / $max) * 100, 1);
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span>{{ $m['label'] }}</span>
                            <span>Rev {{ \App\Services\Property\PropertyMoney::kes((float) $m['income']) }} · Exp {{ \App\Services\Property\PropertyMoney::kes((float) $m['expense']) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded bg-slate-100 overflow-hidden">
                            <div class="h-2 bg-emerald-500" style="width: {{ $incomePct }}%"></div>
                        </div>
                        <div class="mt-1 h-2 rounded bg-slate-100 overflow-hidden">
                            <div class="h-2 bg-rose-500" style="width: {{ $expensePct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Rent billed vs collected (this month)</h3>
            @php
                $billed = (float) ($rentSnapshot['billed'] ?? 0);
                $collected = (float) ($rentSnapshot['collected'] ?? 0);
                $pct = $billed > 0 ? min(100, round(($collected / $billed) * 100, 1)) : 0;
            @endphp
            <div class="mt-4 rounded-lg border border-slate-200 p-4">
                <div class="flex items-center justify-between text-sm">
                    <span>Billed: {{ \App\Services\Property\PropertyMoney::kes($billed) }}</span>
                    <span>Collected: {{ \App\Services\Property\PropertyMoney::kes($collected) }}</span>
                </div>
                <div class="mt-2 h-3 rounded bg-slate-100 overflow-hidden">
                    <div class="h-3 bg-blue-600" style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Collection rate: {{ number_format($pct, 1) }}%</p>
            </div>
            <div class="mt-4 grid gap-2">
                <a href="{{ route('property.revenue.arrears') }}" class="text-sm text-amber-700 hover:text-amber-800">Arrears trend →</a>
                <a href="{{ route('property.maintenance.costs') }}" class="text-sm text-slate-700 hover:text-slate-900">Maintenance cost trend →</a>
            </div>
        </div>
        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-amber-200 bg-amber-50 shadow-sm">
            <h3 class="text-sm font-semibold text-amber-900">Alerts</h3>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('property.revenue.arrears') }}" class="text-amber-900 hover:underline">⚠ Overdue tenant balances: {{ (int) ($alerts['overdue_tenants'] ?? 0) }}</a></li>
                <li><a href="{{ route('property.accounting.cash_bank.reconciliation') }}" class="text-amber-900 hover:underline">⚠ Unreconciled bank transactions: {{ (int) ($alerts['unreconciled_bank'] ?? 0) }}</a></li>
                <li><a href="{{ route('property.accounting.payables.landlord_payouts') }}" class="text-amber-900 hover:underline">⚠ Pending landlord payouts: {{ (int) ($alerts['pending_payouts'] ?? 0) }}</a></li>
                <li><a href="{{ route('property.communications.messages') }}" class="text-amber-900 hover:underline">⚠ Failed message deliveries: {{ (int) ($alerts['failed_messages'] ?? 0) }}</a></li>
                <li class="{{ ((int) ($alerts['negative_cash'] ?? 0)) > 0 ? 'text-rose-700 font-semibold' : 'text-emerald-700' }}">⚠ Negative cash warning: {{ ((int) ($alerts['negative_cash'] ?? 0)) > 0 ? 'Yes' : 'No' }}</li>
            </ul>
        </div>
        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Quick drill-down</h3>
            <x-property.responsive.quick-action-grid class="mt-3">
                <a href="{{ route('property.accounting.gl.chart_accounts') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">Chart of accounts</a>
                <a href="{{ route('property.accounting.gl.journal_batches') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">Journal batches</a>
                <a href="{{ route('property.accounting.reports.balance_sheet') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">Balance sheet</a>
                <a href="{{ route('property.accounting.controls.reversals') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">Reversals</a>
            </x-property.responsive.quick-action-grid>
        </div>
    </x-property.responsive.compact-card-grid>
</x-property.workspace>

