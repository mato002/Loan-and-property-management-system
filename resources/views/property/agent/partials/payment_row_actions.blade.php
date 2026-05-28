@php
    use App\Models\PmPayment;

    $receiptUrl = route('property.payments.receipt.show', ['payment' => $payment->id], false);
    $reversalStatus = (string) ($payment->reversal_status ?? '');
@endphp

<x-property.action-menu width="w-48">
    @if ($payment->status === PmPayment::STATUS_PENDING && ($canSettle ?? false))
        <form method="post" action="{{ route('property.payments.settle', $payment) }}" data-turbo-frame="property-main" class="block">
            @csrf
            @method('PATCH')
            <input type="hidden" name="decision" value="completed" />
            <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Mark complete</button>
        </form>
        <form method="post" action="{{ route('property.payments.settle', $payment) }}" data-turbo-frame="property-main" class="block">
            @csrf
            @method('PATCH')
            <input type="hidden" name="decision" value="failed" />
            <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-red-700 hover:bg-rose-50 dark:text-red-300 dark:hover:bg-slate-700/50">Mark failed</button>
        </form>
    @endif

    @if ($payment->status === PmPayment::STATUS_COMPLETED && ($canSettle ?? false))
        @if ($reversalStatus === '' || $reversalStatus === PmPayment::REVERSAL_STATUS_REJECTED)
            <form method="post" action="{{ route('property.payments.reversal.request', $payment) }}" class="block js-reversal-request-form" data-payment-ref="PAY-{{ $payment->id }}">
                @csrf
                <input type="hidden" name="reason" value="" />
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-slate-700/50">Request reversal</button>
            </form>
        @endif
        @if ($reversalStatus === PmPayment::REVERSAL_STATUS_PENDING)
            <form method="post" action="{{ route('property.payments.reversal.approve', $payment) }}" class="block js-reversal-approve-form" data-payment-ref="PAY-{{ $payment->id }}" data-swal-confirm="Approve this reversal now?">
                @csrf
                <input type="hidden" name="reason" value="" />
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-slate-700/50">Approve reversal</button>
            </form>
        @endif
    @endif

    <a href="{{ $receiptUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">View</a>
</x-property.action-menu>
