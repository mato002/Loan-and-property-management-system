@php
    $showInvoiceFormByDefault = $errors->hasAny(['pm_lease_id','property_unit_id','pm_tenant_id','issue_date','due_date','amount','status','description']);
@endphp
<div
    x-data="{ showInvoiceForm: @js($showInvoiceFormByDefault) }"
    class="w-full min-w-0"
    data-property-page-modals
>
<x-property.workspace
    title="Invoices & billing"
    subtitle="Rent and charges — draft or sent; allocations update status when payments post."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No invoices"
    empty-hint="Create an invoice for a unit and tenant; record payments from the Payments screen."
>
    <x-slot name="actions">
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            @click="showInvoiceForm = true"
        >
            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
            <span>Create invoice</span>
        </button>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showInvoiceForm"
            close="showInvoiceForm = false"
            name="invoice-create"
            title="Create invoice"
            max-width="3xl"
        >
        <form method="post" action="{{ route('property.invoices.store') }}" class="space-y-3" data-lease-info-url="{{ route('property.invoices.lease_info', ['lease' => 'LEASE_ID'], false) }}" data-initial-tenant-id="{{ old('pm_tenant_id') }}">
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
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Invoice type</label>
                    <select name="invoice_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="rent" @selected(old('invoice_type', 'rent') === 'rent')>Rent</option>
                        <option value="water" @selected(old('invoice_type') === 'water')>Water</option>
                        <option value="mixed" @selected(old('invoice_type') === 'mixed')>Mixed</option>
                    </select>
                </div>
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
        </x-property.modal>
    </x-slot>

    <x-slot name="secondary">
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
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.invoices', [
            'filters' => $filters,
            'tenants' => $tenants,
            'tenantsForFilter' => $tenantsForFilter,
            'units' => $units,
            'billingRangeLabel' => $billingRangeLabel ?? null,
        ])
        @if (trim((string) ($filters['q'] ?? '')) !== '' || (int) ($filters['tenant_id'] ?? 0) > 0)
            <p class="mt-2 text-xs text-slate-500">Tenant search shows all matching invoices, including carry-forward from earlier periods.</p>
        @endif
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
</div>
