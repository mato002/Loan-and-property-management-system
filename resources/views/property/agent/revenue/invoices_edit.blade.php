@php
    use App\Models\PmInvoice;
    $current = (string) old('status', $invoice->status);
    $statusOptions = [
        PmInvoice::STATUS_DRAFT => 'Draft',
        PmInvoice::STATUS_SENT => 'Sent',
        PmInvoice::STATUS_CANCELLED => 'Cancelled',
    ];
    // Computed statuses (partial/paid/overdue) are NOT manually editable.
    // If the invoice is currently in one of those, show it disabled-style
    // and revert to the underlying manual status (sent) on save.
    $isComputed = in_array($invoice->status, [PmInvoice::STATUS_PARTIAL, PmInvoice::STATUS_PAID, PmInvoice::STATUS_OVERDUE], true);
@endphp
<x-property.crud-shell :in-property-form-modal="$inPropertyFormModal ?? false"
    title="Edit invoice"
    subtitle="Adjust billing details and status. Material changes (amount, cancel) re-post journal entries automatically."
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
            <p><span class="font-semibold">Current status:</span> <span class="uppercase">{{ $invoice->status }}</span>
                @if ($isComputed)
                    <span class="text-xs text-slate-500">(computed; saving will revert to a manual status)</span>
                @endif
            </p>
        </div>

        <form method="post" action="{{ route('property.revenue.invoices.update', $invoice) }}" class="space-y-4">
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
                    <input type="number" name="amount" step="0.01" min="{{ (float) $invoice->amount_paid }}" value="{{ old('amount', (float) $invoice->amount) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    @if ((float) $invoice->amount_paid > 0)
                        <p class="text-[11px] text-slate-500 mt-1">Cannot go below already paid amount (KES {{ number_format((float) $invoice->amount_paid, 2) }}).</p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($current === $value || ($isComputed && $value === PmInvoice::STATUS_SENT))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-data="{ show: {{ $current === PmInvoice::STATUS_CANCELLED ? 'true' : 'false' }} }">
                <label class="block text-xs font-medium text-slate-600">Cancellation reason (only required if cancelling)</label>
                <input type="text" name="cancelled_reason" value="{{ old('cancelled_reason', $invoice->cancelled_reason) }}" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Description (short)</label>
                <input type="text" name="description" value="{{ old('description', $invoice->description) }}" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Internal notes (long, not printed)</label>
                <textarea name="notes" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">{{ old('notes', $invoice->notes) }}</textarea>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
                <a href="{{ route('property.revenue.invoices.show', $invoice) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>

        <p class="mt-4 text-xs text-slate-500">
            Material changes — switching to <em>Cancelled</em>, reopening, or changing the amount — automatically reverse and re-post journal entries so the trust ledger stays in sync.
        </p>
    </div>
</x-property.crud-shell>
