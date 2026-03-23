<x-property-layout>
    <x-slot name="header">Portfolio overview</x-slot>

    <x-property.page
        title="Portfolio overview"
        subtitle="Snapshot from invoices, units, arrears, and your landlord ledger — updates as your team records data."
    >
        <x-property.module-status label="Landlord portal" class="mb-4" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-900/50 bg-gradient-to-br from-white to-emerald-50/50 dark:from-gray-900 dark:to-emerald-950/20 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-800/80 dark:text-emerald-300/80">Income this month</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">{{ $incomeMonth }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Gross billed on your units (invoice issue date this month).</p>
            </div>
            <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-900/50 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Collected this month</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">{{ $incomeCollectedMonth }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Collection rate: <span class="font-semibold">{{ $collectionRateDisplay }}</span></p>
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
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="py-2 pr-3">Property</th>
                                <th class="py-2 pr-3">Units</th>
                                <th class="py-2 pr-3">Occupancy</th>
                                <th class="py-2 pr-3">Billed (MTD)</th>
                                <th class="py-2">Arrears</th>
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
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="py-2 pr-3">Invoice</th>
                                <th class="py-2 pr-3">Property / Unit</th>
                                <th class="py-2 pr-3">Issue date</th>
                                <th class="py-2 pr-3">Amount</th>
                                <th class="py-2">Outstanding</th>
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
    </x-property.page>
</x-property-layout>
