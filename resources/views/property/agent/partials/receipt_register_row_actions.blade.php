@php
    /** @var \App\Models\PmEzenReceiptRegister $receipt */
    $paymentId = (int) ($receipt->pm_payment_id ?? 0);
    $tenantId = (int) ($receipt->pm_tenant_id ?? 0);
    $missingTenant = (string) ($receipt->link_status ?? '') === \App\Models\PmEzenReceiptRegister::LINK_NO_TENANT;
@endphp
<x-property.action-menu width="w-52">
    @if ($paymentId > 0)
        <a href="{{ route('property.payments.receipt.show', $paymentId, false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">View payment receipt</a>
        <a href="{{ route('property.revenue.payments', ['q' => 'PAY-'.$paymentId], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Open payment</a>
    @endif
    @if ($tenantId > 0)
        <a href="{{ route('property.tenants.show', $tenantId, false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Open tenant</a>
    @endif
    @if ($missingTenant)
        <a href="{{ route('property.tenants.index', ['q' => $receipt->phone ?: ($receipt->tnt_account ?? '')], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Find / add tenant</a>
        <a href="{{ route('property.settings.register_imports') }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Re-import receipts</a>
    @endif
    @if ($paymentId === 0 && ! $missingTenant)
        <a href="{{ route('property.equity.unmatched') }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-slate-700/50">Unmatched queue</a>
        <a href="{{ route('property.revenue.mpesa_inbox', ['tab' => 'verify'], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-50 dark:text-teal-200 dark:hover:bg-slate-700/50">Verify M-Pesa receipt</a>
    @endif
</x-property.action-menu>
