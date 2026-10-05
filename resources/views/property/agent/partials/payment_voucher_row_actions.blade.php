@php
    /** @var \App\Models\PmEzenPaymentVoucher $voucher */
    $payoutId = (int) ($voucher->pm_landlord_payout_id ?? 0);
    $entryId = (int) ($voucher->pm_accounting_entry_id ?? 0);
    $landlordId = (int) ($voucher->pm_landlord_id ?? 0);
    $property = $linkedProperty ?? $voucher->property;
    $propertyId = (int) ($property?->id ?? $voucher->property_id ?? 0);
    $unitId = (int) ($voucher->property_unit_id ?? 0);
    $unitLabel = trim((string) ($voucher->unit?->label ?? ''));
    $unmatched = (string) ($voucher->link_status ?? '') === \App\Models\PmEzenPaymentVoucher::LINK_UNMATCHED;
    $isRemittance = (string) ($voucher->category ?? '') === \App\Models\PmEzenPaymentVoucher::CATEGORY_REMITTANCE;
    $landlords = $landlords ?? collect();
    $payee = trim((string) ($voucher->displayPayee() ?? ''));
    if ($payee === '—') {
        $payee = '';
    }
    $ref = trim((string) ($voucher->ref_no ?? ''));
    $voucherNo = trim((string) ($voucher->ezen_voucher_no ?? ''));
    $showUrl = route('property.accounting.payables.payment_vouchers.show', $voucher, false);
    $linkClass = 'block px-3 py-2 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-700/50';
@endphp
<x-property.action-menu width="w-72">
    <a href="{{ $showUrl }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-indigo-700 dark:text-indigo-300">Open {{ $voucherNo !== '' ? $voucherNo : 'voucher' }}</a>
    @if ($payee !== '')
        <a href="{{ route('property.accounting.payables.payment_vouchers', ['q' => $payee], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-slate-800 dark:text-slate-100">Vouchers for {{ $payee }}</a>
        <a href="{{ route('property.accounting.payables.accounts_payable', ['q' => $payee], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-teal-800 dark:text-teal-200">Vendor bills for {{ $payee }}</a>
        <a href="{{ route('property.vendors.directory', ['q' => $payee], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-slate-700 dark:text-slate-200">Find vendor</a>
    @endif
    @if ($ref !== '')
        <a href="{{ route('property.accounting.payables.payment_vouchers', ['q' => $ref], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-slate-700 dark:text-slate-200">Same reference {{ $ref }}</a>
    @endif
    @if ($propertyId > 0)
        <a href="{{ route('property.accounting.payables.payment_vouchers', ['property_id' => $propertyId], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-slate-800 dark:text-slate-100">{{ $property?->name ?: 'Property' }} vouchers</a>
    @endif
    @if ($unitId > 0)
        <a href="{{ route('property.accounting.payables.payment_vouchers', ['property_id' => $propertyId, 'property_unit_id' => $unitId], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-slate-700 dark:text-slate-200">Unit {{ $unitLabel !== '' ? $unitLabel : '#'.$unitId }} vouchers</a>
    @endif
    @if ($payoutId > 0)
        <a href="{{ route('property.accounting.payables.landlord_payouts', ['q' => $payoutId], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-emerald-700 dark:text-emerald-300">Open payout #{{ $payoutId }}</a>
    @endif
    @if ($entryId > 0)
        <a href="{{ route('property.accounting.entries', ['q' => $voucherNo], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-slate-700 dark:text-slate-200">Find journal</a>
    @endif
    @if ($propertyId > 0 && $landlordId > 0 && $voucher->txn_date)
        <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $propertyId, 'landlord_id' => $landlordId, 'month' => $voucher->txn_date->format('Y-m')], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-indigo-700 dark:text-indigo-300">Settlement for this month</a>
    @endif
    @if ($unmatched && $isRemittance && $landlords->isNotEmpty())
        <form method="post" action="{{ route('property.accounting.payables.payment_vouchers.match', $voucher) }}" class="block space-y-2 border-t border-slate-100 px-3 py-2 dark:border-slate-700" data-turbo-frame="property-main" data-swal-confirm="Match this voucher to the selected landlord and create a payout?">
            @csrf
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">Match payee</label>
            <select name="landlord_id" required class="w-full rounded border border-slate-300 bg-white px-1.5 py-1 text-xs dark:border-slate-600 dark:bg-gray-900">
                <option value="">Select landlord…</option>
                @foreach ($landlords as $landlord)
                    <option value="{{ $landlord->id }}">{{ $landlord->name }}</option>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="post_gl" value="1" class="rounded border-slate-300 text-indigo-600" />
                Post GL journal
            </label>
            <button type="submit" class="rounded bg-amber-700 px-2 py-1 text-[11px] font-semibold text-white hover:bg-amber-800">Match &amp; post</button>
        </form>
        <a href="{{ route('property.landlords.index') }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-amber-700 dark:text-amber-200">Add landlord</a>
    @elseif ($unmatched)
        <a href="{{ route('property.settings.register_imports') }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-amber-800 dark:text-amber-200">Re-import / match payee</a>
        <a href="{{ route('property.landlords.index') }}" data-turbo-frame="property-main" class="{{ $linkClass }} text-amber-700 dark:text-amber-200">Add landlord</a>
    @endif
    @if ($voucherNo !== '')
        <a href="{{ route('property.accounting.payables.payment_vouchers', ['q' => $voucherNo], false) }}" data-turbo-frame="property-main" class="{{ $linkClass }} border-t border-slate-100 text-slate-500 dark:border-slate-700 dark:text-slate-400">Only {{ $voucherNo }}</a>
    @endif
</x-property.action-menu>
