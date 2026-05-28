<x-property.workspace
    title="Edit lease"
    subtitle="Update term dates, rent, linked units, and status for an existing lease."
    back-route="property.tenants.leases"
    :stats="[
        ['label' => 'Lease', 'value' => '#'.$lease->id, 'hint' => ucfirst($lease->status)],
        ['label' => 'Tenant', 'value' => $lease->pmTenant->name, 'hint' => 'Current'],
    ]"
    :columns="[]"
>
    @php
        $showOpeningArrearsSection = $errors->hasAny([
            'opening_arrears_items',
            'opening_arrears_items.*.type',
            'opening_arrears_items.*.label',
            'opening_arrears_items.*.period',
            'opening_arrears_items.*.amount',
            'opening_arrears_amount',
            'opening_arrears_as_of',
            'opening_arrears_notes',
            'opening_arrears', 'opening_arrears.*',
            'opening_rent_arrears', 'opening_arrears_manual_total', 'opening_arrears_as_of_date', 'opening_arrears_note',
        ])
            || count((array) old('opening_arrears', (array) ($lease->opening_arrears ?? []))) > 0
            || (float) ($carryForwardTotal ?? 0) > 0;
        $leaseEditArrearsItems = array_values((array) old('opening_arrears_items', (array) ($lease->pmTenant->opening_arrears_items ?? [])));
        $selectedPropertyId = (string) old('property_id', optional($lease->units->first())->property_id);
        $additionalDeposits = old('additional_deposits', $lease->additional_deposits ?? []);
        $openingArrearsRows = old('opening_arrears', ($lease->opening_arrears ?? []));
        $utilityOpeningArrearsRows = collect((array) $openingArrearsRows)
            ->filter(fn ($row) => is_array($row) && ! in_array((string) ($row['charge_type'] ?? ''), ['rent_arrears', 'deposit_arrears'], true))
            ->values()
            ->all();
        $existingRentArrearsRow = collect((array) ($lease->opening_arrears ?? []))->firstWhere('charge_type', 'rent_arrears') ?: [];
        $existingRentArrears = $existingRentArrearsRow['amount'] ?? null;
        $existingRentArrearsPeriod = $existingRentArrearsRow['period'] ?? null;
        $existingRentArrearsDetails = $existingRentArrearsRow['specific_charge'] ?? null;
        $openingDepositArrearsRows = old('opening_deposit_arrears', []);
        $openOptionalFieldsModal = $errors->hasAny([
            'utility_expenses', 'utility_expense_type', 'utility_expense_rate',
            'additional_deposits', 'additional_deposits.*.label', 'additional_deposits.*.amount',
            'terms_summary',
        ]);
        $openOpeningArrearsModal = $showOpeningArrearsSection || $errors->hasAny([
            'opening_arrears', 'opening_arrears.*',
            'opening_rent_arrears', 'opening_rent_arrears_period', 'opening_rent_arrears_details',
            'opening_deposit_arrears', 'opening_deposit_arrears.*',
            'opening_arrears_manual_total', 'opening_arrears_as_of_date', 'opening_arrears_note',
        ]);
    @endphp
    <form
        method="post"
        action="{{ route('property.leases.update', $lease) }}"
        data-turbo-frame="property-main"
        id="lease-edit-form-wrapper"
        x-data="{
            showOptionalFieldsModal: @js($openOptionalFieldsModal),
            showOpeningArrearsModal: @js($openOpeningArrearsModal),
            showArrearsLineModal: false,
            showChargeTypeModal: false,
            showOpeningArrearsSection: @js($showOpeningArrearsSection),
            arrearsItems: @js($leaseEditArrearsItems),
            arrearsTypeLabels: @js($openingArrearsTypeOptions ?? []),
            addArrearsItem() {
                this.arrearsItems.push({ type: 'water', label: '', period: '', amount: '', reference: '' });
            },
            removeArrearsItem(index) {
                this.arrearsItems.splice(index, 1);
            },
            setDefaultLabel(item) {
                if ((item.label ?? '').trim() !== '') return;
                item.label = this.arrearsTypeLabels[item.type] ?? '';
            }
        }"
        class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl"
    >
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Lease details</h3>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                @php
                    $tenantSelectOptions = collect($tenants)->map(function ($t) use ($lease) {
                        return [
                            'value' => $t->id,
                            'label' => $t->name,
                            'selected' => (string) old('pm_tenant_id', $lease->pm_tenant_id) === (string) $t->id,
                        ];
                    })->all();
                @endphp
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                <x-property.quick-create-select
                    name="pm_tenant_id"
                    :required="true"
                    :options="$tenantSelectOptions"
                    :create="[
                        'mode' => 'ajax',
                        'title' => 'Create tenant',
                        'endpoint' => route('property.tenants.store_json'),
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. John Tenant'],
                            ['name' => 'phone', 'label' => 'Phone', 'required' => false, 'span' => '2', 'placeholder' => '+2547…'],
                            ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'email', 'required' => false, 'span' => '2', 'placeholder' => 'name@example.com'],
                            ['name' => 'national_id', 'label' => 'National ID / reference', 'required' => false, 'span' => '2', 'placeholder' => 'e.g. 12345678'],
                            ['name' => 'risk_level', 'label' => 'Risk level', 'type' => 'select', 'required' => false, 'options' => [
                                ['value' => 'normal', 'label' => 'Normal'],
                                ['value' => 'medium', 'label' => 'Medium'],
                                ['value' => 'high', 'label' => 'High'],
                            ]],
                            ['name' => 'create_portal_login', 'label' => 'Create portal login', 'type' => 'select', 'required' => false, 'options' => [
                                ['value' => '0', 'label' => 'No'],
                                ['value' => '1', 'label' => 'Yes'],
                            ]],
                            ['name' => 'notes', 'label' => 'Notes', 'required' => false, 'span' => '2', 'placeholder' => 'Optional notes'],
                        ],
                    ]"
                />
                @error('pm_tenant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Start</label>
                <input type="date" name="start_date" value="{{ old('start_date', $lease->start_date->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('start_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">End</label>
                <input type="date" name="end_date" value="{{ old('end_date', optional($lease->end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('end_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500">Optional for open-ended leases.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly rent</label>
                <input id="lease-monthly-rent" type="number" name="monthly_rent" value="{{ old('monthly_rent', $lease->monthly_rent) }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <p class="mt-1 text-xs text-slate-500">Auto-fills from selected unit rent.</p>
                @error('monthly_rent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent deposit</label>
                <input id="lease-rent-deposit" type="number" name="deposit_amount" value="{{ old('deposit_amount', $lease->deposit_amount) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <p id="rent-deposit-meta" class="mt-1 text-xs text-slate-500">—</p>
                @error('deposit_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="draft" @selected(old('status', $lease->status) === 'draft')>Draft</option>
                    <option value="active" @selected(old('status', $lease->status) === 'active')>Active</option>
                    <option value="expired" @selected(old('status', $lease->status) === 'expired')>Expired</option>
                    <option value="terminated" @selected(old('status', $lease->status) === 'terminated')>Terminated</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <button type="button" id="open-optional-fields-edit-modal" class="inline-flex items-center gap-2 rounded-lg border border-emerald-700 bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:border-emerald-300 disabled:bg-emerald-400/90 disabled:text-white/95 disabled:shadow-none dark:border-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500 dark:disabled:bg-emerald-800/80" disabled>
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                    Utilities, deposits &amp; terms
                </button>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (with vacant/linked units)</label>
            <select id="lease-property-select" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">All properties</option>
                @foreach (($vacantProperties ?? []) as $property)
                    <option value="{{ $property->id }}" @selected($selectedPropertyId !== '' && (string) $property->id === (string) $selectedPropertyId)>{{ $property->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
            <select id="lease-unit-select" name="property_unit_ids[]" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select unit...</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" data-property-id="{{ $u->property_id }}" data-rent="{{ (float) ($u->rent_amount ?? 0) }}" @selected((string) collect(old('property_unit_ids', $lease->units->pluck('id')->all()))->first() === (string) $u->id)>{{ $u->property->name }} / {{ $u->label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">A tenant can only be assigned one unit.</p>
            @error('property_unit_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('property_unit_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        @php
            $showCarryForwardInline = $openOpeningArrearsModal
                || (float) ($carryForwardTotal ?? 0) > 0
                || count((array) $openingArrearsRows) > 0;
        @endphp
        <details class="rounded-xl border border-amber-200 bg-amber-50/40 dark:border-amber-700/40 dark:bg-amber-900/10 p-3 space-y-3" @if($showCarryForwardInline) open @endif>
            <summary class="cursor-pointer list-none flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-xs font-semibold text-amber-900 dark:text-amber-200">Carry-forward debt</p>
                    <p class="text-sm font-bold text-amber-950 dark:text-amber-100">
                        {{ \App\Services\Property\PropertyMoney::kes((float) ($carryForwardTotal ?? 0)) }}
                        <span class="text-xs font-normal text-amber-800 dark:text-amber-300">saved on this lease</span>
                    </p>
                </div>
                <span class="text-xs font-medium text-amber-800 dark:text-amber-300">Expand / collapse</span>
            </summary>
            @include('property.agent.partials.lease_carry_forward_inline', [
                'lease' => $lease,
                'openingArrearsRows' => $utilityOpeningArrearsRows,
                'existingRentArrears' => $existingRentArrears,
                'existingRentArrearsPeriod' => $existingRentArrearsPeriod,
                'existingRentArrearsDetails' => $existingRentArrearsDetails,
                'rowsId' => 'opening-arrears-inline-rows',
                'addLineButtonId' => 'open-arrears-line-inline-edit',
            ])
        </details>

        @include('property.agent.partials.lease_edit_modals', [
            'formId' => 'lease-edit-form-wrapper',
            'lease' => $lease,
            'additionalDeposits' => $additionalDeposits,
            'openingArrearsRows' => $openingArrearsRows,
            'existingRentArrears' => $existingRentArrears,
            'existingRentArrearsPeriod' => $existingRentArrearsPeriod,
            'existingRentArrearsDetails' => $existingRentArrearsDetails,
        ])

        <div class="flex gap-2">
            <button type="submit" id="lease-edit-form-submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">Save changes</button>
            <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
    @php
        $_leaseUtilityRowsForJs = old('utility_expenses');
        if (! is_array($_leaseUtilityRowsForJs) || $_leaseUtilityRowsForJs === []) {
            $_leaseUtilityRowsForJs = $lease->utility_expenses ?? [];
        }
    @endphp
    <script>
        (function () {
            const leaseUtilityExpenseFormOld = @json(collect($_leaseUtilityRowsForJs)->values()->all());
            const openingDepositArrearsOld = @json((array) ($openingDepositArrearsRows ?? []));
            const utilityTemplatesByProperty = @json($utilityChargeTemplatesByProperty ?? []);
            const depositDefinitionsByProperty = @json($depositDefinitionsByProperty ?? []);
            const canCustomDepositOverride = @js((bool) (((auth()->user() && auth()->user()->is_super_admin) ? true : false) || ((auth()->user() && auth()->user()->hasPmPermission('settings.manage')) ? true : false) || (\App\Models\PropertyPortalSetting::getValue('lease_deposit_allow_custom_types', '0') === '1')));
            const propertySelect = document.getElementById('lease-property-select');
            const unitSelect = document.getElementById('lease-unit-select');
            const monthlyRentInput = document.getElementById('lease-monthly-rent');
            const additionalDepositsWrap = document.getElementById('additional-deposits-rows');
            const rentDepositInput = document.getElementById('lease-rent-deposit');
            const rentDepositMeta = document.getElementById('rent-deposit-meta');
            const leaseFormId = 'lease-edit-form-wrapper';
            const openOptionalFieldsEditModalButton = document.getElementById('open-optional-fields-edit-modal');
            const utilityDefaultsTbody = document.getElementById('utility-defaults-tbody');
            const utilityDefaultsEmptyHint = document.getElementById('utility-defaults-empty');
            const openingArrearsEditWrap = document.getElementById('opening-arrears-edit-wrap');
            const openingArrearsEditRows = document.getElementById('opening-arrears-edit-rows');
            const openingArrearsInlineRows = document.getElementById('opening-arrears-inline-rows');
            const openArrearsLineInlineEditButton = document.getElementById('open-arrears-line-inline-edit');
            const openArrearsLineModalEditButton = document.getElementById('open-arrears-line-modal-edit');
            const cancelArrearsLineModalEditButton = document.getElementById('cancel-arrears-line-modal-edit');
            const saveArrearsLineModalEditButton = document.getElementById('save-arrears-line-modal-edit');
            const arrearsLineEditChargeType = document.getElementById('arrears-line-edit-charge-type');
            const arrearsLineEditSpecificCharge = document.getElementById('arrears-line-edit-specific-charge');
            const arrearsLineEditPeriod = document.getElementById('arrears-line-edit-period');
            const arrearsLineEditAmount = document.getElementById('arrears-line-edit-amount');
            const toggleOpeningArrearsEditButton = document.getElementById('toggle-opening-arrears-edit');
            const openingDepositArrearsEditRows = document.getElementById('opening-deposit-arrears-edit-rows');
            const openingDepositArrearsEditEmpty = document.getElementById('opening-deposit-arrears-edit-empty');
            const cancelChargeTypeModalButton = document.getElementById('cancel-charge-type-modal');
            const saveChargeTypeModalButton = document.getElementById('save-charge-type-modal');
            const newChargeTypeInput = document.getElementById('new-charge-type-input');
            const chargeTypeModalError = document.getElementById('charge-type-modal-error');
            const leaseEditForm = document.getElementById('lease-edit-form-wrapper');
            if (leaseEditForm && leaseEditForm.dataset.leaseValidationBound !== '1') {
                leaseEditForm.dataset.leaseValidationBound = '1';
                const revealFieldContainer = (field) => {
                    window.revealLeaseField?.(field, leaseFormId);
                };
                const fieldLabel = (field) => {
                    if (!(field instanceof HTMLElement)) return 'Required field';
                    const label = field.closest('div')?.querySelector('label')?.textContent?.trim();
                    if (label) return label;
                    if (field.name === 'pm_tenant_id') return 'Tenant';
                    return field.name || 'Required field';
                };
                leaseEditForm.addEventListener('invalid', (event) => {
                    event.preventDefault();
                    const field = event.target;
                    revealFieldContainer(field);
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'warning',
                            title: 'Cannot save lease',
                            text: 'Please complete: ' + fieldLabel(field),
                            confirmButtonColor: '#2563eb',
                        });
                    }
                    if (field instanceof HTMLElement && typeof field.focus === 'function') {
                        field.focus();
                    }
                }, true);
                leaseEditForm.addEventListener('submit', (event) => {
                    window.syncLeaseCarryForwardToForm?.(leaseFormId);
                    if (!leaseEditForm.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                        const firstInvalid = leaseEditForm.querySelector(':invalid');
                        if (firstInvalid instanceof HTMLElement) {
                            revealFieldContainer(firstInvalid);
                            firstInvalid.focus();
                        }
                    }
                });
            }

            if (!propertySelect || !unitSelect || !monthlyRentInput) return;

            const normalizeTypeValue = (value) => (value || '').toString().trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            const toMoney = (value) => {
                const num = Number(value);
                return Number.isFinite(num) ? num.toFixed(2) : '';
            };
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const getSelectedUnitOption = () => {
                if (unitSelect.selectedIndex < 0) return null;
                const selected = unitSelect.options[unitSelect.selectedIndex];
                return selected && selected.value !== '' ? selected : null;
            };
            const getCurrentPropertyId = () => {
                const selectedUnit = getSelectedUnitOption();
                if (selectedUnit) {
                    return (selectedUnit.getAttribute('data-property-id') || '').toString();
                }
                return (propertySelect.value || '').toString();
            };
            const getPropertyTemplates = (propertyId) => {
                if (!propertyId) return [];
                const rows = utilityTemplatesByProperty[String(propertyId)];
                return Array.isArray(rows) ? rows : [];
            };
            const getEffectivePropertyTemplates = (propertyId) => {
                const rows = getPropertyTemplates(propertyId);
                const selectedUnit = getSelectedUnitOption();
                const unitId = selectedUnit ? Number(selectedUnit.value || 0) : 0;
                if (rows.length === 0) return [];
                const map = new Map();
                rows
                    .filter((r) => !r?.property_unit_id)
                    .forEach((r) => map.set(normalizeTypeValue(r?.charge_type || r?.label || ''), r));
                rows
                    .filter((r) => Number(r?.property_unit_id || 0) === unitId)
                    .forEach((r) => map.set(normalizeTypeValue(r?.charge_type || r?.label || ''), r));
                return Array.from(map.values());
            };
            const getEffectiveDepositDefinitions = () => {
                const propertyId = getCurrentPropertyId();
                if (!propertyId) return [];
                const selectedUnit = getSelectedUnitOption();
                const unitId = selectedUnit ? Number(selectedUnit.value || 0) : 0;
                const rows = Array.isArray(depositDefinitionsByProperty[String(propertyId)]) ? depositDefinitionsByProperty[String(propertyId)] : [];
                const map = new Map();
                rows
                    .filter((r) => !r.property_unit_id || Number(r.property_unit_id) === unitId)
                    .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))
                    .forEach((r) => map.set(String(r.deposit_key || ''), r));
                return Array.from(map.values());
            };
            const computeDefinitionAmount = (definition) => {
                const monthlyRent = Number(monthlyRentInput?.value || 0);
                const val = Number(definition?.amount_value || 0);
                if (!Number.isFinite(val) || val <= 0) return 0;
                return String(definition?.amount_mode || '') === 'percent_rent' ? (monthlyRent * val) / 100 : val;
            };
            const additionalLabelOptionsHtml = (definitions, selected = '') => {
                const optionalDefs = definitions.filter((d) => String(d.deposit_key || '') !== 'rent_deposit');
                const options = optionalDefs.map((d) => {
                    const label = String(d.label || d.deposit_key || 'Deposit');
                    const isSel = selected !== '' && selected === label ? 'selected' : '';
                    return `<option value="${label.replace(/"/g, '&quot;')}" ${isSel}>${label}</option>`;
                });
                if (selected && !optionalDefs.some((d) => String(d.label || '') === selected)) {
                    options.unshift(`<option value="${selected.replace(/"/g, '&quot;')}" selected>${selected}</option>`);
                }
                if (options.length === 0) options.push('<option value="">No configured deposit types</option>');
                return options.join('');
            };
            const reindexDepositRows = () => {
                additionalDepositsWrap?.querySelectorAll('.additional-deposit-row').forEach((row, idx) => {
                    const labelSelect = row.querySelector('.additional-deposit-label');
                    const amountInput = row.querySelector('input[type="number"]');
                    if (labelSelect) labelSelect.setAttribute('name', `additional_deposits[${idx}][label]`);
                    if (amountInput) amountInput.setAttribute('name', `additional_deposits[${idx}][amount]`);
                });
            };
            const renderDepositMeta = (def) => {
                if (!def) return '—';
                const mode = String(def.amount_mode || 'fixed');
                const value = Number(def.amount_value || 0);
                const formula = mode === 'percent_rent' ? `${value}% of rent` : `Fixed ${toMoney(value)}`;
                return `${def.is_required ? 'Required' : 'Optional'} | ${def.is_refundable ? 'Refundable' : 'Non-refundable'} | ${formula}`;
            };
            const styleDepositRow = (row, def) => {
                row.classList.remove('bg-amber-50/40', 'ring-1', 'ring-amber-200');
                if (def && def.is_required) {
                    row.classList.add('bg-amber-50/40', 'ring-1', 'ring-amber-200');
                }
            };
            const getTemplateTypeRows = (propertyId) => {
                const rows = getEffectivePropertyTemplates(propertyId);
                return rows
                    .map((row) => {
                        const type = normalizeTypeValue(row?.charge_type || row?.label || '');
                        if (!type) return null;
                        const label = (row?.label || '').toString().trim() || type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                        const rate = Number(row?.rate_per_unit ?? 0);
                        const fixed = Number(row?.fixed_charge ?? 0);
                        let mode = 'fixed';
                        let amount = 0;
                        if (rate > 0 && fixed <= 0) {
                            mode = 'rate_per_unit';
                            amount = rate;
                        } else if (fixed > 0) {
                            mode = 'fixed';
                            amount = fixed;
                        } else if (rate > 0) {
                            mode = 'rate_per_unit';
                            amount = rate;
                        }
                        let inputMode = 'both';
                        if (rate > 0 && fixed <= 0) inputMode = 'rate_only';
                        else if (fixed > 0 && rate <= 0) inputMode = 'fixed_only';
                        else if (rate > 0 && fixed > 0) inputMode = 'mixed';
                        return { value: type, label, amount, mode, tplRate: rate, tplFixed: fixed, inputMode };
                    })
                    .filter((row, idx, arr) => row && arr.findIndex((x) => x.value === row.value) === idx);
            };
            const buildTypeOptionsHtml = (rows, includeNone = false) => {
                const chunks = [];
                if (includeNone) chunks.push('<option value="">None</option>');
                rows.forEach((row) => {
                    chunks.push(`<option value="${row.value}" data-default-amount="${toMoney(row.amount)}" data-default-mode="${row.mode || 'fixed'}">${row.label}</option>`);
                });
                return chunks.join('');
            };
            const syncOptionalSectionState = () => {
                const selectedUnit = getSelectedUnitOption();
                const enabled = !!selectedUnit;
                if (openOptionalFieldsEditModalButton) {
                    openOptionalFieldsEditModalButton.disabled = !enabled;
                }
            };
            const getActiveUtilityTemplateRows = () => {
                const propertyId = getCurrentPropertyId();
                const templateRows = getTemplateTypeRows(propertyId);
                const fallbackRows = [
                    { value: 'water', label: 'Water', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'electricity', label: 'Electricity', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'service', label: 'Service', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'garbage', label: 'Garbage', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'other', label: 'Other', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                ];
                return templateRows.length > 0 ? templateRows : fallbackRows;
            };
            const pickSavedAmountForType = (typeValue, tpl) => {
                const savedRow = (leaseUtilityExpenseFormOld || []).find((r) => normalizeTypeValue(String(r?.type || '')) === typeValue);
                if (savedRow) {
                    const r = savedRow.rate_per_unit;
                    const f = savedRow.fixed_charge ?? savedRow.fixed;
                    if (r !== undefined && r !== null && r !== '' && Number(r) >= 0) return { rate: Number(r), fixed: f !== undefined && f !== null && f !== '' ? Number(f) : null };
                    if (f !== undefined && f !== null && f !== '' && Number(f) >= 0) return { rate: null, fixed: Number(f) };
                    const a = savedRow.amount;
                    if (a !== undefined && a !== null && a !== '' && Number(a) > 0) {
                        const amt = Number(a);
                        if (tpl.inputMode === 'rate_only') return { rate: amt, fixed: null };
                        if (tpl.inputMode === 'fixed_only') return { rate: null, fixed: amt };
                        if (tpl.inputMode === 'mixed') {
                            return Number(tpl.tplRate) >= Number(tpl.tplFixed) ? { rate: amt, fixed: null } : { rate: null, fixed: amt };
                        }
                        return { rate: null, fixed: amt };
                    }
                }
                return null;
            };
            const renderUtilityDefaultsTable = () => {
                if (!utilityDefaultsTbody) return;
                utilityDefaultsTbody.innerHTML = '';
                const selectedUnit = getSelectedUnitOption();
                if (!selectedUnit) {
                    if (utilityDefaultsEmptyHint) {
                        utilityDefaultsEmptyHint.classList.remove('hidden');
                        utilityDefaultsEmptyHint.textContent = 'Select a unit first.';
                    }
                    return;
                }
                if (utilityDefaultsEmptyHint) utilityDefaultsEmptyHint.classList.add('hidden');
                const rows = getActiveUtilityTemplateRows();
                rows.forEach((tpl, idx) => {
                    const saved = pickSavedAmountForType(tpl.value, tpl);
                    let rateVal = '';
                    let fixedVal = '';
                    if (saved && saved.rate !== null && saved.rate !== undefined && Number(saved.rate) >= 0) {
                        rateVal = Number(saved.rate) > 0 ? toMoney(saved.rate) : '';
                    } else if (tpl.tplRate > 0) {
                        rateVal = toMoney(tpl.tplRate);
                    }
                    if (saved && saved.fixed !== null && saved.fixed !== undefined && Number(saved.fixed) >= 0) {
                        fixedVal = Number(saved.fixed) > 0 ? toMoney(saved.fixed) : '';
                    } else if (tpl.tplFixed > 0) {
                        fixedVal = toMoney(tpl.tplFixed);
                    }
                    const rateDisabled = tpl.inputMode === 'fixed_only';
                    const fixedDisabled = tpl.inputMode === 'rate_only';
                    const ratePost = rateDisabled ? '' : rateVal;
                    const fixedPost = fixedDisabled ? '' : fixedVal;
                    const rateShow = rateDisabled ? '—' : (rateVal !== '' ? rateVal : '—');
                    const fixedShow = fixedDisabled ? '—' : (fixedVal !== '' ? fixedVal : '—');
                    const escAttr = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-slate-100 dark:border-slate-700';
                    tr.innerHTML = `
                        <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-200">${tpl.label.replace(/</g, '&lt;')}</td>
                        <td class="px-3 py-2">
                            <input type="hidden" name="utility_expenses[${idx}][type]" value="${escAttr(tpl.value)}" />
                            <input type="hidden" name="utility_expenses[${idx}][rate_per_unit]" value="${escAttr(ratePost)}" />
                            <span class="text-sm tabular-nums text-slate-700 dark:text-slate-200">${rateShow === '—' ? '—' : escAttr(rateShow)}</span>
                        </td>
                        <td class="px-3 py-2">
                            <input type="hidden" name="utility_expenses[${idx}][fixed_charge]" value="${escAttr(fixedPost)}" />
                            <span class="text-sm tabular-nums text-slate-700 dark:text-slate-200">${fixedShow === '—' ? '—' : escAttr(fixedShow)}</span>
                        </td>
                    `;
                    utilityDefaultsTbody.appendChild(tr);
                });
            };
            const refreshUtilityTypeSources = () => {
                renderUtilityDefaultsTable();
            };
            const renderOpeningDepositArrearsRows = () => {
                if (!openingDepositArrearsEditRows) return;
                const defs = getEffectiveDepositDefinitions();
                const rows = defs
                    .filter((d) => String(d.deposit_key || '').trim() !== '')
                    .map((d) => ({
                        key: String(d.deposit_key || '').trim(),
                        label: String(d.label || d.deposit_key || 'Deposit').trim(),
                    }));
                openingDepositArrearsEditRows.innerHTML = '';
                if (rows.length === 0) {
                    openingDepositArrearsEditEmpty?.classList.remove('hidden');
                    return;
                }
                openingDepositArrearsEditEmpty?.classList.add('hidden');
                rows.forEach((row) => {
                    const currentValue = openingDepositArrearsOld[row.key] ?? '';
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-amber-100';
                    tr.innerHTML = `
                        <td class="px-3 py-2 text-slate-700">${escapeHtml(row.label)}</td>
                        <td class="px-3 py-2">
                            <input type="number" name="opening_deposit_arrears[${escapeHtml(row.key)}]" value="${escapeHtml(currentValue)}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                        </td>
                    `;
                    openingDepositArrearsEditRows.appendChild(tr);
                });
            };

            const filterUnits = () => {
                const propertyId = (propertySelect.value || '').toString();
                let visibleCount = 0;
                Array.from(unitSelect.options).forEach((opt) => {
                    const optPropertyId = (opt.getAttribute('data-property-id') || '').toString();
                    const selected = opt.selected;
                    const shouldShow = propertyId === '' || optPropertyId === propertyId || selected;
                    opt.hidden = !shouldShow;
                    if (shouldShow) visibleCount++;
                });

                if (visibleCount === 0 && propertyId !== '') {
                    unitSelect.title = 'No vacant units under selected property.';
                } else {
                    unitSelect.title = '';
                }
            };

            const syncMonthlyRentFromUnit = () => {
                const selected = unitSelect.options[unitSelect.selectedIndex];
                if (!selected) return;
                const rent = selected.getAttribute('data-rent');
                if (!rent || selected.value === '') return;
                monthlyRentInput.value = Number(rent).toFixed(2);
                const selectedPropertyId = (selected.getAttribute('data-property-id') || '').toString();
                if (selectedPropertyId !== '' && propertySelect.value !== selectedPropertyId) {
                    propertySelect.value = selectedPropertyId;
                }
                syncOptionalSectionState();
                refreshUtilityTypeSources();
                renderOpeningDepositArrearsRows();
                syncDepositRules();
            };

            const createDepositRow = (label = '', amount = '', locked = false) => {
                if (!additionalDepositsWrap) return;
                const index = additionalDepositsWrap.querySelectorAll('.additional-deposit-row').length;
                const defs = getEffectiveDepositDefinitions();
                const row = document.createElement('div');
                row.className = 'grid gap-2 grid-cols-[2fr_1fr_2fr_auto] additional-deposit-row';
                row.innerHTML = `
                    <select name="additional_deposits[${index}][label]" class="additional-deposit-label rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        ${additionalLabelOptionsHtml(defs, label)}
                    </select>
                    <input type="number" name="additional_deposits[${index}][amount]" value="${amount}" step="0.01" min="0" placeholder="Amount" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <div class="deposit-line-meta rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">—</div>
                    <button type="button" class="remove-deposit-row rounded-lg border border-red-200 px-2.5 py-2 text-xs font-medium text-red-700 hover:bg-red-50" ${locked ? 'disabled' : ''}>Remove</button>
                `;
                if (locked && !canCustomDepositOverride) {
                    const labelSelect = row.querySelector('.additional-deposit-label');
                    if (labelSelect instanceof HTMLSelectElement) labelSelect.disabled = true;
                }
                additionalDepositsWrap.appendChild(row);
            };
            const syncDepositRules = () => {
                const defs = getEffectiveDepositDefinitions();
                const byLabel = new Map(defs.map((d) => [String(d.label || ''), d]));
                const rentDef = defs.find((d) => String(d.deposit_key || '') === 'rent_deposit');
                if (rentDepositInput && rentDef) {
                    const required = !!rentDef.is_required;
                    if (!rentDepositInput.value || Number(rentDepositInput.value) === 0) {
                        rentDepositInput.value = toMoney(computeDefinitionAmount(rentDef));
                    }
                    rentDepositInput.readOnly = required && !canCustomDepositOverride;
                    if (rentDepositMeta) rentDepositMeta.textContent = renderDepositMeta(rentDef);
                }
                additionalDepositsWrap?.querySelectorAll('.additional-deposit-label').forEach((el) => {
                    if (!(el instanceof HTMLSelectElement)) return;
                    const current = el.value || '';
                    el.innerHTML = additionalLabelOptionsHtml(defs, current);
                    const row = el.closest('.additional-deposit-row');
                    const def = byLabel.get(el.value || '');
                    const metaEl = row?.querySelector('.deposit-line-meta');
                    if (metaEl) metaEl.textContent = renderDepositMeta(def);
                    if (row) styleDepositRow(row, def);
                });
                const requiredOptional = defs.filter((d) => d.is_required && String(d.deposit_key || '') !== 'rent_deposit');
                const existing = new Set(Array.from(additionalDepositsWrap?.querySelectorAll('.additional-deposit-label') ?? []).map((el) => el.value || ''));
                requiredOptional.forEach((d) => {
                    const label = String(d.label || '');
                    if (!label || existing.has(label)) return;
                    createDepositRow(label, toMoney(computeDefinitionAmount(d)), true);
                });
                const rows = Array.from(additionalDepositsWrap?.querySelectorAll('.additional-deposit-row') ?? []);
                rows
                    .sort((a, b) => {
                        const aLabel = a.querySelector('.additional-deposit-label')?.value || '';
                        const bLabel = b.querySelector('.additional-deposit-label')?.value || '';
                        const aReq = !!byLabel.get(aLabel)?.is_required;
                        const bReq = !!byLabel.get(bLabel)?.is_required;
                        return Number(bReq) - Number(aReq);
                    })
                    .forEach((row) => additionalDepositsWrap?.appendChild(row));
                reindexDepositRows();
            };
            openOptionalFieldsEditModalButton?.addEventListener('click', () => {
                if (openOptionalFieldsEditModalButton.disabled) return;
                window.openLeaseSubmodal?.(leaseFormId, 'optional');
                renderUtilityDefaultsTable();
            });
            additionalDepositsWrap?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-deposit-row')) return;
                const row = target.closest('.additional-deposit-row');
                if (row) row.remove();
                reindexDepositRows();
            });
            const carryForwardInline = leaseEditForm?.querySelector('[data-lease-carry-forward-inline]');
            carryForwardInline?.addEventListener('input', () => window.markLeaseCarryForwardTouched?.(leaseFormId));
            carryForwardInline?.addEventListener('change', () => window.markLeaseCarryForwardTouched?.(leaseFormId));
            const carryForwardSyncHolder = leaseEditForm?.querySelector('[data-lease-carry-forward-sync]');
            const appendCarryForwardHiddenInput = (name, value) => {
                if (!(carryForwardSyncHolder instanceof HTMLElement)) return;
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = value ?? '';
                carryForwardSyncHolder.appendChild(hidden);
            };
            const buildOpeningArrearsRowHtml = (index, chargeType, specificCharge, period, amount, formAttr = '') => `
                <td class="px-3 py-2">
                    <select name="opening_arrears[${index}][charge_type]"${formAttr} class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="water">Water</option>
                        <option value="electricity">Electricity</option>
                        <option value="service">Service</option>
                        <option value="garbage">Garbage</option>
                        <option value="other">Other</option>
                    </select>
                </td>
                <td class="px-3 py-2">
                    <input type="text" name="opening_arrears[${index}][specific_charge]"${formAttr} value="${specificCharge}" placeholder="e.g. Water meter bill" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </td>
                <td class="px-3 py-2">
                    <input type="month" name="opening_arrears[${index}][period]"${formAttr} value="${period}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </td>
                <td class="px-3 py-2">
                    <input type="number" name="opening_arrears[${index}][amount]"${formAttr} value="${amount}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </td>
                <td class="px-3 py-2">
                    <button type="button" class="remove-opening-arrears-row rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Remove</button>
                </td>
            `;
            const appendOpeningArrearsRow = (tbody, chargeType = 'water', specificCharge = '', period = '', amount = '', useFormAttr = false) => {
                if (!(tbody instanceof HTMLElement)) return;
                const index = tbody.querySelectorAll('.opening-arrears-row').length;
                const formAttr = useFormAttr ? ` form="${leaseFormId}"` : '';
                const row = document.createElement('tr');
                row.className = 'opening-arrears-row border-t border-amber-100 dark:border-amber-800/40';
                row.innerHTML = buildOpeningArrearsRowHtml(index, chargeType, specificCharge, period, amount, formAttr);
                const select = row.querySelector('select');
                if (select) select.value = chargeType;
                tbody.appendChild(row);
                if (useFormAttr) {
                    appendCarryForwardHiddenInput(`opening_arrears[${index}][charge_type]`, chargeType);
                    appendCarryForwardHiddenInput(`opening_arrears[${index}][specific_charge]`, specificCharge);
                    appendCarryForwardHiddenInput(`opening_arrears[${index}][period]`, period);
                    appendCarryForwardHiddenInput(`opening_arrears[${index}][amount]`, amount);
                }
                window.markLeaseCarryForwardTouched?.(leaseFormId);
            };
            const appendOpeningArrearsEditRow = (chargeType, specificCharge, period, amount) => {
                appendOpeningArrearsRow(openingArrearsEditRows, chargeType, specificCharge, period, amount, true);
            };
            const openArrearsLineModalEdit = () => {
                window.openLeaseSubmodal?.(leaseFormId, 'arrearsLine');
            };
            const closeArrearsLineModalEdit = () => {
                window.closeLeaseSubmodal?.(leaseFormId, 'arrearsLine');
            };
            openArrearsLineInlineEditButton?.addEventListener('click', () => {
                appendOpeningArrearsRow(openingArrearsInlineRows);
            });
            openArrearsLineModalEditButton?.addEventListener('click', openArrearsLineModalEdit);
            cancelArrearsLineModalEditButton?.addEventListener('click', closeArrearsLineModalEdit);
            saveArrearsLineModalEditButton?.addEventListener('click', () => {
                appendOpeningArrearsEditRow(
                    (arrearsLineEditChargeType && 'value' in arrearsLineEditChargeType) ? arrearsLineEditChargeType.value : 'water',
                    arrearsLineEditSpecificCharge?.value || '',
                    arrearsLineEditPeriod?.value || '',
                    arrearsLineEditAmount?.value || ''
                );
                closeArrearsLineModalEdit();
            });
            const removeOpeningArrearsRow = (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-opening-arrears-row')) return;
                const row = target.closest('.opening-arrears-row');
                if (row) {
                    row.remove();
                    window.markLeaseCarryForwardTouched?.(leaseFormId);
                }
            };
            openingArrearsEditRows?.addEventListener('click', removeOpeningArrearsRow);
            openingArrearsInlineRows?.addEventListener('click', removeOpeningArrearsRow);

            // Utility defaults are display-only in lease edit.

            propertySelect.addEventListener('change', () => {
                filterUnits();
                refreshUtilityTypeSources();
                renderOpeningDepositArrearsRows();
                syncOptionalSectionState();
                syncDepositRules();
            });
            unitSelect.addEventListener('change', syncMonthlyRentFromUnit);
            filterUnits();
            syncMonthlyRentFromUnit();
            refreshUtilityTypeSources();
            renderOpeningDepositArrearsRows();
            syncOptionalSectionState();
            syncDepositRules();
        })();
    </script>
</x-property.workspace>

