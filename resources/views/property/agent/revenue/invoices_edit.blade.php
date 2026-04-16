<x-property.workspace
    title="Edit invoice"
    subtitle="Adjust billing details and status for this invoice."
    back-route="property.revenue.invoices"
>
    <div class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
            <p><span class="font-semibold">Invoice #:</span> {{ $invoice->invoice_no }}</p>
            <p><span class="font-semibold">Tenant:</span> {{ $invoice->tenant?->name ?? '—' }}</p>
            <p><span class="font-semibold">Unit:</span> {{ ($invoice->unit?->property?->name ?? '—') . ' / ' . ($invoice->unit?->label ?? '—') }}</p>
            <p><span class="font-semibold">Amount paid:</span> KES {{ number_format((float) $invoice->amount_paid, 2) }}</p>
        </div>

        <form method="post" action="{{ route('property.revenue.invoices.update', $invoice, false) }}" class="space-y-4">
            @csrf
            @method('put')

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Issue date</label>
                    <input type="date" name="issue_date" value="{{ old('issue_date', optional($invoice->issue_date)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Due date</label>
                    <input type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Amount (KES)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount', (float) $invoice->amount) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="draft" @selected(old('status', $invoice->status) === 'draft')>Draft</option>
                        <option value="sent" @selected(old('status', $invoice->status) === 'sent')>Sent</option>
                        <option value="cancelled" @selected(old('status', $invoice->status) === 'cancelled')>Cancelled</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Description</label>
                <input type="text" name="description" value="{{ old('description', $invoice->description) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </div>
</x-property.workspace>
