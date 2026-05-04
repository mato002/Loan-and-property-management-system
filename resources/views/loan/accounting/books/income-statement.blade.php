<x-loan-layout>
    <x-loan.page title="Income Statement (Cash Basis)" subtitle="Real-time visibility into liquidity, performance, and compliance.">
        @php
            $currency = fn (float|int $amount): string => 'KSh '.number_format((float) $amount, 2);
            $pct = fn (float|int $value): string => number_format((float) $value, 1).'%';
            $coaLinkedAccounts = collect($sections)->sum(fn ($section) => count($section['account_ids'] ?? []));
            $netMargin = $incomeTotal > 0 ? ($netIncome / $incomeTotal) * 100 : 0;
            $rangeOptions = [
                'current_month' => 'Current Month',
                'previous_month' => 'Previous Month',
                'ytd' => 'Year-to-Date',
                'custom' => 'Custom Range',
            ];
            $displaySections = [
                'revenue',
                'direct_cost',
                'operating_expense',
                'tax_expense',
                'other_income',
                'other_expense',
            ];
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
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label for="range" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Range</label>
                        <select id="range" name="range" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700">
                            @foreach($rangeOptions as $key => $label)
                                <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="from" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">From</label>
                        <input id="from" name="from" type="date" value="{{ $from->toDateString() }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700">
                    </div>
                    <div>
                        <label for="to" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">To</label>
                        <input id="to" name="to" type="date" value="{{ $to->toDateString() }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 font-semibold text-white shadow-sm hover:bg-emerald-700">Apply</button>
                    </div>
                </div>
            </form>

            @if(!empty($profitIntegrityWarning))
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 shadow-sm">
                    <p class="text-sm font-semibold">Profit may be incomplete or misclassified</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @if(!empty($hasUnclassifiedAccounts))
                            <li>
                                Unclassified chart activity:
                                {{ number_format((int) ($unclassifiedWarning['account_count'] ?? 0)) }} account(s),
                                {{ number_format((int) ($unclassifiedWarning['transaction_count'] ?? 0)) }} line(s),
                                amount {{ $currency((float) ($unclassifiedWarning['amount'] ?? 0)) }}.
                            </li>
                        @endif
                        @if(!empty($companyExpensesNotInGl))
                            <li>Some company expenses in this period are not linked to the general ledger (older entries or a failed posting).</li>
                        @endif
                    </ul>
                </div>
            @endif

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
                            @php $hasRows = false; @endphp
                            @foreach ($displaySections as $sectionKey)
                                @php
                                    $section = $sections[$sectionKey] ?? ['label' => ucwords(str_replace('_', ' ', $sectionKey)), 'rows' => [], 'total' => 0, 'drilldown' => ['transaction_count' => 0, 'account_ids' => []]];
                                    $sectionRows = collect($section['rows'] ?? []);
                                    $hasRows = $hasRows || $sectionRows->isNotEmpty();
                                @endphp
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $section['label'] }}</td>
                                </tr>
                                @forelse ($sectionRows as $row)
                                    @php
                                        $share = $incomeTotal > 0 ? ((float) $row['amount'] / $incomeTotal) * 100 : 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-900">
                                            {{ $row['account_code'] }} - {{ $row['account_name'] }}
                                            <div class="mt-1 text-[11px] text-gray-500">
                                                Tx: {{ number_format((int) ($row['transaction_count'] ?? 0)) }}
                                                | Range: {{ $row['date_range']['from'] ?? $from->toDateString() }} to {{ $row['date_range']['to'] ?? $to->toDateString() }}
                                                | Account ID: {{ $row['account_id'] }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ $currency($row['amount']) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $pct($share) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-sm text-gray-500">No posted journals for this section in selected period.</td>
                                    </tr>
                                @endforelse
                                <tr class="bg-gray-50 text-gray-700">
                                    <td class="px-4 py-2 font-semibold">{{ $section['label'] }} Subtotal</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $currency((float) ($section['total'] ?? 0)) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums">
                                        {{ $pct($incomeTotal > 0 ? (((float) ($section['total'] ?? 0)) / $incomeTotal) * 100 : 0) }}
                                        <span class="ml-2 text-[11px] font-normal text-gray-500">
                                            {{ number_format((int) (($section['drilldown']['transaction_count'] ?? 0))) }} tx
                                        </span>
                                    </td>
                                </tr>
                            @endforeach

                            @if(!$hasRows)
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-sm text-gray-500">No posted income/expense journals found for selected period.</td>
                                </tr>
                            @endif

                            @php $unclassified = $sections['unclassified'] ?? ['total' => 0, 'drilldown' => ['transaction_count' => 0]]; @endphp
                            @if(abs((float) ($unclassified['total'] ?? 0)) > 0.00001)
                                <tr class="bg-amber-50 text-amber-800">
                                    <td class="px-4 py-2 font-semibold">Unclassified Subtotal</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ $currency((float) ($unclassified['total'] ?? 0)) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ number_format((int) ($unclassified['drilldown']['transaction_count'] ?? 0)) }} tx</td>
                                </tr>
                            @endif

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
