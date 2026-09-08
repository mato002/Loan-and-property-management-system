<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Invoices</h3>
        <a href="{{ route('property.revenue.invoices', ['tenant_id' => $tenant->id, 'q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-cyan-700 hover:underline">Open invoice workspace</a>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Invoice</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($recentInvoices ?? []) as $invoice)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">
                        <a href="{{ route('property.revenue.invoices.show', $invoice, false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">{{ $invoice->invoice_no ?: '#'.$invoice->id }}</a>
                    </td>
                    <td class="px-4 py-3">{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($invoice->invoice_type ?? 'charge')) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $invoice->amount) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes(max(0, (float) $invoice->amount - (float) $invoice->amount_paid)) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
