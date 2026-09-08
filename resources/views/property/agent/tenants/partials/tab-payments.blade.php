<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Payments</h3>
        <a href="{{ route('property.revenue.payments', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-teal-700 hover:underline">Record payment</a>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Channel</th>
                <th class="px-4 py-3">Reference</th>
                <th class="px-4 py-3">Receipt</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($recentPayments ?? []) as $payment)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">{{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</td>
                    <td class="px-4 py-3 uppercase">{{ $payment->channel ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $payment->external_ref ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('property.payments.receipt.show', $payment, false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No payments recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
