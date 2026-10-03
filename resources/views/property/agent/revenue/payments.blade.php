@php
    $showPaymentFormByDefault = request('form') === 'invoice'
        || (old('payment_form') !== 'advance' && $errors->hasAny(['pm_tenant_id','channel','amount','record_date','banking_date','external_ref','payer_bank','memo']));
    $showAdvanceFormByDefault = request('form') === 'advance'
        || old('payment_form') === 'advance'
        || $errors->has('advance')
        || (old('payment_form') === 'advance' && $errors->hasAny(['pm_tenant_id', 'channel', 'amount', 'paid_at', 'external_ref', 'notes']));
@endphp
<x-property.workspace
    title="Payment tracking"
    subtitle="Manual receipt entry — allocates to an open invoice and updates balances."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats ?? $statsPrimary ?? []"
    :columns="$columns"
    :column-config="$columnConfig ?? null"
    :table-min-width="$tableMinWidth ?? '1280px'"
    :table-rows="$tableRows"
    empty-title="No payment events"
    empty-hint="Record a payment for the paying tenant and choose an invoice with an open balance."
>
    <x-slot name="pageModalsAttributes"
        x-data="{!! \Illuminate\Support\Js::from([
            'showInvoicePaymentForm' => $showPaymentFormByDefault,
            'showAdvancePaymentForm' => $showAdvanceFormByDefault,
        ]) !!}"
        x-init="
            window.addEventListener('property-payment-panel-open', (event) => {
                const panel = event.detail?.panel;
                if (panel === 'invoice-payment-panel') showInvoicePaymentForm = true;
                if (panel === 'advance-payment-panel') showAdvancePaymentForm = true;
            });
            window.addEventListener('property-payment-panel-close', (event) => {
                const panel = event.detail?.panel;
                if (panel === 'invoice-payment-panel') showInvoicePaymentForm = false;
                if (panel === 'advance-payment-panel') showAdvancePaymentForm = false;
            });
        "
    ></x-slot>

    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            data-property-modal-open="showInvoicePaymentForm" @click="showInvoicePaymentForm = true"
        >
            <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>
            <span>Rent receipt</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            data-property-modal-open="showAdvancePaymentForm" @click="showAdvancePaymentForm = true"
        >
            <i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>
            <span>Record advance</span>
        </button>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showInvoicePaymentForm"
            close="showInvoicePaymentForm = false"
            name="invoice-payment-panel"
            title="Rent receipt"
            max-width="full"
        >
            @include('property.agent.revenue.partials.rent_receipt_form', [
                'properties' => $properties ?? collect(),
                'receiptTenants' => $receiptTenants ?? [],
                'receiptInvoices' => $receiptInvoices ?? [],
                'cashAccounts' => $cashAccounts ?? collect(),
            ])
        </x-property.modal>

        <x-property.modal
            show="showAdvancePaymentForm"
            close="showAdvancePaymentForm = false"
            name="advance-payment-panel"
            title="Record advance payment"
            max-width="3xl"
        >
            @error('advance')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @if (! ($advanceCreditsEnabled ?? false))
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Tenant advance credits are not enabled on this database. Run migrations for <code class="text-xs">pm_tenant_credit_*</code> tables, then retry.
                </div>
            @else
                @include('property.agent.revenue.partials.advance_payment_form_fields', [
                    'tenantsForAdvance' => $tenantsForAdvance ?? collect(),
                    'returnTo' => null,
                ])
            @endif
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.payments', get_defined_vars())
    </x-slot>

    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} payment(s)
                </p>
                <div>
                    {{ $paginator->links() }}
                </div>
            </div>
        @endisset
    </x-slot>
    <x-slot name="table_actions">
        @if (!empty($tableRows))
            <form id="property-payments-bulk-form" method="post" action="{{ route('property.revenue.payments.bulk') }}" class="flex items-center gap-2" data-swal-confirm="Apply bulk action to selected payments?">
                @csrf
                <select name="action" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                    <option value="">Bulk action</option>
                    <option value="delete">Delete (pending/failed only)</option>
                </select>
                <button type="submit" class="rounded-lg bg-red-600 text-white px-3 py-1.5 text-xs font-semibold">Apply</button>
            </form>
        @endif
    </x-slot>
</x-property.workspace>

@include('property.agent.revenue.partials.rent_receipt_form_script')
