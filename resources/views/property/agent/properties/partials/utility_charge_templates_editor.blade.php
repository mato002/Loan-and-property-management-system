@php
    $savedChargeTemplates = is_array($propertyChargeTemplates ?? null) ? $propertyChargeTemplates : [];
    $seedChargeTemplates = old('charge_templates');
    if (! is_array($seedChargeTemplates)) {
        $seedChargeTemplates = $savedChargeTemplates;
    }
    $seedChargeTemplates = array_values(array_map(static function ($row): array {
        $row = is_array($row) ? $row : [];
        $unitId = $row['property_unit_id'] ?? '';

            $rawMode = strtolower(trim((string) ($row['amount_mode'] ?? '')));
            $type = strtolower(trim((string) ($row['charge_type'] ?? 'garbage'))) ?: 'garbage';
            $label = strtolower(trim((string) ($row['label'] ?? '')));
            $rate = is_numeric($row['rate_per_unit'] ?? null) ? (float) $row['rate_per_unit'] : 0.0;
            $fixed = is_numeric($row['fixed_charge'] ?? null) ? (float) $row['fixed_charge'] : 0.0;
            $waterAmount = is_numeric($row['water_amount'] ?? null) ? (float) $row['water_amount'] : 0.0;
            $isElectricity = $type === 'electricity' || str_contains($label, 'electric');
            $isWater = $type === 'water';
            if ($isElectricity && $type !== 'electricity') {
                $type = 'electricity';
            }
            if ($isElectricity && $rawMode !== 'variable' && $rate <= 0.009 && $fixed > 0.009) {
                $rate = $fixed;
                $fixed = 0.0;
                $rawMode = 'per_unit';
            }
            if ($isWater && $waterAmount <= 0.009 && $rate > 0.009) {
                $waterAmount = $rate;
            }
            if (in_array($rawMode, ['variable', 'manual', 'monthly'], true)) {
                $amountMode = 'variable';
            } elseif ($isWater) {
                $amountMode = 'fixed';
            } elseif ($rawMode === 'per_unit' || ($isElectricity && $rate > 0.009 && $fixed <= 0.009 && $rawMode !== 'fixed')) {
                $amountMode = 'per_unit';
            } elseif ($rawMode === 'fixed') {
                $amountMode = 'fixed';
            } elseif ($fixed > 0 || $rate > 0) {
                $amountMode = 'fixed';
            } else {
                $amountMode = $isElectricity ? 'per_unit' : 'fixed';
            }

        return [
            'property_unit_id' => ($unitId !== '' && $unitId !== null) ? (string) (int) $unitId : '',
            'charge_type' => $type,
            'label' => (string) ($row['label'] ?? ''),
            'amount_mode' => $amountMode,
            'rate_per_unit' => $rate > 0 ? (string) $rate : (is_numeric($row['rate_per_unit'] ?? null) ? (string) $row['rate_per_unit'] : ''),
            'fixed_charge' => $fixed > 0 ? (string) $fixed : '',
            'vat_rate' => is_numeric($row['vat_rate'] ?? null) ? (string) $row['vat_rate'] : '',
            'escalates_with_rent' => ! empty($row['escalates_with_rent']),
            'water_amount' => $waterAmount > 0 ? (string) $waterAmount : '',
            'maintenance_fee' => is_numeric($row['maintenance_fee'] ?? null) ? (string) $row['maintenance_fee'] : '',
            'notes' => (string) ($row['notes'] ?? ''),
        ];
    }, $seedChargeTemplates));
    $unitLabelMap = collect($units ?? [])->mapWithKeys(fn ($u) => [(string) $u->id => (string) $u->label])->all();
    $unitOptions = collect($units ?? [])->map(fn ($u) => ['id' => (string) $u->id, 'label' => (string) $u->label])->values()->all();
