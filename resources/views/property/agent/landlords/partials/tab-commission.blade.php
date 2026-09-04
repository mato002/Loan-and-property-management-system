@php
    $commissionTotals = $commissionTotals ?? ['collected' => 0, 'landlord_share' => 0, 'commission' => 0, 'landlord_net' => 0];
    $commissionRows = $commissionRows ?? [];
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:bg-gray-800/80">
        <p class="text-[11px] uppercase text-slate-500">Collected (gross)</p>
        <p class="text-lg font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['collected'] ?? 0)) }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:bg-gray-800/80">
        <p class="text-[11px] uppercase text-slate-500">Owner share</p>
        <p class="text-lg font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['landlord_share'] ?? 0)) }}</p>
    </div>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3 dark:bg-emerald-950/20">
        <p class="text-[11px] uppercase text-emerald-700">Your commission</p>
        <p class="text-lg font-semibold tabular-nums text-emerald-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['commission'] ?? 0)) }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:bg-gray-800/80">
        <p class="text-[11px] uppercase text-slate-500">Net to landlord</p>
        <p class="text-lg font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($commissionTotals['landlord_net'] ?? 0)) }}</p>
    </div>
</div>

<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Commission by property</h3>
            <p class="text-xs text-slate-500">{{ $periodLabel }}</p>
        </div>
        <a href="{{ route('property.financials.commission', array_filter(['month' => $monthValue ?? '', 'landlord_id' => $landlord->id]), false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Full commission register →</a>
    </div>
    @if ($commissionRows === [])
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
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($commissionRows as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $row['property_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($row['ownership_percent'] ?? 0), 2) }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['owner_share'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($row['rate_pct'] ?? 0), 2) }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['commission'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['landlord_net'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
