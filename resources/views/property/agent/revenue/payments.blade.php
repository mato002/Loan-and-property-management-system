@php
    $showPaymentFormByDefault = request('form') === 'invoice'
        || (old('payment_form') !== 'advance' && $errors->hasAny(['pm_tenant_id','channel','pm_invoice_id','amount','paid_at','external_ref']));
    $showAdvanceFormByDefault = request('form') === 'advance'
        || old('payment_form') === 'advance'
        || $errors->has('advance')
        || (old('payment_form') === 'advance' && $errors->hasAny(['pm_tenant_id', 'channel', 'amount', 'paid_at', 'external_ref', 'notes']));
@endphp
<div
    x-data="{
        showInvoicePaymentForm: @js($showPaymentFormByDefault),
        showAdvancePaymentForm: @js($showAdvanceFormByDefault),
        init() {
            window.addEventListener('property-payment-panel-open', (event) => {
                const panel = event.detail?.panel;
                if (panel === 'invoice-payment-panel') this.showInvoicePaymentForm = true;
                if (panel === 'advance-payment-panel') this.showAdvancePaymentForm = true;
            });
            window.addEventListener('property-payment-panel-close', (event) => {
                const panel = event.detail?.panel;
                if (panel === 'invoice-payment-panel') this.showInvoicePaymentForm = false;
                if (panel === 'advance-payment-panel') this.showAdvancePaymentForm = false;
            });
        },
    }"
    class="w-full min-w-0"
    data-property-page-modals
>
<x-property.workspace
    title="Payment tracking"
    subtitle="Manual receipt entry — allocates to an open invoice and updates balances."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats ?? $statsPrimary ?? []"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No payment events"
    empty-hint="Record a payment for the paying tenant and choose an invoice with an open balance."
>
    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            @click="showInvoicePaymentForm = true"
        >
            <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>
            <span>Record payment</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            @click="showAdvancePaymentForm = true"
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
            title="Record payment (against invoice)"
            max-width="3xl"
        >
            <form
                method="post"
                action="{{ route('property.payments.store') }}"
                class="space-y-3"
            >
                @csrf
                <input type="hidden" name="payment_form" value="invoice" />
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Record payment (against invoice)</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                        <x-property.quick-create-select
                            selectId="payment-tenant-select"
                            name="pm_tenant_id"
                            :required="true"
                            :searchable="true"
                            :options="\App\Support\Property\PmTenantSelectOptions::fromCollection($tenants, old('pm_tenant_id'))"
                            :create="\App\Support\Property\PmTenantQuickCreateFields::quickCreateConfig()"
                        />
                        @error('pm_tenant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            This screen posts payments against an <span class="font-medium">open invoice</span>. Only tenants with an open invoice are listed.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Channel</label>
                        <select name="channel" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            @foreach (['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('channel', 'mpesa') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Invoice (open balance)</label>
                        <select id="payment-invoice-select" name="pm_invoice_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            <option value="">Select…</option>
                            @foreach ($openInvoices as $inv)
                                @php $open = max(0, (float) $inv->amount - (float) $inv->amount_paid); @endphp
                                <option value="{{ $inv->id }}" data-tenant-id="{{ $inv->pm_tenant_id }}" @selected(old('pm_invoice_id') == $inv->id)>
                                    {{ $inv->invoice_no }}  -  {{ $inv->tenant->name }}  -  bal {{ number_format($open, 2) }}
                                </option>
                            @endforeach
                        </select>
                        @error('pm_invoice_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <p id="payment-no-invoices-hint" class="mt-1 hidden text-xs text-amber-700">
                            No open invoices for this tenant.
                            <button type="button" class="font-semibold text-emerald-700 underline" data-open-payment-panel="advance-payment-panel">Record advance payment</button>
                            instead.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                        <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Paid at</label>
                        <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('paid_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">External ref</label>
                    <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="M-Pesa receipt, bank ref…" />
                    @error('external_ref')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save payment</button>
            </form>
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

        <script>
            (function () {
                const tenantSelect = document.getElementById('payment-tenant-select');
                const invoiceSelect = document.getElementById('payment-invoice-select');
                const noInvoicesHint = document.getElementById('payment-no-invoices-hint');

                if (!tenantSelect || !invoiceSelect) return;

                function filterInvoices() {
                    const tenantId = (tenantSelect.value || '').toString();
                    let visibleCount = 0;
                    let selectedStillValid = false;

                    Array.from(invoiceSelect.options).forEach((opt, idx) => {
                        if (idx === 0) return; // "Select…"
                        const optTenantId = (opt.getAttribute('data-tenant-id') || '').toString();
                        const shouldShow = tenantId === '' || optTenantId === tenantId;
                        opt.hidden = !shouldShow;
                        if (shouldShow) visibleCount++;
                        if (shouldShow && opt.selected) selectedStillValid = true;
                    });

                    if (!selectedStillValid) {
                        invoiceSelect.value = '';
                    }

                    if (noInvoicesHint) {
                        const showHint = tenantId !== '' && visibleCount === 0;
                        noInvoicesHint.classList.toggle('hidden', !showHint);
                    }
                }

                tenantSelect.addEventListener('change', filterInvoices);
                filterInvoices(); // initial load (old input)
            })();
        </script>
    </x-slot>

    <x-slot name="secondary">
        <div class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Rent flow (Step 3 of 3): Collect payment</p>
            <p class="mt-1 text-sm text-slate-600">Record the tenant payment and select the invoice with an open balance. The invoice updates automatically (Partial / Paid).</p>
            <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <span class="font-semibold">Trust accounting controls:</span>
                Completed payments can be submitted for reversal with a reason, then approved by a different checker (maker/checker rule).
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back: Create rent bill
                </a>
                <a href="{{ route('property.revenue.receipts', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    View receipts
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.tenant_credits', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">
                    Tenant credits
                    <i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>
                </a>
                @if (auth()->user()?->hasPmPermission('payments.settle'))
                    <a href="{{ route('property.equity.matched', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                        Matched payments
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.equity.unmatched', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100">
                        Unmatched bank payments
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.equity.all', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        All equity payments
                        <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.payments', [
            'filters' => $filters,
            'perPage' => $perPage ?? null,
            'receivedRangeLabel' => $receivedRangeLabel ?? null,
        ])
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
</div>