@endphp
<form
    method="post"
    action="{{ route('property.properties.update', $property) }}"
    x-data="{
        formOpen: false,
        chargeTypeOptions: ['water', 'electricity', 'garbage', 'service_charge', 'other'],
        unitLabels: @js($unitLabelMap),
        units: @js($unitOptions),
        charges: @js($seedChargeTemplates),
        editingIndex: null,
        scopeMode: 'property',
        unitDrafts: {},
        fillAllAmount: '',
        fillAllMaintenance: '',
        draft: { property_unit_id: '', charge_type: '', label: '', amount_mode: 'fixed', rate_per_unit: '', fixed_charge: '', water_amount: '', maintenance_fee: '', vat_rate: '', escalates_with_rent: false, notes: '' },
        emptyDraft() {
            return { property_unit_id: '', charge_type: '', label: '', amount_mode: 'fixed', rate_per_unit: '', fixed_charge: '', water_amount: '', maintenance_fee: '', vat_rate: '', escalates_with_rent: false, notes: '' };
        },
        init() {
            this.resetUnitDrafts();
            this.charges.forEach((charge) => {
                const type = String(charge?.charge_type || '').trim().toLowerCase();
                if (type !== '' && !this.chargeTypeOptions.includes(type)) this.chargeTypeOptions.push(type);
            });
        },
        resetUnitDrafts() {
            const next = {};
            this.units.forEach((unit) => {
                next[unit.id] = { mode: 'exclude', amount: '', maintenance: '' };
            });
            this.unitDrafts = next;
            this.fillAllAmount = '';
            this.fillAllMaintenance = '';
        },
        isWaterType() {
            return String(this.draft.charge_type || '').toLowerCase() === 'water';
        },
        isWaterFixed() {
            return this.isWaterType() && String(this.draft.amount_mode || '') !== 'variable';
        },
        isElectricityType() {
            return String(this.draft.charge_type || '').toLowerCase() === 'electricity';
        },
        isPerUnit() {
            return String(this.draft.amount_mode || '') === 'per_unit';
        },
        onScopeChange() {
            if (this.scopeMode === 'units') this.loadUnitDrafts();
        },
        onChargeTypeChange() {
            if (this.isElectricityType() && String(this.draft.amount_mode || '') !== 'variable') {
                if (!(Number(this.draft.rate_per_unit || 0) > 0) && Number(this.draft.fixed_charge || 0) > 0) {
                    this.draft.rate_per_unit = this.draft.fixed_charge;
                    this.draft.fixed_charge = '';
                }
                this.draft.amount_mode = 'per_unit';
                if (!String(this.draft.label || '').trim()) this.draft.label = 'Electricity';
            }
            if (this.showUnitGrid()) this.loadUnitDrafts();
        },
        warn(title, text) {
            if (window.Swal) {
                window.Swal.fire({ icon: 'warning', title, text });
                return;
            }
            window.alert(text);
        },
        async confirmReplaceSameType(type, count) {
            const label = this.typeLabel(type);
            const text = 'Replace ' + count + ' ' + label + ' unit rows with one property amount?';
            if (window.Swal) {
                const result = await window.Swal.fire({
                    icon: 'question',
                    title: 'Replace ' + label + '?',
                    text,
                    showCancelButton: true,
                    confirmButtonText: 'Replace',
                    cancelButtonText: 'Cancel',
                });
                return !!result.isConfirmed;
            }
            return window.confirm(text);
        },
        draftFromCharge(charge) {
            const mode = String(charge?.amount_mode || '') === 'variable' ? 'variable' : 'fixed';
            const isWater = String(charge?.charge_type || '').toLowerCase() === 'water';
            const amount = mode !== 'fixed'
                ? ''
                : (isWater
                    ? String((charge?.water_amount !== '' && charge?.water_amount != null) ? charge.water_amount : (charge?.rate_per_unit || ''))
                    : String(charge?.fixed_charge || charge?.rate_per_unit || ''));
            return {
                mode,
                amount,
                maintenance: mode === 'fixed' ? String(charge?.maintenance_fee || '') : '',
            };
        },
        loadUnitDrafts() {
            const type = String(this.draft.charge_type || '').trim().toLowerCase();
            const propertyRow = this.charges.find((charge) => String(charge.charge_type || '').toLowerCase() === type && String(charge.property_unit_id || '') === '');
            const next = {};
            this.units.forEach((unit) => {
                const specific = this.charges.find((charge) => String(charge.charge_type || '').toLowerCase() === type && String(charge.property_unit_id || '') === String(unit.id));
                next[unit.id] = specific
                    ? this.draftFromCharge(specific)
                    : (propertyRow ? this.draftFromCharge(propertyRow) : { mode: 'exclude', amount: '', maintenance: '' });
            });
            this.unitDrafts = next;
        },
        anyFixed() {
            return this.units.some((unit) => (this.unitDrafts[unit.id] || {}).mode === 'fixed');
        },
        setAllMode(mode) {
            this.units.forEach((unit) => {
                const current = this.unitDrafts[unit.id] || { mode: 'exclude', amount: '', maintenance: '' };
                current.mode = mode;
                if (mode === 'fixed') {
                    current.amount = this.fillAllAmount;
                    current.maintenance = this.isWaterType() ? this.fillAllMaintenance : '';
                } else {
                    current.amount = '';
                    current.maintenance = '';
                }
                this.unitDrafts[unit.id] = current;
            });
        },
        onUnitModeChange(unitId) {
            const current = this.unitDrafts[unitId];
            if (!current || current.mode !== 'fixed') return;
            if (current.amount === '' || current.amount == null) current.amount = this.fillAllAmount;
            if (this.isWaterType() && (current.maintenance === '' || current.maintenance == null)) current.maintenance = this.fillAllMaintenance;
            this.unitDrafts[unitId] = current;
        },
        dropChargeRows(type, unitId) {
            const wanted = String(type || '').toLowerCase();
            this.charges = this.charges.filter((charge) => {
                if (String(charge.charge_type || '').toLowerCase() !== wanted) return true;
                return String(charge.property_unit_id || '') !== String(unitId || '');
            });
        },
        showUnitGrid() {
            return this.editingIndex === null && this.scopeMode === 'units';
        },
        typeLabel(type) {
            return String(type || 'other').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        },
        scopeLabel(charge) {
            const unitId = String(charge?.property_unit_id || '');
            if (unitId === '') return 'Property default';
            return this.unitLabels[unitId] || ('Unit #' + unitId);
        },
        resetDraft() {
            this.editingIndex = null;
            this.scopeMode = 'property';
            this.draft = this.emptyDraft();
            this.resetUnitDrafts();
            this.formOpen = false;
        },
        openAdd() {
            this.editingIndex = null;
            this.scopeMode = 'property';
            this.draft = this.emptyDraft();
            this.resetUnitDrafts();
            this.formOpen = true;
        },
        billingLabel(charge) {
            const mode = String(charge?.amount_mode || '').trim().toLowerCase();
            const type = String(charge?.charge_type || '').trim().toLowerCase();
            if (type !== 'water' && (mode === 'per_unit' || (type === 'electricity' && Number(charge?.rate_per_unit || 0) > 0 && !(Number(charge?.fixed_charge || 0) > 0)))) return 'Per unit';
            if (mode === 'variable') return type === 'water' ? 'Meter reading' : 'Enter monthly';
            return 'Fixed';
        },
        amountText(charge) {
            if (this.billingLabel(charge) === 'Per unit') {
                return Number(charge.rate_per_unit || 0).toFixed(2) + ' per unit';
            }
            if (this.billingLabel(charge) !== 'Fixed') return '—';
            const maintenance = Number(charge.maintenance_fee || 0);
            const rate = Number(charge.rate_per_unit || charge.water_amount || 0);
            if (String(charge?.charge_type || '').toLowerCase() === 'water' && (rate > 0.009 || maintenance > 0.009)) {
                if (rate > 0.009 && maintenance > 0.009) return rate.toFixed(2) + ' per unit + ' + maintenance.toFixed(2) + ' fee';
                if (rate > 0.009) return rate.toFixed(2) + ' per unit';
                return maintenance.toFixed(2) + ' fee';
            }
            return Number(charge.fixed_charge || charge.rate_per_unit || 0).toFixed(2);
        },
        waterPreview() {
            const rate = Number(this.draft.water_amount || 0);
            const fee = Number(this.draft.maintenance_fee || 0);
            return {
                water: (3 * rate).toFixed(2),
                fee: fee.toFixed(2),
                total: ((3 * rate) + fee).toFixed(2),
            };
        },
        warnAmount(text) {
            if (window.Swal) {
                window.Swal.fire({ icon: 'warning', title: 'Amount required', text: text });
            }
        },
        makeRow(unitId, mode, fixedCharge, waterAmount, maintenanceFee) {
            const isWater = String(this.draft.charge_type || '').toLowerCase() === 'water';
            const isElectricity = String(this.draft.charge_type || '').toLowerCase() === 'electricity';
            const perUnit = mode === 'per_unit';
            const maintenance = mode === 'fixed' ? Number(maintenanceFee || 0) : 0;
            const waterRate = mode === 'fixed' && isWater ? Number(waterAmount || 0) : 0;
            const electricRate = perUnit ? Number(waterAmount || this.draft.rate_per_unit || 0) : 0;
            return {
                property_unit_id: String(unitId || ''),
                charge_type: String(this.draft.charge_type || '').trim(),
                label: String(this.draft.label || ''),
                amount_mode: mode,
                rate_per_unit: perUnit
                    ? (electricRate > 0 ? String(electricRate) : '')
                    : (mode === 'variable' ? '' : (isWater ? (waterRate > 0 ? String(waterRate) : '') : (isElectricity ? '' : this.draft.rate_per_unit))),
                fixed_charge: perUnit || mode === 'variable' ? '' : (isWater ? (maintenance > 0 ? String(maintenance) : '') : String(fixedCharge ?? '')),
                water_amount: isWater && waterRate > 0 ? String(waterRate) : '',
                maintenance_fee: isWater && maintenance > 0.009 ? String(maintenance) : '',
                vat_rate: this.draft.vat_rate,
                escalates_with_rent: !!this.draft.escalates_with_rent,
                notes: String(this.draft.notes || ''),
            };
        },
        upsertRow(row) {
            const idx = this.charges.findIndex((charge) => String(charge.charge_type || '') === String(row.charge_type || '') && String(charge.property_unit_id || '') === String(row.property_unit_id || ''));
            if (idx >= 0) this.charges.splice(idx, 1, row);
            else this.charges.push(row);
        },
        applyFillAll() {
            this.units.forEach((unit) => {
                const current = this.unitDrafts[unit.id] || { mode: 'exclude', amount: '', maintenance: '' };
                if (current.mode !== 'fixed') return;
                current.amount = this.fillAllAmount;
                current.maintenance = this.isWaterType() ? this.fillAllMaintenance : '';
                this.unitDrafts[unit.id] = current;
            });
        },
        async addOrUpdateCharge() {
            const type = String(this.draft.charge_type || '').trim();
            if (!type) {
                this.warn('Charge type required', 'Select a charge type.');
                return;
            }
            const mode = String(this.draft.amount_mode || 'fixed') === 'variable' ? 'variable' : 'fixed';
            if (this.showUnitGrid()) {
                const rows = [];
                let missingAmount = false;
                this.units.forEach((unit) => {
                    const entry = this.unitDrafts[unit.id] || { mode: 'exclude' };
                    const unitMode = entry.mode === 'variable' ? 'variable' : (entry.mode === 'fixed' ? 'fixed' : 'exclude');
                    if (unitMode === 'exclude') return;
                    if (unitMode === 'variable') {
                        rows.push(this.makeRow(unit.id, 'variable', '', '', ''));
                        return;
                    }
                    const water = Number(entry.amount || 0);
                    const maintenance = this.isWaterType() ? Number(entry.maintenance || 0) : 0;
                    if (!(water > 0) && !(maintenance > 0)) {
                        missingAmount = true;
                        return;
                    }
                    if (this.isElectricityType()) {
                        rows.push(this.makeRow(unit.id, 'per_unit', '', water, ''));
                        return;
                    }
                    rows.push(this.makeRow(unit.id, 'fixed', this.isWaterType() ? maintenance : water, this.isWaterType() ? water : '', maintenance));
                });
                if (missingAmount) {
                    this.warnAmount('Enter an amount for every unit set to Fixed, or mark that unit Excluded.');
                    return;
                }
                const hadType = this.charges.some((charge) => String(charge.charge_type || '').toLowerCase() === type.toLowerCase());
                if (rows.length === 0 && !hadType) {
                    this.warnAmount('Set at least one unit to Fixed or Variable. Excluded units are left out.');
                    return;
                }
                this.charges = this.charges.filter((charge) => String(charge.charge_type || '').toLowerCase() !== type.toLowerCase());
                const sameFixed = rows.length === this.units.length
                    && rows.every((row) => row.amount_mode === 'fixed' && row.fixed_charge === rows[0].fixed_charge && String(row.rate_per_unit || '') === String(rows[0].rate_per_unit || '') && String(row.maintenance_fee || '') === String(rows[0].maintenance_fee || ''));
                if (sameFixed) {
                    this.charges.push(this.makeRow('', 'fixed', rows[0].fixed_charge, rows[0].water_amount, rows[0].maintenance_fee));
                } else {
                    rows.forEach((row) => this.charges.push(row));
                }
                this.resetDraft();
                return;
            }
            let fixedCharge = this.draft.fixed_charge;
            let waterAmount = '';
            let maintenanceFee = '';
            let saveMode = mode;
            if (String(type).toLowerCase() === 'electricity' && mode !== 'variable') {
                saveMode = this.isPerUnit() || mode !== 'fixed' ? 'per_unit' : 'fixed';
            }
            if (saveMode === 'per_unit') {
                waterAmount = this.draft.rate_per_unit;
                if (!(Number(waterAmount || 0) > 0)) {
                    this.warnAmount('Enter the charge per electricity unit.');
                    return;
                }
            } else if (mode === 'fixed' && String(type).toLowerCase() === 'water') {
                waterAmount = this.draft.water_amount;
                maintenanceFee = this.draft.maintenance_fee;
                if (!(Number(waterAmount || 0) > 0) && !(Number(maintenanceFee || 0) > 0)) {
                    this.warnAmount('Enter the water rate per unit, the maintenance fee, or both.');
                    return;
                }
            } else if (mode === 'fixed' && !(Number(fixedCharge || 0) > 0) && !(Number(this.draft.rate_per_unit || 0) > 0)) {
                this.warnAmount('Enter a fixed monthly amount, or switch billing to “Enter each month”.');
                return;
            }
            const row = this.makeRow(this.draft.property_unit_id, saveMode, fixedCharge, waterAmount, maintenanceFee);
            if (this.editingIndex === null && String(row.property_unit_id || '') === '') {
                const typeKey = type.toLowerCase();
                const unitCount = this.charges.filter((charge) => String(charge.charge_type || '').toLowerCase() === typeKey && String(charge.property_unit_id || '') !== '').length;
                if (unitCount > 0 && !(await this.confirmReplaceSameType(type, unitCount))) {
                    return;
                }
                this.charges = this.charges.filter((charge) => {
                    if (String(charge.charge_type || '').toLowerCase() !== typeKey) return true;
                    if (unitCount > 0) return false;
                    return String(charge.property_unit_id || '') !== '';
                });
                this.charges.push(row);
            } else if (this.editingIndex === null) {
                this.upsertRow(row);
            } else {
                this.charges.splice(this.editingIndex, 1, row);
            }
            this.resetDraft();
        },
        editCharge(index) {
            const charge = this.charges[index];
            if (!charge) return;
            this.editingIndex = index;
            this.draft = {
                property_unit_id: String(charge.property_unit_id || ''),
                charge_type: String(charge.charge_type || 'garbage'),
                label: String(charge.label || ''),
                amount_mode: String(charge.charge_type || '').toLowerCase() === 'water'
                    ? (String(charge.amount_mode || '') === 'variable' ? 'variable' : 'fixed')
                    : (['variable', 'per_unit'].includes(String(charge.amount_mode || '')) ? String(charge.amount_mode) : 'fixed'),
                rate_per_unit: charge.rate_per_unit ?? '',
                fixed_charge: charge.fixed_charge ?? '',
                water_amount: String(charge.charge_type || '').toLowerCase() === 'water' && String(charge.amount_mode || '') !== 'variable'
                    ? String((charge.water_amount !== '' && charge.water_amount != null) ? charge.water_amount : (charge.rate_per_unit || ''))
                    : '',
                maintenance_fee: charge.maintenance_fee ?? '',
                vat_rate: charge.vat_rate ?? '',
                escalates_with_rent: !!charge.escalates_with_rent,
                notes: String(charge.notes || ''),
            };
            const type = this.draft.charge_type;
            if (type && !this.chargeTypeOptions.includes(type)) this.chargeTypeOptions.push(type);
            this.formOpen = true;
        },
        removeCharge(index) {
            if (this.editingIndex === index) this.resetDraft();
            else if (this.editingIndex !== null && this.editingIndex > index) this.editingIndex -= 1;
            this.charges.splice(index, 1);
        },
        async addChargeType() {
            let raw = '';
            if (window.Swal) {
                const result = await window.Swal.fire({
                    title: 'Add charge type',
                    text: 'Examples: internet, security, sewer',
                    input: 'text',
                    inputPlaceholder: 'Enter charge type',
                    showCancelButton: true,
                    confirmButtonText: 'Add',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => {
                        if (!String(value || '').trim()) return 'Charge type is required.';
                        return null;
                    },
                });
                if (!result.isConfirmed) return;
                raw = String(result.value || '').trim();
            }
            if (!raw) return;
            const normalized = String(raw).trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            if (!normalized) return;
            if (!this.chargeTypeOptions.includes(normalized)) this.chargeTypeOptions.push(normalized);
            this.draft.charge_type = normalized;
        }
    }"
    class="md:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm min-w-0"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="name" value="{{ old('name', $property->name) }}" />
    <input type="hidden" name="code" value="{{ old('code', $property->code) }}" />
    <input type="hidden" name="city" value="{{ old('city', $property->city) }}" />
    <input type="hidden" name="address_line" value="{{ old('address_line', $property->address_line) }}" />
    <input type="hidden" name="commission_percent" value="{{ old('commission_percent', $commissionPct ?? '') }}" />
    <input type="hidden" name="utility_templates_save" value="1" />
    <template x-for="(charge, index) in charges" :key="'hidden-'+index">
        <div class="hidden">
            <input type="hidden" :name="`charge_templates[${index}][property_unit_id]`" :value="charge.property_unit_id" />
            <input type="hidden" :name="`charge_templates[${index}][charge_type]`" :value="charge.charge_type" />
            <input type="hidden" :name="`charge_templates[${index}][label]`" :value="charge.label" />
            <input type="hidden" :name="`charge_templates[${index}][amount_mode]`" :value="charge.amount_mode || 'fixed'" />
            <input type="hidden" :name="`charge_templates[${index}][rate_per_unit]`" :value="charge.rate_per_unit" />
            <input type="hidden" :name="`charge_templates[${index}][fixed_charge]`" :value="charge.fixed_charge" />
            <input type="hidden" :name="`charge_templates[${index}][water_amount]`" :value="charge.water_amount" />
            <input type="hidden" :name="`charge_templates[${index}][maintenance_fee]`" :value="charge.maintenance_fee" />
            <input type="hidden" :name="`charge_templates[${index}][vat_rate]`" :value="charge.vat_rate" />
            <input type="hidden" :name="`charge_templates[${index}][escalates_with_rent]`" :value="charge.escalates_with_rent ? 1 : 0" />
            <input type="hidden" :name="`charge_templates[${index}][notes]`" :value="charge.notes" />
        </div>
    </template>

    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Saved utility charge templates</h3>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="openAdd()" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm shadow-blue-200 hover:bg-blue-700">
                <span aria-hidden="true">+</span>
                <span>Add charge</span>
            </button>
            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save utility templates</button>
        </div>
    </div>

    <div class="max-h-[22rem] overflow-auto">
        <table class="w-full min-w-[40rem] border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Charge type</th>
                    <th class="px-4 py-3">Label</th>
                    <th class="px-4 py-3">Billing</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Notes</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(charge, index) in charges" :key="'row-'+index+'-'+(charge.property_unit_id || 'all')+'-'+(charge.charge_type || '')">
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 text-slate-700" x-text="scopeLabel(charge)"></td>
                        <td class="px-4 py-3 font-medium text-slate-900" x-text="typeLabel(charge.charge_type)"></td>
                        <td class="px-4 py-3 text-slate-700" x-text="charge.label || '—'"></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                  :class="billingLabel(charge) === 'Fixed' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'"
                                  x-text="billingLabel(charge)"></span>
                        </td>
                        <td class="px-4 py-3 tabular-nums" x-text="amountText(charge)"></td>
                        <td class="px-4 py-3 text-slate-600" x-text="charge.notes || '—'"></td>
                        <td class="px-4 py-3 text-right" data-row-ignore-click>
                            <x-property.action-menu width="w-40">
                                <button type="button" @click="editCharge(index)" class="block w-full px-3 py-2 text-left text-xs text-blue-700 hover:bg-blue-50">Edit</button>
                                <button type="button" @click="removeCharge(index)" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50">Delete</button>
                            </x-property.action-menu>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <p x-show="charges.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">No saved utility templates yet for this property.</p>
    </div>

    <x-property.modal show="formOpen" close="resetDraft()" title="" teleport="false" max-width="3xl" :close-on-escape="true">
        <x-slot:header>
            <h2 class="text-base font-semibold text-slate-900" x-text="editingIndex === null ? 'Add charge' : 'Edit charge'"></h2>
        </x-slot:header>
        <div class="space-y-3">
            <div x-show="editingIndex === null">
                <label class="block text-xs font-medium text-slate-600">Applies to</label>
                <select x-model="scopeMode" @change="onScopeChange()" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="property">Whole property</option>
                    <option value="units">Selected units</option>
                </select>
            </div>
            <div x-show="editingIndex !== null">
                <label class="block text-xs font-medium text-slate-600">Scope</label>
                <select x-model="draft.property_unit_id" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="">Whole property</option>
                    @foreach(($units ?? []) as $u)
                        <option value="{{ $u->id }}">{{ $u->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-medium text-slate-600">Charge type <span class="text-red-600">*</span></label>
                    <button type="button" @click="addChargeType()" class="rounded border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">+</button>
                </div>
                <select x-model="draft.charge_type" @change="onChargeTypeChange()" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="">Select charge type</option>
                    <template x-for="type in chargeTypeOptions" :key="'draft-type-'+type">
                        <option :value="type" x-text="typeLabel(type)"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Label</label>
                <input x-model="draft.label" type="text" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="e.g. Water bill" />
            </div>
            <div x-show="!showUnitGrid()">
                <label class="block text-xs font-medium text-slate-600">Billing</label>
                <template x-if="isWaterType()">
                    <select x-model="draft.amount_mode" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                        <option value="fixed">Fixed</option>
                        <option value="variable">Meter reading</option>
                    </select>
                </template>
                <template x-if="!isWaterType()">
                    <select x-model="draft.amount_mode" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                        <option value="fixed">Fixed</option>
                        <option value="per_unit">Per unit</option>
                        <option value="variable">Enter each month</option>
                    </select>
                </template>
            </div>
            <div x-show="showUnitGrid()" class="rounded-xl border border-slate-200">
                <div class="flex flex-wrap items-end gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2">
                    <button type="button" @click="setAllMode('fixed')" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">All fixed</button>
                    <button type="button" @click="setAllMode('variable')" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">All variable</button>
                    <button type="button" @click="setAllMode('exclude')" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">All excluded</button>
                    <div x-show="anyFixed()">
                        <label class="block text-[11px] font-medium text-slate-500" x-text="isWaterType() ? 'Rate for every fixed unit' : (isElectricityType() ? 'Charge per unit for every unit' : 'Amount for every fixed unit')"></label>
                        <input x-model="fillAllAmount" @input="applyFillAll()" type="number" min="0" step="0.01" class="mt-1 w-28 rounded-lg border border-slate-200 bg-white text-sm px-2 py-1.5" />
                    </div>
                    <div x-show="anyFixed() && isWaterType()">
                        <label class="block text-[11px] font-medium text-slate-500">Maintenance fee for every fixed unit</label>
                        <input x-model="fillAllMaintenance" @input="applyFillAll()" type="number" min="0" step="0.01" class="mt-1 w-28 rounded-lg border border-slate-200 bg-white text-sm px-2 py-1.5" />
                    </div>
                </div>
                <div class="max-h-64 overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-white text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Unit</th>
                                <th class="px-3 py-2">Billing</th>
                                <th class="px-3 py-2" x-text="isWaterType() ? 'Rate / unit' : (isElectricityType() ? 'Charge / unit' : 'Amount')"></th>
                                <th class="px-3 py-2" x-show="isWaterType()">Fee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="unit in units" :key="'unit-draft-'+unit.id">
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2 font-medium text-slate-800" x-text="unit.label"></td>
                                    <td class="px-3 py-2">
                                        <select x-model="unitDrafts[unit.id].mode" @change="onUnitModeChange(unit.id)" class="rounded-lg border border-slate-200 bg-white text-sm px-2 py-1.5">
                                            <option value="exclude">Excluded</option>
                                            <option value="fixed">Fixed</option>
                                            <option value="variable">Variable</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" min="0" step="0.01" class="w-28 rounded-lg border border-slate-200 bg-white text-sm px-2 py-1.5" x-model="unitDrafts[unit.id].amount" x-show="unitDrafts[unit.id].mode === 'fixed'" />
                                        <span class="text-xs text-slate-400" x-show="unitDrafts[unit.id].mode !== 'fixed'" x-text="unitDrafts[unit.id].mode === 'variable' ? 'Enter each month' : '—'"></span>
                                    </td>
                                    <td class="px-3 py-2" x-show="isWaterType()">
                                        <input type="number" min="0" step="0.01" class="w-28 rounded-lg border border-slate-200 bg-white text-sm px-2 py-1.5" x-model="unitDrafts[unit.id].maintenance" x-show="unitDrafts[unit.id].mode === 'fixed'" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div x-show="draft.amount_mode !== 'variable' && !showUnitGrid() && isWaterFixed()" class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Rate per unit</label>
                    <input x-model="draft.water_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="e.g. 200" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Fee</label>
                    <input x-model="draft.maintenance_fee" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                </div>
                <p class="sm:col-span-2 text-xs tabular-nums text-slate-700" x-text="'3 × ' + Number(draft.water_amount || 0).toFixed(2) + ' + ' + Number(draft.maintenance_fee || 0).toFixed(2) + ' = ' + waterPreview().total"></p>
            </div>
            <div x-show="isPerUnit() && !showUnitGrid() && !isWaterType()">
                <label class="block text-xs font-medium text-slate-600">Charge per unit <span class="text-red-600">*</span></label>
                <input x-model="draft.rate_per_unit" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div x-show="draft.amount_mode === 'fixed' && !showUnitGrid() && !isWaterFixed()">
                <label class="block text-xs font-medium text-slate-600">Fixed charge <span class="text-red-600">*</span></label>
                <input x-model="draft.fixed_charge" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div x-show="draft.amount_mode === 'fixed' && !showUnitGrid() && !isWaterFixed()">
                <label class="block text-xs font-medium text-slate-600">Per area / measure (optional)</label>
                <input x-model="draft.rate_per_unit" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">VAT rate %</label>
                    <input x-model="draft.vat_rate" type="number" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="e.g. 16" />
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 sm:mt-7">
                    <input type="checkbox" x-model="draft.escalates_with_rent" class="rounded border-slate-300" />
                    % with rent
                </label>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Notes</label>
                <input x-model="draft.notes" type="text" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="Optional notes" />
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button type="button" @click="resetDraft()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" @click="addOrUpdateCharge()" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700" x-text="editingIndex !== null ? 'Update charge' : (scopeMode === 'units' ? 'Add unit charges' : 'Add charge')"></button>
            </div>
        </x-slot:footer>
    </x-property.modal>
</form>
