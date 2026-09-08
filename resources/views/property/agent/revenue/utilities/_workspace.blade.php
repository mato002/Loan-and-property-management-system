<div
            x-data="{
                activeTab: @js($utilityCreateFormHasErrors ? 'readings' : ((((int) ($standingLeaseCount ?? 0) > 0) || ((float) ($standingMonthlyTotal ?? 0) > 0)) ? 'standing' : 'overview')),
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
            x-init="try { const allowed = ['overview','readings','billing','standing','charges']; const q = @js($filters['ops_tab'] ?? ''); const hasStanding = @json(((int) ($standingLeaseCount ?? 0) > 0) || ((float) ($standingMonthlyTotal ?? 0) > 0)); if (q && allowed.includes(q)) activeTab = q; else if (hasStanding) activeTab = 'standing'; else { const s = sessionStorage.getItem('utility_ops_tab'); if (s && allowed.includes(s)) activeTab = s; } } catch (e) {} $watch('selectedReadingUnitId', () => { autofillWaterRates(); scheduleFetchWaterPrevious(); }); $watch('selectedWaterMonth', () => scheduleFetchWaterPrevious()); $watch('selectedChargeUnitId', () => syncChargeDefaults()); if (this.waterPrevAutofillOnMount) { $nextTick(() => scheduleFetchWaterPrevious()); }"
            class="utility-ops-shell space-y-4"
        >
            @include('property.agent.partials.filter_toolbars.utilities', get_defined_vars())

            @if (! empty($opsKpis))
                <x-property.utility.compact-kpi-strip :items="$opsKpis" />
            @endif

            <nav class="utility-ops-tabbar" aria-label="Utility operations">
                <button type="button" class="utility-ops-tab" :class="activeTab === 'standing' ? 'is-active' : ''" @click="setTab('standing')"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Register</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'overview' ? 'is-active' : ''" @click="setTab('overview')"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Overview</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'readings' ? 'is-active' : ''" @click="setTab('readings')"><i class="fa-solid fa-droplet" aria-hidden="true"></i> Readings</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'billing' ? 'is-active' : ''" @click="setTab('billing')"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> Billing</button>
                <button type="button" class="utility-ops-tab" :class="activeTab === 'charges' ? 'is-active' : ''" @click="setTab('charges')"><i class="fa-solid fa-list" aria-hidden="true"></i> Charge lines</button>
            </nav>
            <div x-show="activeTab === 'overview'" x-cloak class="space-y-4">
                @include('property.agent.revenue.utilities._tab_overview')
                <x-property.responsive.quick-action-grid>
                    <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-200 bg-white text-slate-800 hover:bg-slate-50">Ledger</a>
                    <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-teal-200 bg-teal-50 text-teal-900 hover:bg-teal-100">Reconcile</a>
                    <a href="{{ route('property.revenue.utilities.periods', absolute: false) }}" data-turbo-frame="property-main" class="quick-action-btn border border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100">Periods</a>
                    <button type="button" @click="setTab('standing')" class="quick-action-btn bg-slate-800 text-white hover:bg-slate-900">Standing register</button>
                    <button type="button" data-property-modal-open="showWaterReadingForm" @click="showWaterReadingForm = true" class="quick-action-btn bg-cyan-600 text-white hover:bg-cyan-700">Capture readings</button>
                </x-property.responsive.quick-action-grid>
            </div>
            <div x-show="activeTab === 'readings'" x-cloak class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recorded readings</h3>
                    <button type="button" data-property-modal-open="showWaterReadingForm" @click="showWaterReadingForm = true" class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-700 min-h-[44px]">Capture reading</button>
                </div>
                @include('property.agent.revenue.utilities._readings_list')
            </div>
            <div x-show="activeTab === 'billing'" x-cloak class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <h3 class="text-sm font-semibold text-slate-900">Billing</h3>
                    <p class="text-sm text-slate-600">Create charge lines and generate invoices from a modal. This tab stays a workspace, not a long form.</p>
                    <button type="button" data-property-modal-open="showBillingActionsForm" @click="showBillingActionsForm = true" class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-700 min-h-[44px]">Open billing actions</button>
                </div>
            </div>
            <div x-show="activeTab === 'charges'" x-cloak class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Posted charge lines</h3>
                    <button type="button" data-property-modal-open="showAddChargeForm" @click="showAddChargeForm = true" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 min-h-[44px]">Add charge line</button>
                </div>
                @include('property.agent.revenue.utilities._charges_list')
            </div>
            <div x-show="activeTab === 'standing'" x-cloak class="space-y-4">
                @include('property.agent.revenue.utilities._standing_register')
            </div>
            <x-property.utility.penalty-preview-modal />
        </div>