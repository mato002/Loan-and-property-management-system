        <script>
            window.initLeaseFormLogic = window.initLeaseFormLogic || function () {
            const leaseForm = document.getElementById('lease-form-wrapper');
            if (!leaseForm) {
                return;
            }

            const bindLeaseFormLogic = () => {
                if (leaseForm.dataset.leaseJsBound === '1') {
                    if (typeof leaseForm._runVisibleSetup === 'function') {
                        leaseForm._runVisibleSetup();
                    }
                    return;
                }
                leaseForm.dataset.leaseJsBound = '1';

            const leaseUtilityExpenseFormOld = @json(collect(old('utility_expenses', []))->values()->all());
            const openingDepositArrearsOld = @json((array) ($openingDepositArrearsRows ?? []));
            const leaseFormEndpoints = @json($leaseFormEndpoints ?? []);
            const leaseFormSelectedTenantId = @json((int) ($leaseFormSelectedTenantId ?? 0));
            const leaseFormSelectedUnitId = @json((int) ($leaseFormSelectedUnitId ?? 0));
            const leaseFormSelectedPropertyId = @json((string) ($selectedPropertyId ?? $leaseFormSelectedPropertyId ?? ''));
            let utilityTemplatesByProperty = {};
            let depositDefinitionsByProperty = {};
            const canCustomDepositOverride = @js((bool) ((auth()->user()?->is_super_admin ?? false) || (auth()->user()?->hasPmPermission('settings.manage') ?? false) || (\App\Models\PropertyPortalSetting::getValue('lease_deposit_allow_custom_types', '0') === '1')));
            const leaseFormToggleButton = document.getElementById('toggle-lease-form-button');
            const leaseFormToggleLabel = document.getElementById('toggle-lease-form-label');
            const propertySelect = document.getElementById('lease-property-select');
            const unitSelect = document.getElementById('lease-unit-select');
            const monthlyRentInput = document.getElementById('lease-monthly-rent');
            const additionalDepositsWrap = document.getElementById('additional-deposits-rows');
            const rentDepositInput = document.getElementById('lease-rent-deposit');
            const rentDepositMeta = document.getElementById('rent-deposit-meta');
            const leaseFormId = 'lease-form-wrapper';
            const openOptionalFieldsCreateModalButton = document.getElementById('open-optional-fields-create-modal');
            const utilityDefaultsTbody = document.getElementById('utility-defaults-tbody');
            const utilityDefaultsEmptyHint = document.getElementById('utility-defaults-empty');
            const openingArrearsCreateWrap = document.getElementById('opening-arrears-create-wrap');
            const openingArrearsCreateRows = document.getElementById('opening-arrears-create-rows');
            const openArrearsLineModalCreateButton = document.getElementById('open-arrears-line-modal-create');
            const cancelArrearsLineModalCreateButton = document.getElementById('cancel-arrears-line-modal-create');
            const saveArrearsLineModalCreateButton = document.getElementById('save-arrears-line-modal-create');
            const arrearsLineCreateChargeType = document.getElementById('arrears-line-create-charge-type');
            const arrearsLineCreateSpecificCharge = document.getElementById('arrears-line-create-specific-charge');
            const arrearsLineCreatePeriod = document.getElementById('arrears-line-create-period');
            const arrearsLineCreateAmount = document.getElementById('arrears-line-create-amount');
            const toggleOpeningArrearsCreateButton = document.getElementById('toggle-opening-arrears-create');
            const openingDepositArrearsCreateRows = document.getElementById('opening-deposit-arrears-create-rows');
            const openingDepositArrearsCreateEmpty = document.getElementById('opening-deposit-arrears-create-empty');
            if (!propertySelect || !unitSelect || !monthlyRentInput) {
                return;
            }

            const EMPTY_CELL = '\u2014';

            const fetchLeaseFormJson = async (url) => {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error(`Lease form request failed (${response.status})`);
                }
                return response.json();
            };

            const populateTenantSelect = (items, selectedTenantId = 0) => {
                const tenantSelect = leaseForm.querySelector('select[name="pm_tenant_id"]');
                if (!(tenantSelect instanceof HTMLSelectElement)) {
                    return;
                }
                const previousValue = tenantSelect.value;
                tenantSelect.innerHTML = '<option value="">Select tenant…</option>';
                (items || []).forEach((item) => {
                    const option = document.createElement('option');
                    option.value = String(item.value ?? '');
                    option.textContent = String(item.label ?? item.value ?? '');
                    if (selectedTenantId > 0 && String(item.value) === String(selectedTenantId)) {
                        option.selected = true;
                    }
                    tenantSelect.appendChild(option);
                });
                if (previousValue !== '') {
                    tenantSelect.value = previousValue;
                } else if (selectedTenantId > 0) {
                    tenantSelect.value = String(selectedTenantId);
                }
            };

            const populatePropertySelect = (properties, selectedPropertyId = '') => {
                const previousValue = propertySelect.value;
                propertySelect.innerHTML = '<option value="">All properties</option>';
                (properties || []).forEach((property) => {
                    const option = document.createElement('option');
                    option.value = String(property.id ?? '');
                    option.textContent = String(property.name ?? property.id ?? '');
                    if (selectedPropertyId !== '' && String(property.id) === String(selectedPropertyId)) {
                        option.selected = true;
                    }
                    propertySelect.appendChild(option);
                });
                if (previousValue !== '') {
                    propertySelect.value = previousValue;
                } else if (selectedPropertyId !== '') {
                    propertySelect.value = String(selectedPropertyId);
                }
            };

            const populateUnitSelect = (units, selectedUnitId = 0) => {
                const previousValue = unitSelect.value;
                unitSelect.innerHTML = '<option value="">Select unit...</option>';
                if (!Array.isArray(units) || units.length === 0) {
                    const emptyOption = document.createElement('option');
                    emptyOption.value = '';
                    emptyOption.disabled = true;
                    emptyOption.textContent = 'No vacant units';
                    unitSelect.appendChild(emptyOption);
                    return;
                }
                units.forEach((unit) => {
                    const option = document.createElement('option');
                    option.value = String(unit.id ?? '');
                    option.setAttribute('data-property-id', String(unit.property_id ?? ''));
                    option.setAttribute('data-rent', String(unit.rent_amount ?? 0));
                    option.textContent = `${unit.property_name ?? 'Property'} / ${unit.label ?? unit.id ?? ''}`;
                    if (selectedUnitId > 0 && String(unit.id) === String(selectedUnitId)) {
                        option.selected = true;
                    }
                    unitSelect.appendChild(option);
                });
                if (previousValue !== '') {
                    unitSelect.value = previousValue;
                } else if (selectedUnitId > 0) {
                    unitSelect.value = String(selectedUnitId);
                }
            };

            const loadTenants = async (search = '') => {
                if (!leaseFormEndpoints.tenants) {
                    return;
                }
                const params = new URLSearchParams();
                if (search !== '') {
                    params.set('q', search);
                }
                if (leaseFormSelectedTenantId > 0) {
                    params.set('selected', String(leaseFormSelectedTenantId));
                }
                const data = await fetchLeaseFormJson(`${leaseFormEndpoints.tenants}?${params.toString()}`);
                populateTenantSelect(data.items || [], leaseFormSelectedTenantId);
            };

            const loadVacantUnits = async (propertyId = '', refreshProperties = false) => {
                if (!leaseFormEndpoints.vacantUnits) {
                    return;
                }
                const params = new URLSearchParams();
                if (propertyId !== '') {
                    params.set('property_id', propertyId);
                }
                if (leaseFormSelectedUnitId > 0) {
                    params.set('selected_unit_id', String(leaseFormSelectedUnitId));
                }
                const data = await fetchLeaseFormJson(`${leaseFormEndpoints.vacantUnits}?${params.toString()}`);
                if (refreshProperties) {
                    populatePropertySelect(data.properties || [], leaseFormSelectedPropertyId);
                }
                populateUnitSelect(data.units || [], leaseFormSelectedUnitId);
            };

            const loadPropertyRules = async (propertyId = '') => {
                if (!leaseFormEndpoints.propertyRules || propertyId === '') {
                    utilityTemplatesByProperty = {};
                    depositDefinitionsByProperty = {};
                    return;
                }
                const data = await fetchLeaseFormJson(`${leaseFormEndpoints.propertyRules}?property_id=${encodeURIComponent(propertyId)}`);
                utilityTemplatesByProperty = {
                    [String(propertyId)]: Array.isArray(data.utilityChargeTemplates) ? data.utilityChargeTemplates : [],
                };
                depositDefinitionsByProperty = {
                    [String(propertyId)]: Array.isArray(data.depositDefinitions) ? data.depositDefinitions : [],
                };
            };

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
                if (!def) return EMPTY_CELL;
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
            const buildArrearsChargeTypeOptionsHtml = (rows) => rows
                .map((row) => `<option value="${row.value}">${row.label}</option>`)
                .join('');
            const syncOptionalSectionState = () => {
                const selectedUnit = getSelectedUnitOption();
                const enabled = !!selectedUnit;
                if (openOptionalFieldsCreateModalButton) {
                    openOptionalFieldsCreateModalButton.disabled = !enabled;
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
                        utilityDefaultsEmptyHint.textContent = 'Select a property and unit first.';
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
                    const rateShow = rateDisabled ? EMPTY_CELL : (rateVal !== '' ? rateVal : EMPTY_CELL);
                    const fixedShow = fixedDisabled ? EMPTY_CELL : (fixedVal !== '' ? fixedVal : EMPTY_CELL);
                    const escAttr = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-slate-100 dark:border-slate-700';
                    tr.innerHTML = `
                        <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-200">${tpl.label.replace(/</g, '&lt;')}</td>
                        <td class="px-3 py-2">
                            <input form="${leaseFormId}" type="hidden" name="utility_expenses[${idx}][type]" value="${escAttr(tpl.value)}" />
                            <input form="${leaseFormId}" type="hidden" name="utility_expenses[${idx}][rate_per_unit]" value="${escAttr(ratePost)}" />
                            <span class="text-sm tabular-nums text-slate-700 dark:text-slate-200">${rateShow === EMPTY_CELL ? EMPTY_CELL : escAttr(rateShow)}</span>
                        </td>
                        <td class="px-3 py-2">
                            <input form="${leaseFormId}" type="hidden" name="utility_expenses[${idx}][fixed_charge]" value="${escAttr(fixedPost)}" />
                            <span class="text-sm tabular-nums text-slate-700 dark:text-slate-200">${fixedShow === EMPTY_CELL ? EMPTY_CELL : escAttr(fixedShow)}</span>
                        </td>
                    `;
                    utilityDefaultsTbody.appendChild(tr);
                });
            };
            const refreshUtilityTypeSources = () => {
                renderUtilityDefaultsTable();
            };
            const refreshOpeningArrearsChargeTypes = () => {
                const templateRows = getTemplateTypeRows(getCurrentPropertyId());
                const optionsHtml = templateRows.length > 0
                    ? buildArrearsChargeTypeOptionsHtml(templateRows)
                    : '<option value="">No property charge types configured</option>';

                if (arrearsLineCreateChargeType) {
                    const prev = arrearsLineCreateChargeType.value;
                    arrearsLineCreateChargeType.innerHTML = optionsHtml;
                    arrearsLineCreateChargeType.disabled = templateRows.length === 0;
                    arrearsLineCreateChargeType.value = templateRows.some((row) => row.value === prev)
                        ? prev
                        : (templateRows[0]?.value || '');
                }

                document.querySelectorAll('#opening-arrears-create-rows select[name$="[charge_type]"]').forEach((selectEl) => {
                    const select = selectEl;
                    if (!(select instanceof HTMLSelectElement)) return;
                    const prev = select.value;
                    select.innerHTML = optionsHtml;
                    select.disabled = templateRows.length === 0;
                    select.value = templateRows.some((row) => row.value === prev)
                        ? prev
                        : (templateRows[0]?.value || '');
                });
            };
            const renderOpeningDepositArrearsRows = () => {
                if (!openingDepositArrearsCreateRows) return;
                const defs = getEffectiveDepositDefinitions();
                const rows = defs
                    .filter((d) => String(d.deposit_key || '').trim() !== '')
                    .map((d) => ({
                        key: String(d.deposit_key || '').trim(),
                        label: String(d.label || d.deposit_key || 'Deposit').trim(),
                    }));
                openingDepositArrearsCreateRows.innerHTML = '';
                if (rows.length === 0) {
                    openingDepositArrearsCreateEmpty?.classList.remove('hidden');
                    return;
                }
                openingDepositArrearsCreateEmpty?.classList.add('hidden');
                rows.forEach((row) => {
                    const currentValue = openingDepositArrearsOld[row.key] ?? '';
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-amber-100';
                    tr.innerHTML = `
                        <td class="px-3 py-2 text-slate-700">${escapeHtml(row.label)}</td>
                        <td class="px-3 py-2">
                            <input form="${leaseFormId}" type="number" name="opening_deposit_arrears[${escapeHtml(row.key)}]" value="${escapeHtml(currentValue)}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                        </td>
                    `;
                    openingDepositArrearsCreateRows.appendChild(tr);
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
                refreshOpeningArrearsChargeTypes();
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
                    <select form="${leaseFormId}" name="additional_deposits[${index}][label]" class="additional-deposit-label rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        ${additionalLabelOptionsHtml(defs, label)}
                    </select>
                    <input form="${leaseFormId}" type="number" name="additional_deposits[${index}][amount]" value="${amount}" step="0.01" min="0" placeholder="Amount" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <div class="deposit-line-meta rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">${EMPTY_CELL}</div>
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
            const runVisibleFormSetup = () => {
                filterUnits();
                syncOptionalSectionState();
                if (getSelectedUnitOption()) {
                    syncMonthlyRentFromUnit();
                }
            };
            leaseForm._runVisibleSetup = runVisibleFormSetup;

            const syncPropertyScopedFields = () => {
                if (!getSelectedUnitOption() && !(propertySelect.value || '').toString()) {
                    return;
                }
                refreshUtilityTypeSources();
                refreshOpeningArrearsChargeTypes();
                renderOpeningDepositArrearsRows();
                syncDepositRules();
            };

            if (leaseFormToggleButton && leaseFormToggleLabel) {
                leaseFormToggleButton.addEventListener('click', () => {
                    const isHidden = leaseForm.classList.toggle('hidden');
                    leaseFormToggleButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                    leaseFormToggleLabel.textContent = isHidden ? 'Create lease' : 'Hide lease form';
                });
            }

            if (leaseForm.dataset.leaseSubmitBound !== '1') {
                leaseForm.dataset.leaseSubmitBound = '1';
                leaseForm.addEventListener('submit', () => {
                    window.syncLeaseCarryForwardToForm?.(leaseFormId);
                });
            }

            openOptionalFieldsCreateModalButton?.addEventListener('click', () => {
                if (openOptionalFieldsCreateModalButton.disabled) return;
                window.openLeaseSubmodal?.(leaseFormId, 'optional');
                renderUtilityDefaultsTable();
                syncDepositRules();
            });
            additionalDepositsWrap?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-deposit-row')) return;
                const row = target.closest('.additional-deposit-row');
                if (row) row.remove();
                reindexDepositRows();
            });
            toggleOpeningArrearsCreateButton?.addEventListener('click', () => {
                window.openLeaseSubmodal?.(leaseFormId, 'arrears');
                refreshOpeningArrearsChargeTypes();
                renderOpeningDepositArrearsRows();
            });
            const openArrearsLineModalCreate = () => {
                window.openLeaseSubmodal?.(leaseFormId, 'arrearsLine');
            };
            const closeArrearsLineModalCreate = () => {
                window.closeLeaseSubmodal?.(leaseFormId, 'arrearsLine');
            };
            const appendOpeningArrearsCreateRow = (chargeType = 'water', specificCharge = '', period = '', amount = '') => {
                if (!openingArrearsCreateRows) return;
                const templateRows = getTemplateTypeRows(getCurrentPropertyId());
                if (templateRows.length === 0) {
                    const msg = 'No charge types configured for the selected property.';
                    window.Swal.fire({ icon: 'info', title: 'No charge types', text: msg });
                    return;
                }
                const index = openingArrearsCreateRows.querySelectorAll('.opening-arrears-row').length;
                const row = document.createElement('tr');
                row.className = 'opening-arrears-row border-t border-amber-100';
                row.innerHTML = `
                    <td class="px-3 py-2">
                        <select form="${leaseFormId}" name="opening_arrears[${index}][charge_type]" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            ${buildArrearsChargeTypeOptionsHtml(templateRows)}
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <input form="${leaseFormId}" type="text" name="opening_arrears[${index}][specific_charge]" value="${specificCharge}" placeholder="e.g. Water meter bill" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </td>
                    <td class="px-3 py-2">
                        <input form="${leaseFormId}" type="month" name="opening_arrears[${index}][period]" value="${period}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </td>
                    <td class="px-3 py-2">
                        <input form="${leaseFormId}" type="number" name="opening_arrears[${index}][amount]" value="${amount}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </td>
                    <td class="px-3 py-2">
                        <button type="button" class="remove-opening-arrears-row rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Remove</button>
                    </td>
                `;
                const select = row.querySelector('select');
                if (select) {
                    select.value = templateRows.some((row) => row.value === chargeType)
                        ? chargeType
                        : (templateRows[0]?.value || '');
                }
                openingArrearsCreateRows.appendChild(row);
            };
            openArrearsLineModalCreateButton?.addEventListener('click', openArrearsLineModalCreate);
            cancelArrearsLineModalCreateButton?.addEventListener('click', closeArrearsLineModalCreate);
            saveArrearsLineModalCreateButton?.addEventListener('click', () => {
                appendOpeningArrearsCreateRow(
                    (arrearsLineCreateChargeType && 'value' in arrearsLineCreateChargeType) ? arrearsLineCreateChargeType.value : 'water',
                    arrearsLineCreateSpecificCharge?.value || '',
                    arrearsLineCreatePeriod?.value || '',
                    arrearsLineCreateAmount?.value || ''
                );
                closeArrearsLineModalCreate();
            });
            openingArrearsCreateRows?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-opening-arrears-row')) return;
                const row = target.closest('.opening-arrears-row');
                if (row) row.remove();
            });

            

            propertySelect.addEventListener('change', async () => {
                await loadVacantUnits((propertySelect.value || '').toString(), false);
                filterUnits();
                syncOptionalSectionState();
                await loadPropertyRules(getCurrentPropertyId());
                syncPropertyScopedFields();
            });
            unitSelect.addEventListener('change', syncMonthlyRentFromUnit);

            const bootstrapLeaseFormContext = async () => {
                try {
                    await loadTenants();
                    const initialPropertyId = leaseFormSelectedPropertyId || (propertySelect.value || '').toString();
                    await loadVacantUnits(initialPropertyId, true);
                    const propertyId = getCurrentPropertyId();
                    if (propertyId !== '') {
                        await loadPropertyRules(propertyId);
                    }
                } catch (error) {
                    console.error('Failed to load lease form context', error);
                }
                runVisibleFormSetup();
            };

            bootstrapLeaseFormContext();
            };

            bindLeaseFormLogic();
        };

        if (!window.__leaseFormLogicBound) {
            window.__leaseFormLogicBound = true;
            document.addEventListener('DOMContentLoaded', window.initLeaseFormLogic);
            document.addEventListener('turbo:load', window.initLeaseFormLogic);
            document.addEventListener('turbo:frame-load', (event) => {
                const frame = event.target;
                if (frame && (frame.id === 'property-main' || frame.id === 'lease-create-modal')) {
                    window.initLeaseFormLogic();
                }
            });
        }
    </script>
