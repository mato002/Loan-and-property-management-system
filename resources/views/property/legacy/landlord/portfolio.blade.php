<x-property-layout>
    <x-slot name="header">Portfolio overview</x-slot>

    <x-property.page
        title="Portfolio overview"
        subtitle="Snapshot from invoices, units, arrears, and your landlord ledger — updates as your team records data."
    >
        <x-property.module-status label="Landlord portal" class="mb-4" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-900/50 bg-gradient-to-br from-white to-emerald-50/50 dark:from-gray-900 dark:to-emerald-950/20 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-800/80 dark:text-emerald-300/80">Billed (due) this month</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">{{ $incomeMonth }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Sum of invoice amounts with due date in this month.</p>
            </div>
            <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Income received this month</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">{{ $incomeCollectedMonth }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">From completed payments this month (allocated to your units). Rate vs billed: <span class="font-semibold">{{ $collectionRateDisplay }}</span></p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Occupancy rate</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">{{ $occupancyDisplay }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $occupiedUnitsCount }}/{{ $totalUnitsCount }} occupied across {{ $propertyCount }} {{ Str::plural('property', $propertyCount) }}.</p>
            </div>
            <div class="rounded-2xl border border-amber-200/70 dark:border-amber-900/40 bg-amber-50/40 dark:bg-amber-950/15 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-amber-900/80 dark:text-amber-200/90">Arrears (income impact)</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">{{ $arrearsImpact }}</p>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Outstanding invoice balances on your units.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Net earnings</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">{{ $netEarnings }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Latest running balance from your ledger.</p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/60 p-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Operational health</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Vacant units</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $vacantUnitsCount }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Notice units</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $noticeUnitsCount }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Open maintenance</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $openMaintenanceCount }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Due next 30 days</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $dueNext30Days }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/60 p-6 xl:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Property performance breakdown</h2>
                @if (!empty($occPerProperty ?? []))
                    <div class="mt-3">
                        <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">Occupancy by property</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($occPerProperty as $o)
                                <div class="flex items-center gap-3">
                                    <div class="w-32 truncate text-xs text-slate-600 dark:text-slate-300">{{ $o['name'] }}</div>
                                    <div class="flex-1 h-2 rounded-full bg-slate-100 dark:bg-slate-900/50 overflow-hidden">
                                        <div class="h-full bg-emerald-500" style="width: {{ max(0, min(100, (int) $o['rate'])) }}%"></div>
                                    </div>
                                    <div class="w-10 text-right text-xs tabular-nums text-slate-600 dark:text-slate-300">{{ (int) $o['rate'] }}%</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full table-auto text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="py-2 pr-3 whitespace-normal break-words">Property</th>
                                <th class="py-2 pr-3 whitespace-normal break-words">Units</th>
                                <th class="py-2 pr-3 whitespace-normal break-words">Occupancy</th>
                                <th class="py-2 pr-3 whitespace-normal break-words">Billed (MTD)</th>
                                <th class="py-2 whitespace-normal break-words">Arrears</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($propertyBreakdown as $p)
                                <tr class="border-b border-slate-100 dark:border-slate-700/70">
                                    <td class="py-2 pr-3 font-medium text-slate-900 dark:text-white">{{ $p['name'] }}</td>
                                    <td class="py-2 pr-3">{{ $p['units'] }}</td>
                                    <td class="py-2 pr-3">{{ $p['occupancy'] !== null ? $p['occupancy'].'%' : '—' }}</td>
                                    <td class="py-2 pr-3">{{ \App\Services\Property\PropertyMoney::kes((float) $p['mtd_billed']) }}</td>
                                    <td class="py-2">{{ \App\Services\Property\PropertyMoney::kes((float) $p['arrears']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-3 text-slate-500 dark:text-slate-400">No properties linked yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/60 p-6 xl:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Recent invoice activity</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full table-auto text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="py-2 pr-3 whitespace-normal break-words">Invoice</th>
                                <th class="py-2 pr-3 whitespace-normal break-words">Property / Unit</th>
                                <th class="py-2 pr-3 whitespace-normal break-words">Issue date</th>
                                <th class="py-2 pr-3 whitespace-normal break-words">Amount</th>
                                <th class="py-2 whitespace-normal break-words">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentInvoices as $invoice)
                                <tr class="border-b border-slate-100 dark:border-slate-700/70">
                                    <td class="py-2 pr-3 font-medium text-slate-900 dark:text-white">{{ $invoice->invoice_no }}</td>
                                    <td class="py-2 pr-3">{{ $invoice->unit?->property?->name ?? '—' }} / {{ $invoice->unit?->label ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="py-2 pr-3">{{ \App\Services\Property\PropertyMoney::kes((float) $invoice->amount) }}</td>
                                    <td class="py-2">{{ \App\Services\Property\PropertyMoney::kes(max(0, (float) $invoice->amount - (float) $invoice->amount_paid)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-3 text-slate-500 dark:text-slate-400">No invoices yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/60 p-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Trust &amp; transparency</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">
                    Numbers trace to invoices and ledger postings.
                    @if (($digestCount ?? 0) > 0)
                        <a href="{{ route('property.landlord.notifications') }}" class="font-medium text-emerald-700 dark:text-emerald-400 hover:underline">{{ $digestCount }} active {{ Str::plural('item', $digestCount) }}</a>
                    @else
                        <a href="{{ route('property.landlord.notifications') }}" class="font-medium text-emerald-700 dark:text-emerald-400 hover:underline">View notifications</a>
                    @endif
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('property.landlord.earnings.index') }}" class="inline-flex rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-medium hover:bg-emerald-700 transition-colors">Earnings &amp; wallet</a>
                    <a href="{{ route('property.landlord.reports.index') }}" class="inline-flex rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">Reports</a>
                    <a href="{{ route('property.landlord.maintenance') }}" class="inline-flex rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">Maintenance</a>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/60 p-6 lg:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Billed vs Collected (last 6 months)</h2>
                @php
                    $chartMonthsSafe = $chartMonths ?? [];
                    $chartBilledSafe = $chartBilled ?? [];
                    $chartCollectedSafe = $chartCollected ?? [];
                    $mx = max(1.0, max(array_merge([0.0], array_map('floatval', $chartBilledSafe), array_map('floatval', $chartCollectedSafe))));
                @endphp
                @if (count($chartMonthsSafe) > 0)
                    <div class="mt-4 rounded-xl border border-slate-100 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/30 p-4">
                        <div class="flex items-end gap-3 h-44">
                            @foreach ($chartMonthsSafe as $idx => $m)
                                @php
                                    $b = (float) ($chartBilledSafe[$idx] ?? 0);
                                    $c = (float) ($chartCollectedSafe[$idx] ?? 0);
                                    $bh = max(2, (int) round(($b / $mx) * 100));
                                    $ch = max(2, (int) round(($c / $mx) * 100));
                                @endphp
                                <div class="flex-1 min-w-0">
                                    <div class="h-36 flex items-end justify-center gap-1">
                                        <div class="w-3 sm:w-4 rounded-t bg-emerald-500/85" style="height: {{ $bh }}%" title="Billed: {{ \App\Services\Property\PropertyMoney::kes($b) }}"></div>
                                        <div class="w-3 sm:w-4 rounded-t bg-amber-400/90" style="height: {{ $ch }}%" title="Collected: {{ \App\Services\Property\PropertyMoney::kes($c) }}"></div>
                                    </div>
                                    <div class="mt-2 text-center text-[11px] text-slate-600 dark:text-slate-400 truncate">{{ $m }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 flex items-center gap-4 text-xs text-slate-600 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 bg-emerald-500/85 rounded-sm"></span> Billed</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 bg-amber-400/90 rounded-sm"></span> Collected</span>
                        </div>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No monthly data yet.</p>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/60 p-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Quick ratios</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Collection rate</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $collectionRateDisplay }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Occupancy</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $occupancyDisplay }}</span>
                    </div>
                </div>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
