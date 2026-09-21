@php
    $tenantId = (int) ($tenantId ?? 0);
    $invoiceIds = collect($invoiceIds ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
    $primaryInvoiceId = (int) ($primaryInvoiceId ?? ($invoiceIds[0] ?? 0));
    $detailUrl = route('property.revenue.arrears.tenant', ['tenant' => $tenantId], false);
    $noticesUrl = route('property.tenants.notices', ['tenant_id' => $tenantId, 'view' => 1], false);
    $paymentsUrl = route('property.revenue.payments', array_filter([
        'tenant_id' => $tenantId > 0 ? $tenantId : null,
        'invoice_id' => $primaryInvoiceId > 0 ? $primaryInvoiceId : null,
    ]), false);
    $remindUrl = route('property.revenue.arrears.reminders', absolute: false);
@endphp
<x-property.action-menu width="w-52">
    <a href="{{ $detailUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">View invoices</a>
    @if ($primaryInvoiceId > 0)
        <a href="{{ route('property.revenue.invoices.show', $primaryInvoiceId, false) }}#record-payment" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Record payment</a>
    @else
        <a href="{{ $paymentsUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Record payment</a>
    @endif
    @if ($invoiceIds !== [])
        <form method="post" action="{{ $remindUrl }}" class="block border-t border-slate-100 dark:border-slate-700" data-turbo-frame="property-main" data-swal-confirm="Send a friendly SMS + email reminder for this tenant's overdue invoices?">
            @csrf
            <input type="hidden" name="template_key" value="friendly" />
            <input type="hidden" name="channel" value="both" />
            <input type="hidden" name="target_mode" value="selected" />
            <input type="hidden" name="selected_invoice_ids_raw" value="{{ implode(',', $invoiceIds) }}" />
            <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Send reminder</button>
        </form>
    @endif
    <a href="{{ $noticesUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Open notices</a>
</x-property.action-menu>
