@php
    $settlementRows = $settlementRows ?? [];
    $periodMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($monthValue ?? '')) ? (string) $monthValue : now()->format('Y-m');
@endphp

<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Monthly settlements</h3>
            <p class="text-xs text-slate-500">Per property remittance status for {{ $periodLabel }}</p>
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
                        <th class="px-4 py-3 text-right">Amount payable</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($settlementRows as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $row['property_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['collected'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['management_fee'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['amount_payable'] ?? 0)) }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold capitalize">{{ $row['status'] ?? '—' }}</span></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $row['property_id'], 'landlord_id' => $row['landlord_id'], 'month' => $row['period_month'] ?? $periodMonth], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-700 hover:underline">Settlement detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
