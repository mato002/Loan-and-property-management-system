<x-property.workspace
    title="Income statement"
    subtitle="Income, expense, and NOI analysis with account/property breakdowns."
    back-route="property.accounting.index"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.reports.income_statement.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'property_id' => $filters['property_id'] ?? null]),
            'xlsUrl' => route('property.accounting.reports.income_statement.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'property_id' => $filters['property_id'] ?? null, 'format' => 'xls']),
            'pdfUrl' => route('property.accounting.reports.income_statement.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'property_id' => $filters['property_id'] ?? null, 'format' => 'pdf']),
        ])
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.reports.income_statement') }}" class="flex flex-wrap gap-2 w-full">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <select name="property_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                <option value="">Property: All</option>
                @foreach (($properties ?? []) as $p)
                    <option value="{{ $p->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>Txns: {{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
            <a href="{{ route('property.accounting.reports.income_statement') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
        </form>
    </x-slot>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reporting period</p>
        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ $periodLabel }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Income</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $income }}</p>
            <p class="mt-1 text-xs text-slate-500">Prev: {{ \App\Services\Property\PropertyMoney::kes((float) ($prevIncomeRaw ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200/70 dark:border-rose-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Expenses</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $expenses }}</p>
            <p class="mt-1 text-xs text-slate-500">Prev: {{ \App\Services\Property\PropertyMoney::kes((float) ($prevExpensesRaw ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-blue-200/70 dark:border-blue-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Net</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $net }}</p>
            <p class="mt-1 text-xs text-slate-500">Prev: {{ \App\Services\Property\PropertyMoney::kes((float) ($prevNetRaw ?? 0)) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200/70 dark:border-indigo-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">NOI</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ $noi }}</p>
            <p class="mt-1 text-xs text-slate-500">Income - operating expenses</p>
        </div>
    </div>

    @php
        $incomeDelta = (float) ($incomeRaw ?? 0) - (float) ($prevIncomeRaw ?? 0);
        $expenseDelta = (float) ($expensesRaw ?? 0) - (float) ($prevExpensesRaw ?? 0);
        $netDelta = (float) ($netRaw ?? 0) - (float) ($prevNetRaw ?? 0);
        $incomeDeltaPct = (float) ($prevIncomeRaw ?? 0) > 0 ? ($incomeDelta / (float) $prevIncomeRaw) * 100 : null;
        $expenseDeltaPct = (float) ($prevExpensesRaw ?? 0) > 0 ? ($expenseDelta / (float) $prevExpensesRaw) * 100 : null;
        $netDeltaPct = (float) ($prevNetRaw ?? 0) != 0.0 ? ($netDelta / (float) abs($prevNetRaw)) * 100 : null;
    @endphp
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Income variance</p>
            <p class="mt-1 text-sm font-semibold {{ $incomeDelta >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $incomeDelta >= 0 ? '+' : '' }}{{ \App\Services\Property\PropertyMoney::kes($incomeDelta) }}
                @if (!is_null($incomeDeltaPct)) ({{ number_format($incomeDeltaPct, 1) }}%) @endif
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Expense variance</p>
            <p class="mt-1 text-sm font-semibold {{ $expenseDelta <= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $expenseDelta >= 0 ? '+' : '' }}{{ \App\Services\Property\PropertyMoney::kes($expenseDelta) }}
                @if (!is_null($expenseDeltaPct)) ({{ number_format($expenseDeltaPct, 1) }}%) @endif
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Net variance</p>
            <p class="mt-1 text-sm font-semibold {{ $netDelta >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $netDelta >= 0 ? '+' : '' }}{{ \App\Services\Property\PropertyMoney::kes($netDelta) }}
                @if (!is_null($netDeltaPct)) ({{ number_format($netDeltaPct, 1) }}%) @endif
            </p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Income breakdown</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="py-2 pr-3">Account</th><th class="py-2 pr-3 text-right">Amount</th><th class="py-2 text-right">% of income</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse (($incomeBreakdown ?? []) as $row)
                            <tr>
                                <td class="py-2 pr-3">{{ $row['account'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ \App\Services\Property\PropertyMoney::kes((float) $row['total']) }}</td>
                                <td class="py-2 text-right">{{ number_format((float) $row['pct'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-slate-500">No income lines in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Expense breakdown</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="py-2 pr-3">Account</th><th class="py-2 pr-3 text-right">Amount</th><th class="py-2 text-right">% of expenses</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse (($expenseBreakdown ?? []) as $row)
                            <tr>
                                <td class="py-2 pr-3">{{ $row['account'] }}</td>
                                <td class="py-2 pr-3 text-right">{{ \App\Services\Property\PropertyMoney::kes((float) $row['total']) }}</td>
                                <td class="py-2 text-right">{{ number_format((float) $row['pct'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-slate-500">No expense lines in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">By property</p>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="py-2 pr-3">Property</th><th class="py-2 pr-3 text-right">Income</th><th class="py-2 pr-3 text-right">Expenses</th><th class="py-2 text-right">Net</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse (($propertyBreakdown ?? []) as $row)
                        <tr>
                            <td class="py-2 pr-3">{{ $row['property'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Services\Property\PropertyMoney::kes((float) $row['income']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Services\Property\PropertyMoney::kes((float) $row['expenses']) }}</td>
                            <td class="py-2 text-right font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $row['net']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">No property data available for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">6-month trend</p>
        @php
            $trendMax = max(1.0, collect($trend ?? [])->flatMap(fn($t) => [(float) $t['income'], (float) $t['expenses'], abs((float) $t['net'])])->max() ?? 1.0);
        @endphp
        <div class="mt-3 space-y-2">
            @foreach (($trend ?? []) as $t)
                @php
                    $incomeW = min(100, ((float) $t['income'] / $trendMax) * 100);
                    $expenseW = min(100, ((float) $t['expenses'] / $trendMax) * 100);
                    $netAbs = abs((float) $t['net']);
                    $netW = min(100, ($netAbs / $trendMax) * 100);
                @endphp
                <div class="rounded-xl border border-slate-100 dark:border-slate-700 p-2">
                    <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300">
                        <span class="font-medium">{{ $t['label'] }}</span>
                        <span>Net: {{ \App\Services\Property\PropertyMoney::kes((float) $t['net']) }}</span>
                    </div>
                    <div class="mt-2 space-y-1">
                        <div class="h-2 rounded bg-slate-100 dark:bg-slate-700"><div class="h-2 rounded bg-emerald-500" style="width: {{ $incomeW }}%"></div></div>
                        <div class="h-2 rounded bg-slate-100 dark:bg-slate-700"><div class="h-2 rounded bg-rose-500" style="width: {{ $expenseW }}%"></div></div>
                        <div class="h-2 rounded bg-slate-100 dark:bg-slate-700"><div class="h-2 rounded {{ ((float) $t['net']) >= 0 ? 'bg-blue-500' : 'bg-amber-500' }}" style="width: {{ $netW }}%"></div></div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="py-2 pr-3">Period</th><th class="py-2 pr-3 text-right">Income</th><th class="py-2 pr-3 text-right">Expenses</th><th class="py-2 text-right">Net</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach (($trend ?? []) as $t)
                        <tr>
                            <td class="py-2 pr-3">{{ $t['label'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Services\Property\PropertyMoney::kes((float) $t['income']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Services\Property\PropertyMoney::kes((float) $t['expenses']) }}</td>
                            <td class="py-2 text-right font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $t['net']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Detailed transactions</p>
            <div class="flex items-center gap-3 text-xs">
                <a href="{{ route('property.accounting.entries', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null]) }}" class="text-indigo-600 hover:underline">Open accounting entries</a>
                <a href="{{ route('property.accounting.audit_trail', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null]) }}" class="text-indigo-600 hover:underline">Open audit trail</a>
                <span class="text-slate-500">Drill-down lines for selected period</span>
            </div>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">Property</th>
                        <th class="py-2 pr-3">Account</th>
                        <th class="py-2 pr-3">Category</th>
                        <th class="py-2 pr-3">Type</th>
                        <th class="py-2 pr-3 text-right">Amount</th>
                        <th class="py-2">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse (($txnPaginator?->items() ?? []) as $e)
                        <tr>
                            <td class="py-2 pr-3">{{ $e->entry_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ $e->property?->name ?? 'General' }}</td>
                            <td class="py-2 pr-3">{{ $e->account_name }}</td>
                            <td class="py-2 pr-3 capitalize">{{ $e->category }}</td>
                            <td class="py-2 pr-3 capitalize">{{ $e->entry_type }}</td>
                            <td class="py-2 pr-3 text-right">{{ \App\Services\Property\PropertyMoney::kes((float) $e->amount) }}</td>
                            <td class="py-2">{{ $e->reference ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-500">No transactions found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (($txnPaginator?->hasPages() ?? false))
            <div class="mt-3">{{ $txnPaginator->links() }}</div>
        @endif
    </div>
</x-property.workspace>

