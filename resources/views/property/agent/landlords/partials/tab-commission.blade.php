@php
    use App\Support\Property\LandlordMonthlyShareTotals;

    $fy = (int) ($fyValue ?? now()->year);
    $uptoMonth = LandlordMonthlyShareTotals::uptoMonth($fy);
    $toDateLabel = LandlordMonthlyShareTotals::periodHint($fy, $uptoMonth);
    $currentShareMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($monthValue ?? '')) ? (string) $monthValue : now()->format('Y-m');
    $rateByProperty = collect($commissionRows ?? [])->keyBy(fn ($row) => (int) ($row['property_id'] ?? 0));
    $propertyRows = collect($propertyBreakdown ?? []);
    if ($propertyRows->isEmpty()) {
        $propertyRows = collect($commissionRows ?? []);
    }

    $commissionDisplayRows = $propertyRows->map(function ($row) use ($rateByProperty, $uptoMonth) {
        $row = is_array($row) ? $row : [];
        $pid = (int) ($row['property_id'] ?? 0);
        $ytd = LandlordMonthlyShareTotals::toDate($row['monthly_shares'] ?? [], $uptoMonth);
        $rateRow = $rateByProperty->get($pid);

        return [
            'property_id' => $pid,
            'property_name' => (string) ($row['property_name'] ?? '—'),
            'ownership_percent' => (float) ($row['ownership_percent'] ?? 0),
            'rate_pct' => (float) ($rateRow['rate_pct'] ?? ($row['commission_percent'] ?? 0)),
            'collected' => $ytd['gross_collected'],
            'owner_share' => $ytd['owner_share'],
            'commission' => $ytd['agent_earning'],
            'landlord_net' => $ytd['landlord_net'],
            'monthly_shares' => $row['monthly_shares'] ?? [],
        ];
    })->values()->all();

    $commissionTotals = [
        'collected' => (float) collect($commissionDisplayRows)->sum('collected'),
        'landlord_share' => (float) collect($commissionDisplayRows)->sum('owner_share'),
        'commission' => (float) collect($commissionDisplayRows)->sum('commission'),
        'landlord_net' => (float) collect($commissionDisplayRows)->sum('landlord_net'),
    ];
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:bg-gray-800/80">
        <p class="text-[11px] uppercase text-slate-500">Collected (gross)</p>
        <p class="text-lg font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['collected'] ?? 0)) }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5">{{ $toDateLabel }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:bg-gray-800/80">
        <p class="text-[11px] uppercase text-slate-500">Owner share</p>
        <p class="text-lg font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['landlord_share'] ?? 0)) }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5">{{ $toDateLabel }}</p>
    </div>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3 dark:bg-emerald-950/20">
        <p class="text-[11px] uppercase text-emerald-700">Your commission</p>
        <p class="text-lg font-semibold tabular-nums text-emerald-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['commission'] ?? 0)) }}</p>
        <p class="text-[11px] text-emerald-700/70 mt-0.5">{{ $toDateLabel }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:bg-gray-800/80">
        <p class="text-[11px] uppercase text-slate-500">Net to landlord</p>
        <p class="text-lg font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['landlord_net'] ?? 0)) }}</p>
        <p class="text-[11px] text-slate-400 mt-0.5">{{ $toDateLabel }}</p>
    </div>
</div>

<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Commission by property</h3>
            <p class="text-xs text-slate-500">Totals {{ $toDateLabel }}. Monthly figures are in the row expander.</p>
        </div>
        <a href="{{ route('property.financials.commission', array_filter(['month' => $monthValue ?? '', 'landlord_id' => $landlord->id]), false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Full commission register →</a>
    </div>
    @if ($commissionDisplayRows === [])
        <p class="p-6 text-sm text-slate-500">No commission rows for this period.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Property</th>
                        <th class="px-4 py-3 text-right">Ownership</th>
                        <th class="px-4 py-3 text-right">Collected</th>
                        <th class="px-4 py-3 text-right">Rate</th>
                        <th class="px-4 py-3 text-right">Commission</th>
                        <th class="px-4 py-3 text-right">Net to owner</th>
                    </tr>
                </thead>
                @foreach ($commissionDisplayRows as $row)
                    @php
                        $pid = (int) ($row['property_id'] ?? 0);
                        $propertyUrl = $pid > 0 ? route('property.properties.show', ['property' => $pid], false) : '#';
                    @endphp
                    <tbody x-data="{ open: false }" class="border-t border-slate-100 dark:border-slate-700">
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                @include('property.agent.landlords.partials.monthly-share-toggle', [
                                    'propertyUrl' => $propertyUrl,
                                    'propertyName' => (string) ($row['property_name'] ?? '—'),
                                ])
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($row['ownership_percent'] ?? 0), 2) }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['collected'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($row['rate_pct'] ?? 0), 2) }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['commission'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['landlord_net'] ?? 0)) }}</td>
                        </tr>
                        <tr x-show="open" x-cloak class="bg-slate-50/70 dark:bg-slate-900/40">
                            <td colspan="6" class="px-4 py-3" data-row-ignore-click>
                                @include('property.agent.landlords.partials.monthly-share-panel', [
                                    'months' => $row['monthly_shares'] ?? [],
                                    'currentMonth' => $currentShareMonth,
                                    'variant' => 'commission',
                                ])
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
</div>
