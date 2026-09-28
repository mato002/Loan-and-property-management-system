@php
    /** @var \App\Models\PmEzenBill $bill */
    $vendorName = (string) ($bill->vendor_name ?? '');
    $due = (float) ($bill->amount_due ?? 0);
    $isPaid = strtolower((string) ($bill->payment_status ?? '')) === \App\Models\PmEzenBill::STATUS_PAID;
@endphp
<x-property.action-menu width="w-52">
    @if ($vendorName !== '')
        <a href="{{ route('property.vendors.directory', ['q' => $vendorName], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Find vendor</a>
    @endif
    @if (! $isPaid && $due > 0.009)
        <form method="post" action="{{ route('property.accounting.payables.accounts_payable.mark_paid', $bill) }}" class="block" data-turbo-frame="property-main" data-swal-confirm="Mark bill {{ $bill->ezen_bill_no }} as paid / closed? This only updates listing status — it does not create a payment voucher.">
            @csrf
            <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-800 hover:bg-emerald-50 dark:text-emerald-200 dark:hover:bg-slate-700/50">Mark paid / closed</button>
        </form>
        <a href="{{ route('property.accounting.payables.payment_vouchers', ['q' => $bill->ezen_bill_no], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-50 dark:text-teal-200 dark:hover:bg-slate-700/50">Related vouchers</a>
        <a href="{{ route('property.settings.register_imports') }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Import payment voucher</a>
    @endif
    <a href="{{ route('property.accounting.payables.accounts_payable', ['q' => $bill->ezen_bill_no], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50">Filter this bill</a>
</x-property.action-menu>
