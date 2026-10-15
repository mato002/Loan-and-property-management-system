@php
    $vendorWorkspaceRoute = request()->routeIs('property.vendors.bills.link_voucher_form');
    $billsRoute = $vendorWorkspaceRoute ? 'property.vendors.bills' : 'property.accounting.payables.accounts_payable';
    $linkVoucherRoute = $vendorWorkspaceRoute ? 'property.vendors.bills.link_voucher' : 'property.accounting.payables.accounts_payable.link_voucher';
@endphp
<x-property.crud-shell
    :in-property-form-modal="$inPropertyFormModal ?? false"
    title="Link existing payment voucher"
    subtitle="Associate an already-recorded vendor payment with this bill. This does not post the expense again."
    :back-route="$billsRoute"
    :columns="[]"
>
    <form method="post" action="{{ route($linkVoucherRoute, $bill, absolute: false) }}" class="max-w-3xl space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-property-form-modal-alerts="1">
        @csrf
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-900">
            Bill <strong>{{ $bill->ezen_bill_no }}</strong> · {{ $bill->vendor_name }} · Remaining due <strong>KES {{ number_format((float) $bill->amount_due, 2) }}</strong>
        </div>
        <label class="block text-sm font-medium text-slate-700">Matching payment voucher <span class="text-rose-600">*</span>
            <select name="voucher_id" required class="mt-1 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                <option value="">Select an existing voucher</option>
                @foreach ($vouchers as $voucher)
                    <option value="{{ $voucher->id }}" @selected((string) old('voucher_id') === (string) $voucher->id)>
                        {{ $voucher->ezen_voucher_no }} · {{ $voucher->txn_date?->format('Y-m-d') ?? '—' }} · {{ $voucher->displayPayee() }} · KES {{ number_format((float) $voucher->amount, 2) }} · {{ $voucher->property?->name ?? 'Property not set' }}
                    </option>
                @endforeach
            </select>
            @error('voucher_id')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        @if ($vouchers->isEmpty())
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">No unlinked expense vouchers were found for this vendor with an amount up to this bill’s remaining balance. Check that the voucher payee matches “{{ $bill->vendor_name }}” and that it is not already linked.</p>
        @else
            <p class="text-xs text-slate-500">Only unlinked expense vouchers for this vendor and property are listed. You can link partial payments one at a time.</p>
        @endif
        <div class="flex justify-end gap-2">
            <a href="{{ route($billsRoute, absolute: false) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</a>
            <button type="submit" @disabled($vouchers->isEmpty()) class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Link voucher</button>
        </div>
    </form>
</x-property.crud-shell>
