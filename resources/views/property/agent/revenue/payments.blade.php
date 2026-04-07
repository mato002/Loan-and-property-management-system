<x-property.workspace
    title="Payment tracking"
    subtitle="Manual receipt entry — allocates to an open invoice and updates balances."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No payment events"
    empty-hint="Record a payment for the paying tenant and choose an invoice with an open balance."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Rent flow (Step 3 of 3): Collect payment</p>
            <p class="mt-1 text-sm text-slate-600">Record the tenant payment and select the invoice with an open balance. The invoice updates automatically (Partial / Paid).</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back: Create rent bill
                </a>
                <a href="{{ route('property.revenue.receipts', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    View receipts
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                </a>
                @if (auth()->user()?->hasPmPermission('payments.settle'))
                    <a href="{{ route('property.equity.sync_status', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                        Equity sync
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

        <form method="post" action="{{ route('property.payments.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Record payment</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                    <x-property.quick-create-select
                        id="payment-tenant-select"
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
                            <option value="{{ $inv->id }}" data-tenant-id="{{ $inv->pm_tenant_id }}" @selected(old('pm_invoice_id') == $inv->id)>
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

    <x-slot name="toolbar">
        <div class="flex flex-wrap items-center gap-2">
            <form method="get" action="{{ route('property.revenue.payments') }}" class="flex flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search ref, tenant, phone..." class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-64" />
                <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                    <option value="">Status: All</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                </select>
                <select name="channel" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                    <option value="">Channel: All</option>
                    @foreach (['mpesa' => 'M-Pesa', 'equity_paybill' => 'Equity Paybill API', 'mpesa_sms_ingest' => 'SMS Forwarder', 'bank' => 'Bank', 'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque', 'mpesa_stk' => 'M-Pesa STK'] as $cv => $cl)
                        <option value="{{ $cv }}" @selected(($filters['channel'] ?? '') === $cv)>{{ $cl }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
                <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                    <option value="paid_at" @selected(($filters['sort'] ?? 'paid_at') === 'paid_at')>Sort: Received at</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Created at</option>
                    <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Sort: Amount</option>
                    <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Sort: Status</option>
                    <option value="id" @selected(($filters['sort'] ?? '') === 'id')>Sort: ID</option>
                </select>
                <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                    <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
                </select>
                <label class="text-xs text-slate-500">Per page</label>
                <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                    @foreach ([10, 30, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? request('per_page', 30)) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
                @include('property.agent.partials.export_dropdown', [
                    'csvUrl' => route('property.revenue.payments', array_merge(request()->query(), ['export' => 'csv']), false),
                    'xlsUrl' => route('property.revenue.payments', array_merge(request()->query(), ['export' => 'xls']), false),
                    'pdfUrl' => route('property.revenue.payments', array_merge(request()->query(), ['export' => 'pdf']), false),
                ])
            </form>
        </div>
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
