<x-property.workspace
    title="Invoices & billing"
    subtitle="Rent and charges — draft or sent; allocations update status when payments post."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No invoices"
    empty-hint="Create an invoice for a unit and tenant; record payments from the Payments screen."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Rent flow (Step 2 of 3): Create rent bill</p>
            <p class="mt-1 text-sm text-slate-600">Create an invoice for the tenant + unit. Payments will be allocated to invoices and the status updates automatically (Sent → Partial → Paid / Overdue).</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back: Lease (allocate unit)
                </a>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Next: Collect payment
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div x-data="{ showInvoiceForm: @js($errors->hasAny(['pm_lease_id','property_unit_id','pm_tenant_id','issue_date','due_date','amount','status','description'])) }" class="space-y-3">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                @click="showInvoiceForm = !showInvoiceForm"
            >
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                <span x-text="showInvoiceForm ? 'Hide invoice form' : 'Create invoice'"></span>
            </button>

        <form method="post" action="{{ route('property.invoices.store') }}" x-show="showInvoiceForm" x-cloak class="property-attention-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl" data-lease-info-url="{{ route('property.invoices.lease_info', ['lease' => 'LEASE_ID'], false) }}" data-initial-tenant-id="{{ old('pm_tenant_id') }}">
            @csrf
            <h3 class="property-attention-title dark:text-white">Create Invoice</h3>
            <p class="property-attention-hint dark:text-slate-300">Generate the rent bill for tenant + unit; payment status will auto-update after collection.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Lease (required for rent)</label>
                    @php
                        $leaseSelectOptions = collect($leases)->map(function ($l) {
                            $unitIds = $l->units->pluck('id')->implode(',');
                            $rent = (float) ($l->monthly_rent ?? 0);
                            $leaseTenantId = $l->pmTenant?->id;
                            $leaseTenantName = $l->pmTenant?->name ?? 'Unknown tenant';
                            $unitSummary = $l->units
                                ->map(fn ($u) => trim(($u->property?->name ?? '').' / '.$u->label, ' /'))
                                ->filter()
                                ->implode(', ');
                            $contact = trim((string) ($l->pmTenant?->phone ?? ''));
                            if ($contact === '') {
                                $contact = trim((string) ($l->pmTenant?->email ?? ''));
                            }

                            return [
                                'value' => $l->id,
                                'label' => $unitSummary !== ''
                                    ? "{$leaseTenantName} · {$unitSummary}"
                                    : $leaseTenantName,
                                'search' => mb_strtolower(trim("{$leaseTenantName} {$unitSummary} {$contact}")),
                                'selected' => (string) old('pm_lease_id') === (string) $l->id,
                                'attrs' => [
                                    'data-tenant-id' => (string) ($leaseTenantId ?? ''),
                                    'data-unit-ids' => $unitIds,
                                    'data-rent' => (string) $rent,
                                ],
                            ];
                        })->all();
                    @endphp
                    <x-property.quick-create-select
                        selectId="invoice-lease"
                        name="pm_lease_id"
                        placeholder="—"
                        :searchable="true"
                        :options="$leaseSelectOptions"
                        :create="['mode' => 'none']"
                    />
                    @error('pm_lease_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <x-property.quick-create-select
                        id="invoice-unit"
                        name="property_unit_id"
                        :required="true"
                        :options="collect($units)->map(fn($u) => ['value' => $u->id, 'label' => (($u->property?->name ?? 'Unknown property').' / '.$u->label), 'selected' => (string) old('property_unit_id') === (string) $u->id, 'attrs' => ['data-rent' => (string) ($u->rent_amount ?? 0), 'data-unit-label' => $u->label]])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Add unit',
                            'endpoint' => route('property.units.store_json'),
                            'fields' => [
                                ['name' => 'property_id', 'label' => 'Property', 'required' => true, 'span' => '2', 'type' => 'select', 'placeholder' => 'Select property', 'options' => collect($units)->map(fn($u) => ['value' => $u->property_id, 'label' => ($u->property?->name ?? 'Unknown property')])->unique('value')->values()->all()],
                                ['name' => 'label', 'label' => 'Unit label', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. A1'],
                                ['name' => 'unit_type', 'label' => 'Unit type', 'required' => false, 'type' => 'select', 'options' => [['value' => 'apartment', 'label' => 'Apartment'], ['value' => 'single_room', 'label' => 'Single room'], ['value' => 'bedsitter', 'label' => 'Bedsitter'], ['value' => 'studio', 'label' => 'Studio'], ['value' => 'bungalow', 'label' => 'Bungalow'], ['value' => 'maisonette', 'label' => 'Maisonette'], ['value' => 'villa', 'label' => 'Villa'], ['value' => 'townhouse', 'label' => 'Townhouse'], ['value' => 'commercial', 'label' => 'Commercial']]],
                                ['name' => 'status', 'label' => 'Status', 'required' => false, 'type' => 'select', 'options' => [['value' => 'vacant', 'label' => 'Vacant'], ['value' => 'occupied', 'label' => 'Occupied'], ['value' => 'notice', 'label' => 'Notice']]],
                            ],
                        ]"
                    />
                    @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                    <x-property.quick-create-select
                        selectId="invoice-tenant"
                        name="pm_tenant_id"
                        :required="true"
                        :searchable="true"
                        :options="\App\Support\Property\PmTenantSelectOptions::fromCollection($tenants, old('pm_tenant_id'))"
                        :create="\App\Support\Property\PmTenantQuickCreateFields::quickCreateConfig()"
                    />
                    @error('pm_tenant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Issue date</label>
                    <input id="invoice-issue-date" type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('issue_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due date <span class="text-slate-400">(auto-fills +14 days)</span></label>
                    <input id="invoice-due-date" type="date" name="due_date" value="{{ old('due_date', now()->addDays(14)->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('due_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <script>
                    (function () {
                        const issue = document.getElementById('invoice-issue-date');
                        const due = document.getElementById('invoice-due-date');
                        if (!issue || !due) return;
                        let userTouchedDue = false;
                        due.addEventListener('change', () => { userTouchedDue = true; });
                        issue.addEventListener('change', () => {
                            if (userTouchedDue) return;
                            const d = new Date(issue.value);
                            if (Number.isNaN(d.getTime())) return;
                            d.setDate(d.getDate() + 14);
                            const yyyy = d.getFullYear();
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const dd = String(d.getDate()).padStart(2, '0');
                            due.value = `${yyyy}-${mm}-${dd}`;
                        });
                    })();
                </script>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                    <input id="invoice-amount" type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Initial status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                        <option value="sent" @selected(old('status') === 'sent')>Sent</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @include('property.agent.revenue.partials.invoice_type_field', ['selected' => old('invoice_type', 'rent')])
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Billing period (YYYY-MM, optional)</label>
                    <input type="month" name="billing_period" value="{{ old('billing_period') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <input id="invoice-description" type="text" name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes (internal, optional)</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
            </div>
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create invoice</button>
        </form>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.invoices', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search invoice, tenant, unit..." class="min-w-0 w-full sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Status: All</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                <option value="sent" @selected(($filters['status'] ?? '') === 'sent')>Sent</option>
                <option value="partial" @selected(($filters['status'] ?? '') === 'partial')>Partial</option>
                <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>Paid</option>
                <option value="overdue" @selected(($filters['status'] ?? '') === 'overdue')>Overdue</option>
                <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Cancelled</option>
            </select>
            <select name="type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Type: All</option>
                @foreach (\App\Models\PmInvoice::createTypeOptions() as $typeValue => $typeLabel)
                    <option value="{{ $typeValue }}" @selected(($filters['type'] ?? '') === $typeValue)>{{ $typeLabel }}</option>
                @endforeach
            </select>
            <select name="tenant_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 max-w-[200px]">
                <option value="0">Tenant: All</option>
                @foreach ($tenants as $t)
                    <option value="{{ $t->id }}" @selected((int)($filters['tenant_id'] ?? 0) === (int) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            <select name="unit_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 max-w-[200px]">
                <option value="0">Unit: All</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" @selected((int)($filters['unit_id'] ?? 0) === (int) $u->id)>{{ ($u->property?->name ?? 'Unknown')."/".$u->label }}</option>
                @endforeach
            </select>
            <input type="month" name="period" value="{{ $filters['period'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" placeholder="Issued from" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" placeholder="Issued to" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="date" name="due_from" value="{{ $filters['due_from'] ?? '' }}" placeholder="Due from" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="date" name="due_to" value="{{ $filters['due_to'] ?? '' }}" placeholder="Due to" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="issue_date" @selected(($filters['sort'] ?? 'issue_date') === 'issue_date')>Sort: Issued</option>
                <option value="due_date" @selected(($filters['sort'] ?? '') === 'due_date')>Sort: Due</option>
                <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Sort: Amount</option>
                <option value="balance" @selected(($filters['sort'] ?? '') === 'balance')>Sort: Balance</option>
                <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Sort: Status</option>
                <option value="invoice_no" @selected(($filters['sort'] ?? '') === 'invoice_no')>Sort: Invoice #</option>
                <option value="id" @selected(($filters['sort'] ?? '') === 'id')>Sort: ID</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
                <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.revenue.invoices', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.revenue.invoices', array_merge(request()->query(), ['export' => 'csv']), false),
                'xlsUrl' => route('property.revenue.invoices', array_merge(request()->query(), ['export' => 'xls']), false),
                'pdfUrl' => route('property.revenue.invoices', array_merge(request()->query(), ['export' => 'pdf']), false),
            ])
        </form>
    </x-slot>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} invoice(s)
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
    </x-slot>
    <x-slot name="table_actions">
        @if (!empty($tableRows))
            <form id="property-invoices-bulk-form" method="post" action="{{ route('property.revenue.invoices.bulk') }}" class="flex items-center gap-2" data-swal-confirm="Apply bulk action to selected invoices?">
                @csrf
                <select name="action" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700">
                    <option value="">Bulk action</option>
                    <option value="mark_sent">Mark draft as sent</option>
                    <option value="cancel">Cancel (skip paid)</option>
                </select>
                <button type="submit" class="rounded-lg bg-amber-600 text-white px-3 py-1.5 text-xs font-semibold">Apply</button>
            </form>
        @endif
    </x-slot>
</x-property.workspace>
