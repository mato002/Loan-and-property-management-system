<x-property.workspace
    title="Invoice details"
    subtitle="View invoice header, balances, and applied payments."
    back-route="property.revenue.invoices"
>
    <div class="space-y-4 max-w-4xl">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Invoice</p>
                    <h2 class="text-xl font-semibold text-slate-900">{{ $invoice->invoice_no }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase text-slate-700">{{ $invoice->status }}</span>
                    <a href="{{ route('property.revenue.invoices.edit', $invoice, false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                <p><span class="font-semibold text-slate-600">Tenant:</span> {{ $invoice->tenant?->name ?? '—' }}</p>
                <p><span class="font-semibold text-slate-600">Unit:</span> {{ ($invoice->unit?->property?->name ?? '—') . ' / ' . ($invoice->unit?->label ?? '—') }}</p>
                <p><span class="font-semibold text-slate-600">Issue date:</span> {{ optional($invoice->issue_date)->format('Y-m-d') ?? '—' }}</p>
                <p><span class="font-semibold text-slate-600">Due date:</span> {{ optional($invoice->due_date)->format('Y-m-d') ?? '—' }}</p>
                <p><span class="font-semibold text-slate-600">Amount:</span> KES {{ number_format((float) $invoice->amount, 2) }}</p>
                <p><span class="font-semibold text-slate-600">Paid:</span> KES {{ number_format((float) $invoice->amount_paid, 2) }}</p>
                <p class="sm:col-span-2"><span class="font-semibold text-slate-600">Balance:</span> KES {{ number_format(max(0, (float) $invoice->amount - (float) $invoice->amount_paid), 2) }}</p>
                <p class="sm:col-span-2"><span class="font-semibold text-slate-600">Description:</span> {{ $invoice->description ?: '—' }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-800 mb-3">Payment allocations</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Payment ref</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Method</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Allocated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($invoice->allocations as $allocation)
                            <tr>
                                <td class="px-3 py-2 text-slate-700">{{ $allocation->payment?->payment_ref ?? ('PAY-'.$allocation->pm_payment_id) }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ optional($allocation->payment?->paid_at)->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $allocation->payment?->payment_method ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $allocation->payment?->status ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-medium text-slate-800">KES {{ number_format((float) $allocation->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-slate-500">No payment allocations yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-property.workspace>
