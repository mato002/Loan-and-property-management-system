<x-property.workspace
    title="Invoices & billing"
    subtitle="Rent and charges — draft or sent; allocations update status when payments post."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :stats="[]"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No invoices"
    empty-hint="Create an invoice for a unit and tenant; record payments from the Payments screen."
>
    <x-slot name="above">
        @include('property.agent.partials.revenue_date_range_clear_script')

        @if (count($stats ?? []) > 0)
            <x-property.responsive.stat-card-grid :stats="$stats" dense />
        @endif

        <div class="rounded-xl sm:rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-3 md:p-5 shadow-sm">
            <p class="text-base sm:text-lg font-semibold text-slate-900">Rent flow (Step 2 of 3): Create rent bill</p>
            <p class="mt-1 text-xs sm:text-sm text-slate-600">Create an invoice for the tenant + unit. Payments will be allocated to invoices and the status updates automatically (Sent → Partial → Paid / Overdue).</p>
            <x-property.responsive.quick-action-grid class="mt-3">
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Lease
                </a>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn bg-blue-600 text-white hover:bg-blue-700">
                    Collect payment
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </x-property.responsive.quick-action-grid>
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

        <form method="post" action="{{ route('property.invoices.store') }}" x-show="showInvoiceForm" x-cloak class="property-attention-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="property-attention-title dark:text-white">Create Invoice</h3>
            <p class="property-attention-hint dark:text-slate-300">Generate the rent bill for tenant + unit; payment status will auto-update after collection.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Lease (optional)</label>
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
            <x-property.bulk-action-bar
                form-id="property-invoices-bulk-form"
                :action="route('property.revenue.invoices.bulk', absolute: false)"
                confirm="Apply bulk action to selected invoices?"
                :actions="[
                    ['value' => 'mark_sent', 'label' => 'Mark draft as sent'],
                    ['value' => 'cancel', 'label' => 'Cancel (skip paid)'],
                ]"
            />
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
                        leaseSel.dispatchEvent(new Event('change', { bubbles: true }));
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
