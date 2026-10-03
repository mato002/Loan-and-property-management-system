<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Invoices</h3>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" data-property-modal-open="showHubInvoiceForm" @click="showHubInvoiceForm = true">Create invoice</button>
            <a href="{{ route('property.revenue.invoices', ['tenant_id' => $tenant->id, 'q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-slate-600 hover:underline self-center">Invoice register</a>
            <a href="{{ route('property.tenants.statement', ['tenant' => $tenant->id, 'export' => 'pdf'], false) }}" data-turbo="false" class="text-xs font-semibold text-slate-700 hover:underline self-center">Statement PDF</a>
        </div>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Invoice</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Balance</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($recentInvoices ?? []) as $invoice)
                @php
                    $balance = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);
                    $statusKey = (string) ($invoice->status ?? '');
                    if ($statusKey === \App\Models\PmInvoice::STATUS_SENT && (bool) ($invoice->is_past_due ?? false)) {
                        $statusKey = \App\Models\PmInvoice::STATUS_OVERDUE;
                    }
                    [$statusLabel, $statusClass] = match ($statusKey) {
                        \App\Models\PmInvoice::STATUS_PAID => ['Paid', 'bg-emerald-100 text-emerald-700'],
                        \App\Models\PmInvoice::STATUS_PARTIAL => ['Partially paid', 'bg-amber-100 text-amber-800'],
                        \App\Models\PmInvoice::STATUS_OVERDUE => ['Overdue', 'bg-red-100 text-red-700'],
                        \App\Models\PmInvoice::STATUS_CANCELLED => ['Cancelled', 'bg-slate-200 text-slate-600'],
                        \App\Models\PmInvoice::STATUS_DRAFT => ['Draft', 'bg-slate-100 text-slate-700'],
                        default => [$balance > 0.009 ? 'Unpaid' : 'Paid', $balance > 0.009 ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'],
                    };
                @endphp
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3">
                        <a href="{{ route('property.revenue.invoices.show', $invoice, false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">{{ $invoice->invoice_no ?: '#'.$invoice->id }}</a>
                    </td>
                    <td class="px-4 py-3">{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($invoice->invoice_type ?? 'charge')) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $invoice->amount) }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($balance) }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @include('property.agent.partials.invoice_row_actions', ['invoice' => $invoice])
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
