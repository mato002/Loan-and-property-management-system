<x-property.workspace
    title="Payment tracking"
    subtitle="Manual receipt entry — allocates to an open invoice and updates balances."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :stats="[]"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No payment events"
    empty-hint="Record a payment for the paying tenant and choose an invoice with an open balance."
>
    <x-slot name="above">
        @include('property.agent.partials.revenue_date_range_clear_script')

        @if (count($statsPrimary ?? []) > 0)
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Collections summary</p>
                <x-property.responsive.stat-card-grid :stats="$statsPrimary" dense />
            </div>
        @endif

        @if (count($statsTable ?? []) > 0)
            <x-property.responsive.stat-card-grid :stats="$statsTable" />
        @endif

        <div class="rounded-xl sm:rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-3 md:p-5 shadow-sm">
            <p class="text-base sm:text-lg font-semibold text-slate-900">Rent flow (Step 3 of 3): Collect payment</p>
            <p class="mt-1 text-xs sm:text-sm text-slate-600">Record the tenant payment and select the invoice with an open balance. The invoice updates automatically (Partial / Paid).</p>
            <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <span class="font-semibold">Trust accounting controls:</span>
                Completed payments can be submitted for reversal with a reason, then approved by a different checker (maker/checker rule).
            </div>
            <x-property.responsive.quick-action-grid class="mt-3">
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Invoices
                </a>
                <a href="{{ route('property.revenue.receipts', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                    Receipts
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                </a>
                @if (auth()->user()?->hasPmPermission('payments.settle'))
                    <a href="{{ route('property.equity.matched', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                        Matched payments
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.equity.unmatched', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100">
                        Unmatched
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.revenue.tenant_credits', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100">
                        Credits
                        <i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('property.equity.all', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                        All equity
                        <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                    </a>
                @endif
            </x-property.responsive.quick-action-grid>
            @include('property.agent.revenue.partials.payment_collection_ctas')
        </div>

        @php
            $showPaymentFormByDefault = request('form') === 'invoice'
                || (old('payment_form') !== 'advance' && $errors->hasAny(['pm_tenant_id','channel','pm_invoice_id','amount','paid_at','external_ref']));
        @endphp
        <details id="invoice-payment-panel" class="group mt-4 rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-gray-800/80 shadow-sm" @if($showPaymentFormByDefault) open @endif>
            <summary class="sr-only">Invoice payment form</summary>
            <div class="flex justify-end border-b border-slate-100 px-4 py-2 dark:border-slate-700">
                <button type="button" class="text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200" data-collapse-payment-panel="invoice-payment-panel">Hide form</button>
            </div>

            <form
                method="post"
                action="{{ route('property.payments.store') }}"
                data-turbo-frame="property-main"
                class="space-y-3 p-5 max-w-3xl"
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
                            :create="[
                                'mode' => 'ajax',
                                'title' => 'Create tenant',
                                'endpoint' => route('property.tenants.store_json'),
                                'fields' => [
                                    ['name' => 'name', 'label' => 'Full name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. John Tenant'],
                                    ['name' => 'phone', 'label' => 'Phone', 'required' => false, 'span' => '2', 'placeholder' => '+2547…'],
                                    ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'email', 'required' => false, 'span' => '2', 'placeholder' => 'name@example.com'],
                                ],
                            ]"
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
                                <option
                                    value="{{ $inv->id }}"
                                    data-tenant-id="{{ $inv->pm_tenant_id }}"
                                    data-open-balance="{{ number_format($open, 2, '.', '') }}"
                                    @selected(old('pm_invoice_id') == $inv->id)
                                >
                                    {{ $inv->invoice_no }} · {{ $inv->tenant->name }} · bal {{ number_format($open, 2) }}
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
                        <input id="payment-amount-input" type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
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
        </details>

        @include('property.agent.revenue.partials.advance_payment_form', [
            'tenantsForAdvance' => $tenantsForAdvance ?? collect(),
            'advanceCreditsEnabled' => $advanceCreditsEnabled ?? false,
            'hideSummary' => true,
        ])

        <script>
            (function () {
                const tenantSelect = document.getElementById('payment-tenant-select');
                const invoiceSelect = document.getElementById('payment-invoice-select');
                const amountInput = document.getElementById('payment-amount-input');
                const noInvoicesHint = document.getElementById('payment-no-invoices-hint');

                if (!tenantSelect || !invoiceSelect) return;

                const prefillAmountFromOption = (opt) => {
                    if (!amountInput || !(opt instanceof HTMLOptionElement) || !opt.value) return;
                    const balance = opt.getAttribute('data-open-balance');
                    if (balance !== null && balance !== '' && Number(balance) > 0) {
                        amountInput.value = balance;
                    }
                };

                const firstVisibleInvoiceForTenant = (tenantId) => {
                    let first = null;
                    Array.from(invoiceSelect.options).forEach((opt, idx) => {
                        if (idx === 0) return;
                        const optTenantId = (opt.getAttribute('data-tenant-id') || '').toString();
                        if (tenantId !== '' && optTenantId === tenantId && !opt.hidden) {
                            if (!first) first = opt;
                        }
                    });
                    return first;
                };

                function filterInvoices() {
                    const tenantId = (tenantSelect.value || '').toString();
                    let visibleCount = 0;
                    let selectedStillValid = false;

                    Array.from(invoiceSelect.options).forEach((opt, idx) => {
                        if (idx === 0) return;
                        const optTenantId = (opt.getAttribute('data-tenant-id') || '').toString();
                        const shouldShow = tenantId === '' || optTenantId === tenantId;
                        opt.hidden = !shouldShow;
                        if (shouldShow) visibleCount++;
                        if (shouldShow && opt.selected) selectedStillValid = true;
                    });

                    if (!selectedStillValid) {
                        const first = tenantId !== '' ? firstVisibleInvoiceForTenant(tenantId) : null;
                        if (first) {
                            invoiceSelect.value = first.value;
                            prefillAmountFromOption(first);
                        } else {
                            invoiceSelect.value = '';
                        }
                    } else {
                        prefillAmountFromOption(invoiceSelect.selectedOptions[0]);
                    }

                    if (noInvoicesHint) {
                        const showHint = tenantId !== '' && visibleCount === 0;
                        noInvoicesHint.classList.toggle('hidden', !showHint);
                    }
                }

                tenantSelect.addEventListener('change', filterInvoices);
                invoiceSelect.addEventListener('change', () => {
                    prefillAmountFromOption(invoiceSelect.selectedOptions[0]);
                });
                filterInvoices();
            })();
        </script>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.payments', [
            'filters' => $filters,
            'perPage' => $perPage ?? null,
            'receivedRangeLabel' => $receivedRangeLabel ?? null,
        ])
    </x-slot>

    <x-slot name="footer">
        @if ((int) ($filters['range_months'] ?? 1) > 0 || ! empty($filters['from'] ?? '') || ! empty($filters['to'] ?? ''))
            <p class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                <span class="font-medium">Period:</span> {{ $receivedRangeLabel ?? '' }}.
                Change dates in the bar above; use <span class="font-medium">All dates</span> for full history.
            </p>
        @endif
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
            <x-property.bulk-action-bar
                form-id="property-payments-bulk-form"
                :action="route('property.revenue.payments.bulk', absolute: false)"
                confirm="Apply bulk action to selected payments?"
                apply-label="Apply"
                :actions="[
                    ['value' => 'delete', 'label' => 'Delete (pending/failed only)'],
                ]"
            />
        @endif
    </x-slot>
</x-property.workspace>
