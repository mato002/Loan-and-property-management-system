@php
    /** @var \App\Models\PmEzenPaymentVoucher $voucher */
    $selectedPropertyId = (int) ($selectedPropertyId ?? $voucher->property_id ?? 0);
    $selectedUnitId = (int) ($voucher->property_unit_id ?? 0);
    $units = $units ?? collect();
@endphp
<form method="post" action="{{ route('property.accounting.payables.payment_vouchers.assign', $voucher, false) }}" class="flex min-w-[14rem] flex-col gap-1" data-turbo-frame="property-main">
    @csrf
    <select name="property_id" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-gray-900" aria-label="Property for {{ $voucher->ezen_voucher_no }}">
        <option value="">Unassigned</option>
        @foreach ($properties as $property)
            <option value="{{ $property->id }}" @selected($selectedPropertyId === (int) $property->id)>{{ $property->name }}</option>
        @endforeach
    </select>
    <select name="property_unit_id" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" @disabled($selectedPropertyId <= 0) class="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-gray-900 disabled:bg-slate-50" aria-label="Unit for {{ $voucher->ezen_voucher_no }}">
        <option value="">{{ $selectedPropertyId > 0 ? 'Whole property' : 'Set a property first' }}</option>
        @foreach ($units as $unit)
            <option value="{{ $unit->id }}" @selected($selectedUnitId === (int) $unit->id)>{{ $unit->label }}</option>
        @endforeach
    </select>
    @if ($selectedPropertyId <= 0 && trim((string) ($voucher->property_code ?? '')) !== '')
        <p class="text-[11px] text-slate-500">Imported code {{ $voucher->property_code }} is not a property in this workspace.</p>
    @endif
</form>
