@php
    use App\Support\Property\LandlordMonthlyShareTotals;

    $settlementRows = $settlementRows ?? [];
    $fy = (int) ($fyValue ?? now()->year);
    $uptoMonth = LandlordMonthlyShareTotals::uptoMonth($fy);
    $toDateLabel = LandlordMonthlyShareTotals::periodHint($fy, $uptoMonth);
    $periodMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($monthValue ?? '')) ? (string) $monthValue : now()->format('Y-m');
    $monthlyByProperty = collect($propertyBreakdown ?? [])->keyBy('property_id');
    $currentShareMonth = $periodMonth;
@endphp

<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Monthly settlements</h3>
            <p class="text-xs text-slate-500">Totals {{ $toDateLabel }}. Monthly remittance figures are in the row expander.</p>
        </div>
        <a href="{{ route('property.accounting.payables.landlord_payment_fees', ['landlord_id' => $landlord->id, 'month' => $periodMonth], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Payment &amp; fees workspace →</a>
    </div>
    @if ($settlementRows === [])
        <p class="p-6 text-sm text-slate-500">No settlement rows — link properties or choose a month with collections.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Property</th>
                        <th class="px-4 py-3 text-right">Collected</th>
                        <th class="px-4 py-3 text-right">Mgmt fee</th>
                        <th class="px-4 py-3 text-right">Expenses</th>
                        <th class="px-4 py-3 text-right">Amount payable</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                @foreach ($settlementRows as $row)
                    @php
                        $pid = (int) ($row['property_id'] ?? 0);
                        $propertyUrl = $pid > 0 ? route('property.properties.show', ['property' => $pid], false) : '#';
                        $breakdown = $monthlyByProperty->get($pid);
                        $months = is_array($breakdown) ? ($breakdown['monthly_shares'] ?? []) : [];
                        $ytd = LandlordMonthlyShareTotals::toDate($months, $uptoMonth);
                    @endphp
                    <tbody x-data="{ open: false }" class="border-t border-slate-100 dark:border-slate-700">
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                @include('property.agent.landlords.partials.monthly-share-toggle', [
                                    'propertyUrl' => $propertyUrl,
                                    'propertyName' => (string) ($row['property_name'] ?? '—'),
                                ])
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($ytd['gross_collected']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($ytd['agent_earning']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($ytd['expenses'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes($ytd['landlord_net']) }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold capitalize">{{ $row['status'] ?? '—' }}</span></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'month' => $row['period_month'] ?? $periodMonth], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-700 hover:underline">Settlement detail</a>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak class="bg-slate-50/70 dark:bg-slate-900/40">
                            <td colspan="7" class="px-4 py-3" data-row-ignore-click>
                                @include('property.agent.landlords.partials.monthly-share-panel', [
                                    'months' => $months,
                                    'currentMonth' => $currentShareMonth,
                                    'variant' => 'settlement',
                                ])
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
</div>
