<div
            x-data="{
                activeTab: @js($utilityCreateFormHasErrors ? 'readings' : 'overview'),
                penaltyModalOpen: false,
                penaltyLoading: false,
                penaltyRows: [],
                penaltyWarnings: [],
                penaltyTotal: 0,
                penaltyTotalDisplay: '',
                penaltyError: null,
                bulkFilter: '',
                bulkFilledCount: 0,
                penaltyPreviewUrl: @js(route('property.revenue.utilities.water_penalties.preview', [], true)),
                allUnits: @js($unitOptions),
                properties: @js($propertyOptions),
                waterUnits: @js($waterUnitOptions),
                waterProperties: @js($waterPropertyOptions),
                waterTemplatesByUnit: @js($waterTemplateByUnit ?? []),
                utilityTemplatesByUnit: @js($utilityTemplateByUnit ?? []),
                waterReadingUnitIdsByMonth: @js($waterReadingUnitIdsByMonth ?? []),
                selectedChargePropertyId: @js($oldChargePropertyId),
                selectedChargeUnitId: @js($oldChargeUnitId),
                selectedWaterPropertyId: @js($oldWaterPropertyId),
                selectedReadingUnitId: @js($oldWaterUnitId),
                selectedWaterMonth: @js(old('billing_month', now()->format('Y-m'))),
                defaultPreviousUrl: @js(route('property.revenue.utilities.water_readings.default_previous', [], true)),
                waterPrevAutofillOnMount: @json(! $skipWaterPrevAutofill),
                _prevFetchTimer: null,
                _prevFetchToken: 0,
                filteredUnits(propertyId) {
                    const pid = Number(propertyId || 0);
                    if (!pid) return [];
                    return this.allUnits.filter((unit) => Number(unit.property_id) === pid);
                },
                filteredWaterUnits() {
                    const pid = Number(this.selectedWaterPropertyId || 0);
                    if (!pid) return [];
                    return this.waterUnits.filter((unit) => Number(unit.property_id) === pid);
                },
                hasSelectedWaterProperty() {
                    return Number(this.selectedWaterPropertyId || 0) > 0;
                },
                syncUnitSelection(scope) {
                    if (scope === 'charge') {
                        const units = this.filteredUnits(this.selectedChargePropertyId);
                        const exists = units.some((unit) => Number(unit.id) === Number(this.selectedChargeUnitId));
                        if (!exists) this.selectedChargeUnitId = Number(units[0]?.id || 0);
                        this.syncChargeDefaults();
                        return;
                    }
                    const waterUnits = this.filteredWaterUnits();
                    const exists = waterUnits.some((unit) => Number(unit.id) === Number(this.selectedReadingUnitId));
                    if (!exists) this.selectedReadingUnitId = Number(waterUnits[0]?.id || 0);
                    this.autofillWaterRates();
                    this.scheduleFetchWaterPrevious();
                },
                syncChargeDefaults() {
                    const unitId = String(this.selectedChargeUnitId || '');
                    const form = this.$refs.addChargeForm;
                    if (!unitId || !form) return;
                    const typeEl = form.querySelector('select[name=charge_type]');
                    const rateEl = form.querySelector('input[name=rate_per_unit]');
                    const unitsEl = form.querySelector('input[name=units_consumed]');
                    const fixedEl = form.querySelector('input[name=fixed_charge]');
                    const amountEl = form.querySelector('input[name=amount]');
                    if (!(typeEl instanceof HTMLSelectElement)) return;
                    const type = String(typeEl.value || '').toLowerCase();
                    const byType = this.utilityTemplatesByUnit[unitId] || {};
                    const tpl = byType[type];
                    if (!tpl) return;
                    if (rateEl && (rateEl.value === '' || Number(rateEl.value) === 0)) rateEl.value = Number(tpl.rate_per_unit || 0).toFixed(2);
                    if (fixedEl) {
                        const mode = this.selectedChargeTemplateMode();
                        if (mode === 'rate_only') {
                            fixedEl.value = '';
                        } else if (fixedEl.value === '') {
                            fixedEl.value = Number(tpl.fixed_charge || 0).toFixed(2);
                        }
                    }
                    if (amountEl && (amountEl.value === '' || Number(amountEl.value) === 0)) {
                        const units = unitsEl ? Number(unitsEl.value || 0) : 0;
                        const rate = Number(rateEl?.value || 0);
                        const fixed = Number(fixedEl?.value || 0);
                        const calc = (units > 0 && rate > 0) ? ((units * rate) + fixed) : fixed;
                        if (calc > 0) amountEl.value = Number(calc).toFixed(2);
                    }
                },
                selectedChargeTemplate() {
                    const unitId = String(this.selectedChargeUnitId || '');
                    const form = this.$refs.addChargeForm;
                    if (!unitId || !form) return null;
                    const typeEl = form.querySelector('select[name=charge_type]');
                    if (!(typeEl instanceof HTMLSelectElement)) return null;
                    const type = String(typeEl.value || '').toLowerCase();
                    const byType = this.utilityTemplatesByUnit[unitId] || {};
                    return byType[type] || null;
                },
                selectedChargeTemplateMode() {
                    const tpl = this.selectedChargeTemplate();
                    if (!tpl) return 'mixed';
                    const rate = Number(tpl.rate_per_unit || 0);
                    const fixed = Number(tpl.fixed_charge || 0);
                    if (rate > 0 && fixed <= 0) return 'rate_only';
                    if (fixed > 0 && rate <= 0) return 'fixed_only';
                    return 'mixed';
                },
                fixedChargeHelpText() {
                    const mode = this.selectedChargeTemplateMode();
                    if (mode === 'rate_only') return 'This utility is configured as rate per unit only.';
                    if (mode === 'fixed_only') return 'This utility includes a fixed component.';
                    return 'Use this when the utility has a fixed component.';
                },
                autofillWaterRates() {
                    const unitId = String(this.selectedReadingUnitId || '');
                    if (!unitId) return;
                    const tpl = this.waterTemplatesByUnit[unitId] || this.utilityTemplatesByUnit?.[unitId]?.water || null;
                    if (!tpl) return;
                    const singleRate = this.$refs.singleRatePerUnit;
                    const singleFixed = this.$refs.singleFixedCharge;
                    const bulkRate = this.$refs.bulkRatePerUnit;
                    const bulkFixed = this.$refs.bulkFixedCharge;
                    if (singleRate) singleRate.value = Number(tpl.rate_per_unit || 0).toFixed(2);
                    if (singleFixed && singleFixed.value === '') singleFixed.value = Number(tpl.fixed_charge || 0).toFixed(2);
                    if (bulkRate) bulkRate.value = Number(tpl.rate_per_unit || 0).toFixed(2);
                    if (bulkFixed && bulkFixed.value === '') bulkFixed.value = Number(tpl.fixed_charge || 0).toFixed(2);
                },
                hasSelectedWaterTemplate() {
                    const unitId = String(this.selectedReadingUnitId || '');
                    if (!unitId) return false;
                    return !!(this.waterTemplatesByUnit[unitId] || this.utilityTemplatesByUnit?.[unitId]?.water);
                },
                selectedWaterTemplateMode() {
                    const unitId = String(this.selectedReadingUnitId || '');
                    const tpl = unitId ? (this.waterTemplatesByUnit[unitId] || this.utilityTemplatesByUnit?.[unitId]?.water) : null;
                    if (!tpl) return 'mixed';
                    const rate = Number(tpl.rate_per_unit || 0);
                    const fixed = Number(tpl.fixed_charge || 0);
                    if (rate > 0 && fixed <= 0) return 'rate_only';
                    if (fixed > 0 && rate <= 0) return 'fixed_only';
                    return 'mixed';
                },
                waterFixedChargeHelpText() {
                    const mode = this.selectedWaterTemplateMode();
                    if (mode === 'rate_only') return 'This unit water rule is rate-per-unit only.';
                    if (mode === 'fixed_only') return 'This unit water rule includes only fixed charge.';
                    return 'Rate/fixed auto-fill from property water template when available.';
                },
                formatWaterPreviousReading(n) {
                    const x = Number(n);
                    if (!Number.isFinite(x)) return '';
                    return String(Number(x.toFixed(3)));
                },
                scheduleFetchWaterPrevious() {
                    clearTimeout(this._prevFetchTimer);
                    this._prevFetchTimer = setTimeout(() => this.fetchWaterPreviousDefaults(), 220);
                },
                async fetchWaterPreviousDefaults() {
                    const pid = Number(this.selectedWaterPropertyId || 0);
                    const month = String(this.selectedWaterMonth || '');
                    if (!pid || !month || !this.defaultPreviousUrl) return;
                    const token = ++this._prevFetchToken;
                    const url = new URL(this.defaultPreviousUrl, window.location.origin);
                    url.searchParams.set('property_id', String(pid));
                    url.searchParams.set('billing_month', month);
                    try {
                        const res = await fetch(url.toString(), {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (token !== this._prevFetchToken) return;
                        const map = data.previous_by_unit || {};
                        const singleEl = this.$refs.singlePreviousReadingInput;
                        if (singleEl instanceof HTMLInputElement) {
                            const uid = String(this.selectedReadingUnitId || '');
                            if (uid && Object.prototype.hasOwnProperty.call(map, uid)) {
                                singleEl.value = this.formatWaterPreviousReading(map[uid]);
                            }
                        }
                        if (this.$el && typeof this.$el.querySelectorAll === 'function') {
                            this.$el.querySelectorAll('[data-water-bulk-prev]').forEach((el) => {
                                if (!(el instanceof HTMLInputElement)) return;
                                const uid = el.getAttribute('data-water-bulk-prev');
                                if (!uid || !Object.prototype.hasOwnProperty.call(map, uid)) return;
                                el.value = this.formatWaterPreviousReading(map[uid]);
                            });
                        }
                    } catch (e) {
                        if (window?.console?.debug) console.debug('Water previous reading autofill failed', e);
                    }
                },

                setTab(tab) {
                    this.activeTab = tab;
                    try { sessionStorage.setItem('utility_ops_tab', tab); } catch (e) {}
                },
                updateBulkFilledCount() {
                    const root = this.$refs.bulkReadingsRoot;
                    if (!root) { this.bulkFilledCount = 0; return; }
                    let n = 0;
                    root.querySelectorAll('[data-bulk-current]').forEach((el) => {
                        if (el instanceof HTMLInputElement && el.value !== '' && Number(el.value) >= 0) n++;
                    });
                    this.bulkFilledCount = n;
                },
                bulkRowVisible(label) {
                    const q = String(this.bulkFilter || '').trim().toLowerCase();
                    return !q || String(label || '').toLowerCase().includes(q);
                },
                async openPenaltyPreview() {
                    if (!this.penaltyPreviewUrl) return;
                    this.penaltyModalOpen = true;
                    this.penaltyLoading = true;
                    this.penaltyError = null;
                    this.penaltyRows = [];
                    this.penaltyWarnings = [];
                    this.penaltyTotal = 0;
                    try {
                        const res = await fetch(this.penaltyPreviewUrl, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) throw new Error('Preview failed');
                        const data = await res.json();
                        this.penaltyRows = data.rows || [];
                        this.penaltyWarnings = data.warnings || [];
                        this.penaltyTotal = Number(data.total_penalty || 0);
                        this.penaltyTotalDisplay = String(data.total_penalty_display || '');
                    } catch (e) {
                        this.penaltyError = e?.message || 'Could not load preview';
                    } finally {
                        this.penaltyLoading = false;
                    }
                },
                closePenaltyModal() { this.penaltyModalOpen = false; },
                isReadingRecorded(unitId) {
                    const month = String(this.selectedWaterMonth || '');
                    if (!month) return false;
                    const ids = Array.isArray(this.waterReadingUnitIdsByMonth[month]) ? this.waterReadingUnitIdsByMonth[month] : [];
                    return ids.includes(Number(unitId));
                },
            }"
            }"
            x-init="try { const s = sessionStorage.getItem('utility_ops_tab'); if (s && ['overview','readings','billing','charges'].includes(s)) activeTab = s; } catch (e) {} $watch('selectedReadingUnitId', () => { autofillWaterRates(); scheduleFetchWaterPrevious(); }); $watch('selectedWaterMonth', () => scheduleFetchWaterPrevious()); $watch('selectedChargeUnitId', () => syncChargeDefaults()); if (this.waterPrevAutofillOnMount) { $nextTick(() => scheduleFetchWaterPrevious()); }"
            class="utility-ops-shell space-y-4"
        >
            @if (! empty($opsKpis))
                <x-property.utility.compact-kpi-strip :items="$opsKpis" />
            @endif

            <nav class="utility-ops-tabbar" aria-label="Utility operations">
                <button type="button" class="utility-ops-tab" :class="activeTab === 'overview' ? 'is-active' : ''" @click="setTab('overview')"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Overview</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'readings' ? 'is-active' : ''" @click="setTab('readings')"><i class="fa-solid fa-droplet" aria-hidden="true"></i> Readings</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'billing' ? 'is-active' : ''" @click="setTab('billing')"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Billing</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'charges' ? 'is-active' : ''" @click="setTab('charges')"><i class="fa-solid fa-list" aria-hidden="true"></i> Charges</button>
            </nav>
            <div x-show="activeTab === 'overview'" x-cloak class="space-y-4">
                @include('property.agent.revenue.utilities._tab_overview')
                <x-property.responsive.quick-action-grid>
                    <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">Ledger</a>
                    <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-teal-200 bg-teal-50 text-teal-900 hover:bg-teal-100">Reconcile</a>
                    <a href="{{ route('property.revenue.utilities.periods', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100">Periods</a>
                    <button type="button" @click="setTab('readings')" class="quick-action-btn bg-cyan-600 text-white hover:bg-cyan-700">Capture readings</button>
                </x-property.responsive.quick-action-grid>
            </div>
            <div x-show="activeTab === 'readings'" x-cloak class="space-y-4">
        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm space-y-3 md:space-y-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Water meter reading</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (water-enabled)</label>
                <select x-model.number="selectedWaterPropertyId" @change="syncUnitSelection('reading')" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select property...</option>
                    <template x-for="property in waterProperties" :key="'water-property-' + property.id">
                        <option :value="property.id" x-text="property.name"></option>
                    </template>
                </select>
                @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p x-show="!hasSelectedWaterProperty()" x-cloak class="mt-1 text-xs text-amber-600">Select a water-enabled property to load units.</p>
                <p x-show="hasSelectedWaterProperty() && filteredWaterUnits().length === 0" x-cloak class="mt-1 text-xs text-amber-600">No units found for this property.</p>
            </div>
            <div class="grid gap-4 lg:grid-cols-2 items-start">
            <form method="post" action="{{ route('property.revenue.utilities.water_readings.store') }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <select name="property_unit_id" x-model.number="selectedReadingUnitId" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select unit...</option>
                        <template x-for="unit in filteredWaterUnits()" :key="'reading-unit-' + unit.id">
                            <option :value="unit.id" :disabled="isReadingRecorded(unit.id)" x-text="isReadingRecorded(unit.id) ? `${unit.label} (already recorded)` : unit.label"></option>
                        </template>
                    </select>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label><input type="month" x-model="selectedWaterMonth" name="billing_month" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Previous reading <span class="font-normal text-slate-400">(optional)</span></label>
                        <input type="number" step="0.001" min="0" name="previous_reading" x-ref="singlePreviousReadingInput" value="{{ old('previous_reading') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Loads from last reading when you pick unit & month" />
                        @error('previous_reading')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Auto-fills from the last saved <span class="font-medium">current</span> reading for this unit (before the billing month). Edit if you need a different baseline (new meter, correction).</p>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Current reading</label><input type="number" step="0.001" min="0" name="current_reading" value="{{ old('current_reading') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label><input x-ref="singleRatePerUnit" type="number" step="0.01" min="0" name="rate_per_unit" value="{{ old('rate_per_unit') }}" required :readonly="hasSelectedWaterTemplate()" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white read-only:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" /></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                    <input x-ref="singleFixedCharge" type="number" step="0.01" min="0" name="fixed_charge" :disabled="selectedWaterTemplateMode() === 'rate_only'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white disabled:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="waterFixedChargeHelpText()"></p>
                </div>
                <button type="submit" :disabled="!hasSelectedWaterProperty()" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-400">Save reading</button>
            </form>
                <form
                    method="post"
                    action="{{ route('property.revenue.utilities.water_readings.bulk') }}"
                    class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    @csrf
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Bulk water readings</h4>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Uses the same property selected above. Fill many units and save once.</p>
                <input type="hidden" name="property_id" :value="selectedWaterPropertyId || ''" />
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                        <input type="month" name="billing_month" x-model="selectedWaterMonth" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label>
                            <input x-ref="bulkRatePerUnit" type="number" name="rate_per_unit" value="{{ old('rate_per_unit') }}" step="0.01" min="0" required :readonly="hasSelectedWaterTemplate()" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white read-only:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                        <input x-ref="bulkFixedCharge" type="number" name="fixed_charge" value="{{ old('fixed_charge') }}" step="0.01" min="0" :disabled="selectedWaterTemplateMode() === 'rate_only'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white disabled:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Shared notes (optional)</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('current_readings')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" x-model="bulkFilter" placeholder="Filter units…" class="flex-1 min-w-[140px] rounded-lg border border-slate-200 text-sm px-3 py-2 min-h-[44px]" />
                    <span class="text-xs font-semibold text-teal-800 tabular-nums" x-text="`${bulkFilledCount} filled`"></span>
                </div>
                <div x-ref="bulkReadingsRoot" class="utility-bulk-grid" x-init="$nextTick(() => updateBulkFilledCount())">
                    @foreach ($waterUnitOptions as $unit)
                        <div
                            x-show="Number(selectedWaterPropertyId) === {{ (int) $unit['property_id'] }} && bulkRowVisible(@js($unit['label']))"
                            x-cloak
                            class="utility-bulk-card"
                            :class="{ 'is-recorded': isReadingRecorded({{ (int) $unit['id'] }}) }"
                        >
                            <p class="text-sm font-semibold text-slate-900">{{ $unit['label'] }}</p>
                            <label class="block text-[10px] font-semibold uppercase text-slate-500">Previous</label>
                            <input type="number" step="0.001" min="0" name="previous_readings[{{ (int) $unit['id'] }}]" data-water-bulk-prev="{{ (int) $unit['id'] }}" value="{{ old('previous_readings.'.(int) $unit['id']) }}" class="w-full rounded-lg border border-slate-200 text-sm px-2 py-2 min-h-[44px]" />
                            <label class="block text-[10px] font-semibold uppercase text-slate-500 mt-1">Current</label>
                            <input type="number" step="0.001" min="0" name="current_readings[{{ (int) $unit['id'] }}]" data-bulk-current value="{{ old('current_readings.'.(int) $unit['id']) }}" @input="updateBulkFilledCount()" class="w-full rounded-lg border border-slate-200 text-sm px-2 py-2 min-h-[44px]" placeholder="Reading" />
                        </div>
                    @endforeach
                </div>
                <div class="utility-bulk-table-wrap">
                    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2">Unit</th>
                                <th class="px-3 py-2">Previous <span class="font-normal normal-case text-slate-400">(opt.)</span></th>
                                <th class="px-3 py-2">Current reading</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($waterUnitOptions as $unit)
                                <tr x-show="Number(selectedWaterPropertyId) === {{ (int) $unit['property_id'] }}" x-cloak>
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $unit['label'] }}</td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name="previous_readings[{{ (int) $unit['id'] }}]"
                                            data-water-bulk-prev="{{ (int) $unit['id'] }}"
                                            value="{{ old('previous_readings.'.(int) $unit['id']) }}"
                                            class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                            placeholder="Loads when month/property set"
                                        />
                                        @error('previous_readings.'.(int) $unit['id'])<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name="current_readings[{{ (int) $unit['id'] }}]" data-bulk-current @input="updateBulkFilledCount()"
                                            value="{{ old('current_readings.'.(int) $unit['id']) }}"
                                            class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                            placeholder="Leave blank to skip"
                                        />
                                        @error('current_readings.'.(int) $unit['id'])<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" :disabled="!hasSelectedWaterProperty()" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-400 min-h-[44px]">Save bulk readings</button>
                </form>
            </div>
        </div>
                @include('property.agent.revenue.utilities._readings_list')
            </div>
            <div x-show="activeTab === 'billing'" x-cloak>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Billing actions</h3>
        <p class="text-xs text-slate-500">Garbage, service charge, and other fixed property expenses are billed via charge lines, then invoiced. Water uses meter readings separately.</p>
        <div class="grid gap-3 lg:grid-cols-2 xl:grid-cols-4">
            <form method="post" action="{{ route('property.revenue.utilities.attached.materialize') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3">
                @csrf
                <div><label class="block text-xs text-slate-500">Billing month</label><input type="month" name="billing_month" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">Create charge lines</button>
            </form>
            <form method="post" action="{{ route('property.revenue.utilities.water_invoices.generate') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3">
                @csrf
                <div><label class="block text-xs text-slate-500">Billing month</label><input type="month" name="billing_month" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <div><label class="block text-xs text-slate-500">Due date</label><input type="date" name="due_date" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Generate water invoices</button>
            </form>
            <form method="post" action="{{ route('property.revenue.utilities.invoices.generate') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3">
                @csrf
                <div><label class="block text-xs text-slate-500">Billing month</label><input type="month" name="billing_month" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <div><label class="block text-xs text-slate-500">Due date</label><input type="date" name="due_date" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <button type="submit" class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700">Generate other utility invoices</button>
            </form>
            <form method="post" action="{{ route('property.revenue.utilities.water_penalties.apply') }}" class="flex items-end rounded-xl border border-slate-200 p-3">
                @csrf
                <button type="button" @click="openPenaltyPreview()" class="rounded-lg bg-amber-100 border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-200 min-h-[44px]">Preview penalties</button>
                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700 min-h-[44px]" data-swal-confirm="Apply overdue water penalties now?">Apply penalties</button>
            </form>
        </div>
    </div>
            </div>
            <div x-show="activeTab === 'charges'" x-cloak class="space-y-4">
                <form method="post" action="{{ route('property.revenue.utilities.store') }}" x-ref="addChargeForm" class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm space-y-3">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add charge line</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
                    <select name="charge_type" @change="syncChargeDefaults()" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="other" @selected(old('charge_type') === 'other')>Other</option>
                        <option value="water" @selected(old('charge_type') === 'water')>Water</option>
                        <option value="electricity" @selected(old('charge_type') === 'electricity')>Electricity</option>
                        <option value="service" @selected(old('charge_type') === 'service')>Service</option>
                        <option value="garbage" @selected(old('charge_type') === 'garbage')>Garbage</option>
                    </select>
                    @error('charge_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Billing month</label>
                    <input type="month" name="billing_month" value="{{ old('billing_month') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select x-model.number="selectedChargePropertyId" @change="syncUnitSelection('charge')" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select property...</option>
                    <template x-for="property in properties" :key="'charge-property-' + property.id">
                        <option :value="property.id" x-text="property.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <select name="property_unit_id" x-model.number="selectedChargeUnitId" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select unit...</option>
                    <template x-for="unit in filteredUnits(selectedChargePropertyId)" :key="'charge-unit-' + unit.id">
                        <option :value="unit.id" x-text="unit.label"></option>
                    </template>
                </select>
                @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label</label>
                <input type="text" name="label" value="{{ old('label') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Water / Service charge" />
                @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Units consumed</label>
                    <input type="number" name="units_consumed" value="{{ old('units_consumed') }}" step="0.001" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Usage units" />
                    @error('units_consumed')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label>
                    <input type="number" name="rate_per_unit" value="{{ old('rate_per_unit') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('rate_per_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                    <input
                        type="number"
                        name="fixed_charge"
                        value="{{ old('fixed_charge') }}"
                        step="0.01"
                        min="0"
                        :disabled="selectedChargeTemplateMode() === 'rate_only'"
                        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white disabled:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2"
                    />
                    @error('fixed_charge')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="fixedChargeHelpText()"></p>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">If usage/rate is entered, amount is calculated as (units × rate) + fixed.</p>
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save charge</button>
        </form>
                @include('property.agent.revenue.utilities._charges_list')
            </div>
            <div class="utility-sticky-bar md:hidden">
                <div class="utility-sticky-bar-inner">
                    <button type="button" @click="setTab('readings')" class="utility-sticky-btn bg-cyan-600 text-white">Readings</button>
                    <button type="button" @click="setTab('billing')" class="utility-sticky-btn bg-emerald-600 text-white">Bill</button>
                    <button type="button" @click="openPenaltyPreview()" class="utility-sticky-btn bg-amber-600 text-white">Penalties</button>
                    <button type="button" @click="setTab('charges')" class="utility-sticky-btn bg-slate-700 text-white">Charges</button>
                </div>
            </div>

            <x-property.utility.penalty-preview-modal />
        </div>