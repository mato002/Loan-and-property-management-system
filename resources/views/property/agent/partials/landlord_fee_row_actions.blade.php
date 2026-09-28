{{--
  Shared row actions for landlord payment fees / property commissions.
  Uses existing batchLandlordPaymentFees endpoint with a single selection.
  Expects: $row with property_id, landlord_id, period_month, status, payout_id?, payout_status?, fees_posted?
--}}
@php
    $status = strtolower((string) ($row['status'] ?? ''));
    $propertyId = (int) ($row['property_id'] ?? 0);
    $landlordId = (int) ($row['landlord_id'] ?? 0);
    $month = (string) ($row['period_month'] ?? '');
    $token = $propertyId.'|'.$landlordId;
    $payoutId = (int) ($row['payout_id'] ?? 0);
    $feesPosted = (bool) ($row['fees_posted'] ?? false);
    $batchUrl = route('property.accounting.payables.landlord_payment_fees.batch');
@endphp
<x-property.action-menu width="w-56">
    <a
        href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $propertyId, 'landlord_id' => $landlordId, 'month' => $month]) }}"
        data-turbo-frame="property-main"
        class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50"
    >Settlement detail</a>

    <a
        href="{{ route('property.accounting.payables.landlord_settlements', ['property_id' => $propertyId, 'landlord_id' => $landlordId, 'month' => $month, 'export' => 'pdf']) }}"
        data-turbo="false"
        target="_blank"
        class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50"
    >Settlement PDF</a>

    @if ($payoutId > 0)
        <a
            href="{{ route('property.accounting.payables.landlord_payouts', ['status' => $row['payout_status'] ?? '', 'q' => $payoutId]) }}"
            data-turbo-frame="property-main"
            class="block px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50"
        >Open payout #{{ $payoutId }}</a>
    @endif

    @if ($month !== '' && $propertyId > 0 && $landlordId > 0)
        @if ($status === 'accrued' || ($payoutId === 0 && ! in_array($status, ['draft', 'approved', 'posted'], true)))
            <form method="post" action="{{ $batchUrl }}" class="block" data-turbo-frame="property-main">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="selection[]" value="{{ $token }}">
                <button type="submit" name="action" value="create_draft" class="block w-full px-3 py-2 text-left text-xs font-semibold text-indigo-800 hover:bg-indigo-50 dark:text-indigo-200 dark:hover:bg-slate-700/50">Create draft payout</button>
            </form>
        @endif

        @if ($status === 'draft' && $payoutId > 0)
            <form method="post" action="{{ route('property.accounting.payables.landlord_payouts.approve', $payoutId) }}" class="block" data-turbo-frame="property-main" data-swal-confirm="Approve this landlord payout?">
                @csrf
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-sky-800 hover:bg-sky-50 dark:text-sky-200 dark:hover:bg-slate-700/50">Approve payout</button>
            </form>
        @endif

        @if (in_array($status, ['approved', 'draft'], true) && $payoutId > 0)
            <form method="post" action="{{ route('property.accounting.payables.landlord_payouts.pay', $payoutId) }}" class="block" data-turbo-frame="property-main" data-swal-confirm="Mark this payout paid (manual) and post?">
                @csrf
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-800 hover:bg-emerald-50 dark:text-emerald-200 dark:hover:bg-slate-700/50">Mark paid (manual)</button>
            </form>
            <form method="post" action="{{ route('property.accounting.payables.landlord_payouts.pay_mpesa', $payoutId) }}" class="block" data-turbo-frame="property-main" data-swal-confirm="Send this payout via M-Pesa B2C?">
                @csrf
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-teal-800 hover:bg-teal-50 dark:text-teal-200 dark:hover:bg-slate-700/50">Pay via M-Pesa B2C</button>
            </form>
        @endif

        @if (in_array($status, ['approved', 'draft'], true))
            <form method="post" action="{{ $batchUrl }}" class="block" data-turbo-frame="property-main" data-swal-confirm="Pay &amp; post fees for this property?">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="selection[]" value="{{ $token }}">
                <button type="submit" name="action" value="pay_post" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-200 dark:hover:bg-slate-700/50">Pay &amp; post (batch)</button>
            </form>
        @endif

        @if (! $feesPosted)
            <form method="post" action="{{ $batchUrl }}" class="block" data-turbo-frame="property-main">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="selection[]" value="{{ $token }}">
                <button type="submit" name="action" value="post_fees_only" class="block w-full px-3 py-2 text-left text-xs font-semibold text-violet-800 hover:bg-violet-50 dark:text-violet-200 dark:hover:bg-slate-700/50">Post fee journal only</button>
            </form>
        @endif
    @endif

    <a
        href="{{ route('property.accounting.payables.landlord_payment_fees', ['property_id' => $propertyId, 'landlord_id' => $landlordId, 'month' => (int) substr($month, 5, 2), 'year' => (int) substr($month, 0, 4)]) }}"
        data-turbo-frame="property-main"
        class="block px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50"
    >Open payment &amp; fees</a>
</x-property.action-menu>
