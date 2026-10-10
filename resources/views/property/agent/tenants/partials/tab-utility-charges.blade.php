<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Other utility charges</h3>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('property.revenue.utilities', ['charge_type' => 'other'], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-blue-700 hover:underline">View all charges</a>
        </div>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Label</th>
                <th class="px-4 py-3">Billing month</th>
                <th class="px-4 py-3">Units</th>
                <th class="px-4 py-3">Rate</th>
                <th class="px-4 py-3">Fixed</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Invoiced</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($utilityCharges ?? []) as $charge)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            @if (($charge['charge_type'] ?? 'other') === 'electricity') bg-amber-100 text-amber-800
                            @elseif (($charge['charge_type'] ?? 'other') === 'service') bg-blue-100 text-blue-800
                            @elseif (($charge['charge_type'] ?? 'other') === 'garbage') bg-green-100 text-green-800
                            @else bg-slate-100 text-slate-800
                            @endif
                        ">
                            {{ ucfirst(($charge['charge_type'] ?? 'other')) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $charge['label'] ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $charge['billing_month'] ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($charge['units_consumed'] ?? 0), 2) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($charge['rate_per_unit'] ?? 0), 2) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($charge['fixed_charge'] ?? 0), 2) }}</td>
                    <td class="px-4 py-3 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($charge['amount'] ?? 0)) }}</td>
                    <td class="px-4 py-3">
                        @if (($charge['is_invoiced'] ?? false))
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Yes</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">No</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No other utility charges found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
