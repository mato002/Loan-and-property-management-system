<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Utility readings</h3>
        <a href="{{ route('property.revenue.utilities', [], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-blue-700 hover:underline">Utility billing workspace</a>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Billing month</th>
                <th class="px-4 py-3">Previous</th>
                <th class="px-4 py-3">Current</th>
                <th class="px-4 py-3">Consumption</th>
                <th class="px-4 py-3">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($utilityReadings ?? []) as $reading)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">{{ $reading->billing_month ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($reading->previous_reading ?? 0), 2) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($reading->current_reading ?? 0), 2) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($reading->units_used ?? 0), 2) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($reading->amount ?? 0)) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No utility readings linked to this tenant's units.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
