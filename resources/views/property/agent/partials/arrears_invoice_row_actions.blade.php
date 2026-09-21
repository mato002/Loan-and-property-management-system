@php
    /** @var \App\Models\PmInvoice $invoice */
    $invoiceId = (int) $invoice->id;
    $showUrl = route('property.revenue.invoices.show', $invoice, false);
    $remindUrl = route('property.revenue.arrears.reminders', absolute: false);
@endphp
<x-property.action-menu width="w-52">
    <a href="{{ $showUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">View invoice</a>
    <a href="{{ $showUrl }}#record-payment" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Record payment</a>
    <form method="post" action="{{ $remindUrl }}" class="block border-t border-slate-100 dark:border-slate-700" data-turbo-frame="property-main" data-swal-confirm="Send a friendly SMS + email reminder for invoice {{ $invoice->invoice_no }}?">
        @csrf
        <input type="hidden" name="template_key" value="friendly" />
        <input type="hidden" name="channel" value="both" />
        <input type="hidden" name="target_mode" value="single" />
        <input type="hidden" name="single_invoice_id" value="{{ $invoiceId }}" />
        <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Send reminder</button>
    </form>
    <a href="{{ route('property.tenants.notices', ['tenant_id' => $invoice->pm_tenant_id, 'view' => 1], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Open notices</a>
</x-property.action-menu>
