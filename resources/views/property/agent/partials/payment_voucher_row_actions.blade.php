@php
    /** @var \App\Models\PmEzenPaymentVoucher $voucher */
    $payoutId = (int) ($voucher->pm_landlord_payout_id ?? 0);
    $entryId = (int) ($voucher->pm_accounting_entry_id ?? 0);
    $landlordId = (int) ($voucher->pm_landlord_id ?? 0);
    $propertyId = (int) ($voucher->property_id ?? 0);
    $unmatched = (string) ($voucher->link_status ?? '') === \App\Models\PmEzenPaymentVoucher::LINK_UNMATCHED;
    $isRemittance = (string) ($voucher->category ?? '') === \App\Models\PmEzenPaymentVoucher::CATEGORY_REMITTANCE;
    $landlords = $landlords ?? collect();
@endphp
<x-property.action-menu width="w-64">
    @if ($payoutId > 0)
        <a href="{{ route('property.accounting.payables.landlord_payouts', ['q' => $payoutId], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Open payout #{{ $payoutId }}</a>
    @endif
    @if ($entryId > 0)
        <a href="{{ route('property.accounting.entries', ['q' => $voucher->ezen_voucher_no], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Find journal</a>
    @endif
    @if ($propertyId > 0 && $landlordId > 0 && $voucher->txn_date)
        <a href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $propertyId, 'landlord_id' => $landlordId, 'month' => $voucher->txn_date->format('Y-m')], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Settlement</a>
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
        <a href="{{ route('property.landlords.index') }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Add landlord</a>
    @elseif ($unmatched)
        <a href="{{ route('property.settings.register_imports') }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Re-import / match payee</a>
        <a href="{{ route('property.landlords.index') }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Add landlord</a>
    @endif
    <a href="{{ route('property.accounting.payables.payment_vouchers', ['q' => $voucher->ezen_voucher_no], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50">Filter this voucher</a>
</x-property.action-menu>
