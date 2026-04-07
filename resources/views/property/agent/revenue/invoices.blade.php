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

        <form method="post" action="{{ route('property.invoices.store') }}" class="property-attention-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="property-attention-title dark:text-white">Create Invoice</h3>
            <p class="property-attention-hint dark:text-slate-300">Generate the rent bill for tenant + unit; payment status will auto-update after collection.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Lease (optional)</label>
                    <select id="invoice-lease" name="pm_lease_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">—</option>
                        @foreach ($leases as $l)
                            @php
                                $unitIds = $l->units->pluck('id')->implode(',');
                                $rent = (float) ($l->monthly_rent ?? 0);
                            @endphp
                            <option value="{{ $l->id }}" data-tenant-id="{{ $l->pmTenant->id }}" data-unit-ids="{{ $unitIds }}" data-rent="{{ $rent }}" @selected(old('pm_lease_id') == $l->id)>#{{ $l->id }} · {{ $l->pmTenant->name }}</option>
                        @endforeach
                    </select>
                    @error('pm_lease_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <x-property.quick-create-select
                        id="invoice-unit"
                        name="property_unit_id"
                        :required="true"
                        :options="collect($units)->map(fn($u) => ['value' => $u->id, 'label' => $u->property->name.' / '.$u->label, 'selected' => (string) old('property_unit_id') === (string) $u->id, 'attrs' => ['data-rent' => (string) ($u->rent_amount ?? 0), 'data-unit-label' => $u->label]])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Add unit',
                            'endpoint' => route('property.units.store_json'),
                            'fields' => [
                                ['name' => 'property_id', 'label' => 'Property', 'required' => true, 'span' => '2', 'type' => 'select', 'placeholder' => 'Select property', 'options' => collect($units)->map(fn($u) => ['value' => $u->property_id, 'label' => $u->property->name])->unique('value')->values()->all()],
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
                        id="invoice-tenant"
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
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Issue date</label>
                    <input id="invoice-issue-date" type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('issue_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due date</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('due_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
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
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                <input id="invoice-description" type="text" name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create invoice</button>
        </form>
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
            <input type="month" name="period" value="{{ $filters['period'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="issue_date" @selected(($filters['sort'] ?? 'issue_date') === 'issue_date')>Sort: Issued</option>
                <option value="due_date" @selected(($filters['sort'] ?? '') === 'due_date')>Sort: Due</option>
                <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Sort: Amount</option>
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
                    <option value="cancel">Cancel (skip paid)</option>
                </select>
                <button type="submit" class="rounded-lg bg-amber-600 text-white px-3 py-1.5 text-xs font-semibold">Apply</button>
            </form>
        @endif
    </x-slot>
    <script>
        (function () {
            const form = document.querySelector('form[action*="invoices"]');
            const byId = (id) => document.getElementById(id);
            const byName = (name) => form?.querySelector(`[name="${name}"]`);
            const tenantSel = byId('invoice-tenant') || byName('pm_tenant_id');
            const leaseSel = byId('invoice-lease') || byName('pm_lease_id');
            const unitSel = byId('invoice-unit') || byName('property_unit_id');
            const amountInput = byId('invoice-amount') || byName('amount');
            const issueInput = byId('invoice-issue-date') || byName('issue_date');
            const descInput = byId('invoice-description') || byName('description');
            if (!leaseSel) return;

            // Helper to robustly set select/hidden values generated by custom components
            const setFieldValue = (name, value) => {
                if (window.pmSetFieldValue) {
                    return window.pmSetFieldValue(name, value, form || document);
                }
                const el = byName(name);
                if (!el) return false;
                el.value = String(value);
                try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
                try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
                return true;
            };

            const parseUnitIds = (opt) => {
                const raw = (opt.getAttribute('data-unit-ids') || '').trim();
                if (!raw) return [];
                return raw.split(',').map((v) => parseInt(v, 10)).filter((n) => Number.isFinite(n));
            };
            const setUnit = (unitId) => {
                const unitEl = byName('property_unit_id') || unitSel;
                if (!unitEl) return false;
                if (!unitId) return false;
                const options = unitEl.querySelectorAll('option, [role="option"]');
                let matched = false;
                options.forEach((o) => {
                    if (String(o.value || o.getAttribute?.('data-value') || '') === String(unitId)) {
                        o.selected = true;
                        matched = true;
                    } else {
                        // keep other selections intact
                    }
                });
                if (!matched) {
                    // Fall back: set the raw value on the field so the component submits correctly
                    setFieldValue('property_unit_id', unitId);
                    matched = true;
                }
                return matched;
            };
            const getSelectedUnitMeta = () => {
                const unitEl = byName('property_unit_id') || unitSel;
                if (!unitEl) return { rent: null, label: '' };
                const o = unitEl.options?.[unitEl.selectedIndex];
                if (!o) return { rent: null, label: '' };
                const rent = parseFloat(o.getAttribute('data-rent') || '0') || null;
                const label = (o.getAttribute('data-unit-label') || '').trim();
                return { rent, label };
            };
            const monthLabel = () => {
                const v = (issueInput?.value || '').trim();
                if (!v) return '';
                try {
                    const d = new Date(v + 'T00:00:00');
                    const y = d.getFullYear();
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    return `${y}-${m}`;
                } catch (e) {
                    return '';
                }
            };
            const maybeSetAmount = (rentFromLease) => {
                if (amountInput && (!amountInput.value || parseFloat(amountInput.value) <= 0)) {
                    const { rent } = getSelectedUnitMeta();
                    const use = Number.isFinite(rentFromLease) && rentFromLease > 0 ? rentFromLease : (Number.isFinite(rent) ? rent : null);
                    if (use !== null) {
                        amountInput.value = String(use);
                    }
                }
            };
            const maybeSetDescription = () => {
                if (!descInput) return;
                if (descInput.value && descInput.value.trim() !== '') return;
                const { label } = getSelectedUnitMeta();
                const m = monthLabel();
                if (label && m) {
                    descInput.value = `Rent · ${label} · ${m}`;
                }
            };

            const applyLease = () => {
                const leaseId = (leaseSel.value || '').toString();
                if (!leaseId) return;
                const url = "{{ route('property.invoices.lease_info', ['lease' => 'LEASE_ID'], false) }}".replace('LEASE_ID', encodeURIComponent(leaseId));
                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.ok ? r.json() : Promise.reject())
                    .then((data) => {
                        if (!data || !data.ok) return;
                        if (data.tenant && data.tenant.id) {
                            setFieldValue('pm_tenant_id', data.tenant.id);
                        }
                        const firstUnitId = (data.unit && data.unit.id) ? data.unit.id : ((data.unit_ids || [])[0] || null);
                        if (firstUnitId) {
                            setFieldValue('property_unit_id', firstUnitId);
                        }
                        if (amountInput && (!amountInput.value || parseFloat(amountInput.value) <= 0)) {
                            const rent = parseFloat(String(data.monthly_rent || '0')) || 0;
                            if (rent > 0) amountInput.value = String(rent);
                        }
                        maybeSetDescription();
                    })
                    .catch(() => {});
            };

            const onTenantChange = () => {
                const tenantEl = byName('pm_tenant_id') || tenantSel;
                const tid = (tenantEl?.value || '').toString();
                if (!tid) return;
                let chosen = false;
                for (let i = 0; i < leaseSel.options.length; i++) {
                    const o = leaseSel.options[i];
                    if ((o.getAttribute('data-tenant-id') || '') === tid) {
                        leaseSel.selectedIndex = i;
                        chosen = true;
                        break;
                    }
                }
                if (chosen) {
                    applyLease();
                }
            };

            if (tenantSel) tenantSel.addEventListener('change', onTenantChange);
            leaseSel.addEventListener('change', applyLease);
            if (unitSel) unitSel.addEventListener('change', () => {
                maybeSetAmount(null);
                maybeSetDescription();
            });
            if (issueInput) {
                issueInput.addEventListener('change', maybeSetDescription);
            }

            // Initial autopopulate on page load:
            // 1) If a lease is already selected (e.g., navigated from lease), apply it.
            // 2) Otherwise, if no tenant set yet, try to sync from tenant -> lease.
            if ((leaseSel.value || '').toString() !== '') {
                applyLease();
            } else if (!('{{ old('pm_tenant_id') }}').toString()) {
                onTenantChange();
            }
        })();
    </script>
</x-property.workspace>
