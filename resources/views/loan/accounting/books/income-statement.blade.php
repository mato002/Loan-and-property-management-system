<x-loan-layout>
    <x-loan.page title="Income Statement (Cash Basis)" subtitle="Real-time visibility into liquidity, performance, and compliance.">
        @php
            $currency = fn (float|int $amount): string => 'KSh '.number_format((float) $amount, 2);
            $pct = fn (float|int $value): string => number_format((float) $value, 1).'%';
            $coaLinkedAccounts = count($incomeRows) + count($expenseRows);
            $netMargin = $incomeTotal > 0 ? ($netIncome / $incomeTotal) * 100 : 0;
            $mode = request('mode', 'summary');
            $granularity = request('view', 'monthly');
            $compareWith = request('compare_with', now()->subMonth()->toDateString());
        @endphp

        <div class="bg-gray-50 space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Realized Income</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $currency($incomeTotal) }}</p>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Operating Burn</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $currency($expenseTotal) }}</p>
                </article>
                <article class="rounded-xl border border-green-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Net Surplus (Cash Basis)</p>
                    <p class="mt-2 text-2xl font-semibold {{ $netIncome >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $currency($netIncome) }}</p>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Net Margin %</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $pct($netMargin) }}</p>
                    <p class="mt-1 text-xs font-semibold text-purple-600">{{ number_format($coaLinkedAccounts) }} COA linked accounts</p>
                </article>
            </div>

            <form method="get" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                <input type="hidden" name="to" value="{{ $to->toDateString() }}">

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                        <button
                            type="submit"
                            name="mode"
                            value="summary"
                            class="rounded-md px-4 py-2 font-semibold transition {{ $mode === 'summary' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-white' }}"
                        >
                            Summary View
                        </button>
                        <button
                            type="submit"
                            name="mode"
                            value="trend"
                            class="rounded-md px-4 py-2 font-semibold transition {{ $mode === 'trend' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-white' }}"
                        >
                            Trend View
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="viewSelect" class="text-sm font-semibold text-gray-600">View:</label>
                        <select
                            id="viewSelect"
                            name="view"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            onchange="this.form.requestSubmit()"
                        >
                            <option value="daily" @selected($granularity === 'daily')>Daily</option>
                            <option value="weekly" @selected($granularity === 'weekly')>Weekly</option>
                            <option value="monthly" @selected($granularity === 'monthly')>Monthly</option>
                            <option value="quarterly" @selected($granularity === 'quarterly')>Quarterly</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="compareWithDate" class="text-sm font-semibold text-gray-600">Compare With:</label>
                        <input
                            id="compareWithDate"
                            type="date"
                            name="compare_with"
                            value="{{ $compareWith }}"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            onchange="this.form.requestSubmit()"
                        >
                    </div>
                </div>
            </form>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-3">Account / Category</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">% of Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-gray-50">
                                <td colspan="3" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Revenue (Realized Income)</td>
                            </tr>
                            @forelse ($incomeRows as $row)
                                @php
                                    $share = $incomeTotal > 0 ? ((float) $row['amount'] / $incomeTotal) * 100 : 0;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900">{{ $row['account']->code }} - {{ $row['account']->name }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ $currency($row['amount']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $pct($share) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm text-gray-500">No income journals found for selected period.</td>
                                </tr>
                            @endforelse
                            <tr class="bg-gray-50 text-gray-700">
                                <td class="px-4 py-2 font-semibold">Revenue Subtotal</td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $currency($incomeTotal) }}</td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $pct(100) }}</td>
                            </tr>

                            <tr class="bg-gray-50">
                                <td colspan="3" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Operating Expenses</td>
                            </tr>
                            @forelse ($expenseRows as $row)
                                @php
                                    $share = $incomeTotal > 0 ? ((float) $row['amount'] / $incomeTotal) * 100 : 0;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900">{{ $row['account']->code }} - {{ $row['account']->name }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ $currency($row['amount']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $pct($share) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm text-gray-500">No expense journals found for selected period.</td>
                                </tr>
                            @endforelse
                            <tr class="bg-gray-50 text-gray-700">
                                <td class="px-4 py-2 font-semibold">Expense Subtotal</td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $currency($expenseTotal) }}</td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $pct($incomeTotal > 0 ? ($expenseTotal / $incomeTotal) * 100 : 0) }}</td>
                            </tr>

                            <tr class="{{ $netIncome >= 0 ? 'bg-emerald-50' : 'bg-red-50' }}">
                                <td class="px-4 py-3 font-semibold text-gray-900">Net Realized Surplus</td>
                                <td class="px-4 py-3 text-right text-lg font-bold tabular-nums {{ $netIncome >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $currency($netIncome) }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-800">{{ $pct($netMargin) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </x-loan.page>
</x-loan-layout>
