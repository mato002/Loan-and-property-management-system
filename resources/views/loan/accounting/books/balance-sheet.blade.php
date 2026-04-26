<x-loan-layout>
    @php
        $formatMoney = fn (float $value): string => 'KSh '.number_format($value, 2);
        $formatPct = fn (float $value): string => number_format($value, 1).'%';
        $asAtLabel = optional($asOf)->format('M d, Y') ?? now()->format('M d, Y');
        $totalEquityValue = (float) ($totalEquity + $netIncomeYtd);
        $liabilityAndEquity = (float) ($totalLiabilities + $totalEquityValue);
        $balanceDifference = (float) $totalAssets - $liabilityAndEquity;
        $isBalanced = abs($balanceDifference) < 0.01;
        $assetBase = abs((float) $totalAssets) > 0.00001 ? abs((float) $totalAssets) : 1.0;
        $currentRatio = $totalLiabilities > 0 ? ((float) $totalAssets / (float) $totalLiabilities) : 0.0;
    @endphp

    <x-loan.page title="Balance Sheet (Cash Basis)" subtitle="As at {{ $asAtLabel }} • COA Linked">
        <div class="space-y-6 rounded-xl bg-gray-50 p-4 sm:p-6">
            <div class="flex justify-end">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
                        <button type="button" class="rounded-md bg-blue-600 px-3 py-1.5 font-medium text-white">Cash Basis</button>
                        <button type="button" class="rounded-md px-3 py-1.5 text-gray-600 hover:bg-gray-100">Accrual Basis</button>
                    </div>
                    <input
                        id="as_of"
                        name="as_of"
                        type="date"
                        value="{{ $asOf->toDateString() }}"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700"
                    />
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-blue-600 hover:bg-gray-100">
                        Apply
                    </button>
                </form>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Current Ratio (Assets / Liabilities)</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($currentRatio, 2) }} : 1</p>
                    <p class="mt-1 text-sm font-medium {{ $currentRatio >= 1 ? 'text-green-600' : 'text-orange-600' }}">
                        {{ $currentRatio >= 1 ? 'Healthy' : 'Watch Closely' }}
                    </p>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Total Assets</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $formatMoney((float) $totalAssets) }}</p>
                    <div class="mt-1 flex items-center justify-between text-sm">
                        <span class="text-gray-500">COA driven</span>
                        <span class="font-medium text-blue-600">Live</span>
                    </div>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Total Equity (+ YTD)</p>
                    <p class="mt-2 text-2xl font-semibold text-purple-700">{{ $formatMoney($totalEquityValue) }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $formatPct(($totalEquityValue / $assetBase) * 100) }} of total assets</p>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Balance Integrity</p>
                    <p class="mt-2 text-2xl font-semibold {{ $isBalanced ? 'text-green-700' : 'text-orange-600' }}">{{ $isBalanced ? 'Balanced' : 'Review Needed' }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $isBalanced ? 'Assets = Liabilities + Equity' : ('Out by '.$formatMoney(abs($balanceDifference))) }}</p>
                </article>
            </div>

            <div>
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-gray-500">
                                    <th class="px-4 py-3 font-medium">COA Code</th>
                                    <th class="px-4 py-3 font-medium">Account / Category</th>
                                    <th class="px-4 py-3 font-medium text-right">Current (KSh)</th>
                                    <th class="px-4 py-3 font-medium text-right">% of Total Assets</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="bg-gray-50 text-gray-900">
                                    <td colspan="4" class="px-4 py-2 font-semibold">A. ASSETS</td>
                                </tr>
                                @forelse ($assets as $row)
                                    @php
                                        $amount = (float) $row['amount'];
                                    @endphp
                                    <tr class="balance-row cursor-pointer transition-colors hover:bg-gray-100">
                                        <td class="px-4 py-2 text-gray-500">{{ $row['account']->code }}</td>
                                        <td class="px-4 py-2 text-gray-700">{{ $row['account']->name }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-gray-900">{{ $formatMoney($amount) }}</td>
                                        <td class="px-4 py-2 text-right text-gray-700">{{ $formatPct((abs($amount) / $assetBase) * 100) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-center text-gray-500">No asset balances found for this date.</td>
                                    </tr>
                                @endforelse
                                <tr class="bg-green-50 text-green-900">
                                    <td colspan="2" class="px-4 py-3 font-semibold">TOTAL ASSETS</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $formatMoney((float) $totalAssets) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">100.0%</td>
                                </tr>

                                <tr class="bg-gray-50 text-gray-900">
                                    <td colspan="4" class="px-4 py-2 font-semibold">B. LIABILITIES</td>
                                </tr>
                                @forelse ($liabilities as $row)
                                    @php
                                        $amount = (float) $row['amount'];
                                    @endphp
                                    <tr class="balance-row cursor-pointer transition-colors hover:bg-gray-100">
                                        <td class="px-4 py-2 text-gray-500">{{ $row['account']->code }}</td>
                                        <td class="px-4 py-2 text-gray-700">{{ $row['account']->name }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-red-600">{{ $formatMoney($amount) }}</td>
                                        <td class="px-4 py-2 text-right text-gray-700">{{ $formatPct((abs($amount) / $assetBase) * 100) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-center text-gray-500">No liability balances found for this date.</td>
                                    </tr>
                                @endforelse
                                <tr class="bg-red-50 text-red-900">
                                    <td colspan="2" class="px-4 py-3 font-semibold">TOTAL LIABILITIES</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $formatMoney((float) $totalLiabilities) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $formatPct(((float) $totalLiabilities / $assetBase) * 100) }}</td>
                                </tr>

                                <tr class="bg-gray-50 text-gray-900">
                                    <td colspan="4" class="px-4 py-2 font-semibold">C. EQUITY</td>
                                </tr>
                                @forelse ($equity as $row)
                                    @php
                                        $amount = (float) $row['amount'];
                                    @endphp
                                    <tr class="balance-row cursor-pointer transition-colors hover:bg-gray-100">
                                        <td class="px-4 py-2 text-gray-500">{{ $row['account']->code }}</td>
                                        <td class="px-4 py-2 text-gray-700">{{ $row['account']->name }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-purple-700">{{ $formatMoney($amount) }}</td>
                                        <td class="px-4 py-2 text-right text-gray-700">{{ $formatPct((abs($amount) / $assetBase) * 100) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-center text-gray-500">No equity balances found for this date.</td>
                                    </tr>
                                @endforelse
                                <tr class="bg-purple-50 text-purple-900">
                                    <td colspan="2" class="px-4 py-3 font-medium">YTD Net Income</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ $formatMoney((float) $netIncomeYtd) }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ $formatPct((abs((float) $netIncomeYtd) / $assetBase) * 100) }}</td>
                                </tr>
                                <tr class="bg-purple-50 text-purple-900">
                                    <td colspan="2" class="px-4 py-3 font-semibold">TOTAL EQUITY (+ YTD)</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $formatMoney($totalEquityValue) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $formatPct(($totalEquityValue / $assetBase) * 100) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm font-medium text-gray-700">Assets = Liabilities + Equity</p>
                            <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium {{ $isBalanced ? 'border-green-200 bg-green-50 text-green-700' : 'border-orange-200 bg-orange-50 text-orange-700' }}">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                {{ $isBalanced ? 'Balanced' : 'Needs Reconciliation' }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const rows = document.querySelectorAll('.balance-row');
                rows.forEach(function (row) {
                    row.addEventListener('click', function () {
                        rows.forEach(function (otherRow) {
                            otherRow.classList.remove('bg-blue-50', 'ring-1', 'ring-blue-200');
                        });
                        row.classList.add('bg-blue-50', 'ring-1', 'ring-blue-200');
                    });
                });
            });
        </script>
    </x-loan.page>
</x-loan-layout>
