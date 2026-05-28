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
        </div>

        @php
            $showPaymentFormByDefault = old('payment_form') !== 'advance' && $errors->hasAny(['pm_tenant_id','channel','pm_invoice_id','amount','paid_at','external_ref']);
            $showAdvanceFormByDefault = old('payment_form') === 'advance' || $errors->has('advance') || (
                old('payment_form') === 'advance' && $errors->hasAny(['pm_tenant_id','channel','amount','paid_at','external_ref','notes'])
            );
        @endphp
        <details class="space-y-3 group" @if($showPaymentFormByDefault) open @endif>
            <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>
                <span class="group-open:hidden">Record payment</span>
                <span class="hidden group-open:inline">Hide record payment form</span>
            </summary>

            <form
                method="post"
                action="{{ route('property.payments.store') }}"
                data-turbo-frame="property-main"
                class="mt-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl"
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
                            :options="collect($tenants)->map(fn($t) => ['value' => $t->id, 'label' => $t->name, 'selected' => (string) old('pm_tenant_id') === (string) $t->id])->all()"
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
                            No open invoices for the selected tenant. Create an invoice first.
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

        <details class="space-y-3 group mt-3" @if($showAdvanceFormByDefault) open @endif>
            <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                <i class="fa-solid fa-piggy-bank" aria-hidden="true"></i>
                <span class="group-open:hidden">Record advance payment</span>
                <span class="hidden group-open:inline">Hide advance payment form</span>
            </summary>

            @error('advance')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror

            @if (! ($advanceCreditsEnabled ?? false))
                <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 max-w-3xl">
                    Tenant advance credits are not enabled on this database. Run migrations for <code class="text-xs">pm_tenant_credit_*</code> tables, then retry.
                </div>
            @else
                <form
                    method="post"
                    action="{{ route('property.payments.store_advance') }}"
                    data-turbo-frame="property-main"
                    class="mt-3 rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl"
                >
                    @csrf
                    <input type="hidden" name="payment_form" value="advance" />
                    <h3 class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">Record advance payment</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        For tenants with <span class="font-medium">no open invoice</span> (or prepaying next month). Any open balance is settled first; the rest is stored as <span class="font-medium">advance credit</span> and auto-applies when future invoices are raised.
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                            <x-property.quick-create-select
                                selectId="advance-payment-tenant-select"
                                name="pm_tenant_id"
                                :required="true"
                                :options="collect($tenantsForAdvance ?? [])->map(fn ($t) => ['value' => $t->id, 'label' => $t->name, 'selected' => (string) old('pm_tenant_id') === (string) $t->id])->all()"
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
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">External ref</label>
                            <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="M-Pesa receipt, bank ref…" />
                            @error('external_ref')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes (optional)</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500" placeholder="e.g. Prepaid rent for June 2026" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save advance payment</button>
                </form>
            @endif
        </details>

        <div id="payment-reversal-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="payment-reversal-modal-title" class="text-base font-semibold text-slate-900">Payment reversal action</h3>
                        <p id="payment-reversal-modal-subtitle" class="mt-1 text-xs text-slate-600">Provide a reason to continue.</p>
                    </div>
                    <button type="button" id="payment-reversal-modal-close" class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">Close</button>
                </div>
                <div class="mt-4">
                    <label for="payment-reversal-modal-reason" class="block text-xs font-medium text-slate-700">Reason</label>
                    <textarea id="payment-reversal-modal-reason" rows="4" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Enter reason..."></textarea>
                    <p id="payment-reversal-modal-hint" class="mt-1 text-xs text-slate-500"></p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" id="payment-reversal-modal-cancel" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="button" id="payment-reversal-modal-submit" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Continue</button>
                </div>
            </div>
        </div>

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

            (function () {
                const requestForms = document.querySelectorAll('.js-reversal-request-form');
                const approveForms = document.querySelectorAll('.js-reversal-approve-form');
                const modal = document.getElementById('payment-reversal-modal');
                const modalTitle = document.getElementById('payment-reversal-modal-title');
                const modalSubtitle = document.getElementById('payment-reversal-modal-subtitle');
                const modalReason = document.getElementById('payment-reversal-modal-reason');
                const modalHint = document.getElementById('payment-reversal-modal-hint');
                const modalClose = document.getElementById('payment-reversal-modal-close');
                const modalCancel = document.getElementById('payment-reversal-modal-cancel');
                const modalSubmit = document.getElementById('payment-reversal-modal-submit');
                let activeForm = null;
                let activeMode = null;

                if (!modal || !modalTitle || !modalSubtitle || !modalReason || !modalHint || !modalClose || !modalCancel || !modalSubmit) {
                    return;
                }

                function showModal(form, mode) {
                    activeForm = form;
                    activeMode = mode;
                    const ref = form.getAttribute('data-payment-ref') || 'payment';
                    modalReason.value = '';
                    if (mode === 'request') {
                        modalTitle.textContent = `Request reversal for ${ref}`;
                        modalSubtitle.textContent = 'A clear reason is required for maker submission.';
                        modalHint.textContent = 'Minimum 5 characters.';
                    } else {
                        modalTitle.textContent = `Approve reversal for ${ref}`;
                        modalSubtitle.textContent = 'Checker note is optional but recommended.';
                        modalHint.textContent = 'Up to 500 characters.';
                    }
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    setTimeout(() => modalReason.focus(), 0);
                }

                function closeModal() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    activeForm = null;
                    activeMode = null;
                }

                requestForms.forEach((form) => {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        showModal(this, 'request');
                    });
                });

                approveForms.forEach((form) => {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        showModal(this, 'approve');
                    });
                });

                modalSubmit.addEventListener('click', function () {
                    if (!activeForm) return;
                    const reason = modalReason.value.trim();
                    if (activeMode === 'request' && reason.length < 5) {
                        window.alert('Please enter a clearer reason (at least 5 characters).');
                        modalReason.focus();
                        return;
                    }
                    const input = activeForm.querySelector('input[name="reason"]');
                    if (input) {
                        input.value = reason;
                    }
                    const formToSubmit = activeForm;
                    closeModal();
                    formToSubmit.submit();
                });

                modalClose.addEventListener('click', closeModal);
                modalCancel.addEventListener('click', closeModal);
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
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
