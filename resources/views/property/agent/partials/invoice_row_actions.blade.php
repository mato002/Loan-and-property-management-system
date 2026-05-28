@php
    use App\Models\PmInvoice;

    $balance = max(0, (float) $invoice->amount - (float) $invoice->amount_paid);
    $showAction = route('property.revenue.invoices.show', $invoice, false);
    $editAction = route('property.revenue.invoices.edit', $invoice, false);
    $destroyAction = route('property.revenue.invoices.destroy', $invoice, false);
    $pdfAction = route('property.revenue.invoices.pdf', $invoice, false);
    $statusAction = route('property.revenue.invoices.status', $invoice, false);
@endphp

<x-property.action-menu>
    <a href="{{ $showAction }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">View</a>
    <a href="{{ $editAction }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit</a>
    <a href="{{ $pdfAction }}" target="_blank" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Download PDF</a>
    @if ($balance > 0)
        <a href="{{ $showAction }}#record-payment" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Record payment</a>
    @endif
    <form method="post" action="{{ $statusAction }}" data-turbo-frame="property-main" class="block px-3 py-2 border-t border-slate-100 dark:border-slate-700">
        @csrf
        @method('patch')
        <select name="status" class="w-full rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-900 px-1.5 py-0.5 text-xs">
            @foreach ([PmInvoice::STATUS_DRAFT => 'Draft', PmInvoice::STATUS_SENT => 'Sent', PmInvoice::STATUS_CANCELLED => 'Cancelled'] as $value => $statusLabel)
                <option value="{{ $value }}" @selected((string) $invoice->status === $value)>{{ $statusLabel }}</option>
            @endforeach
        </select>
        <button type="submit" class="mt-2 rounded bg-slate-800 px-2 py-0.5 text-[11px] font-semibold text-white hover:bg-slate-700">Save status</button>
    </form>
    <form method="post" action="{{ $destroyAction }}" data-turbo-frame="property-main" data-swal-confirm="Delete this invoice? This only works for invoices without payments.">
        @csrf
        @method('delete')
        <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-red-700 hover:bg-rose-50 dark:text-red-300 dark:hover:bg-slate-700/50">Delete</button>
    </form>
</x-property.action-menu>
