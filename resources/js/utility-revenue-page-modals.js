/**
 * Utility revenue list page — charge/water modal Alpine state.
 * Config is passed from Blade via Js::from() (slot attributes cannot use @js).
 */

document.addEventListener('alpine:init', () => {
    window.Alpine.data('utilityRevenuePageModals', (config = {}) => ({
        showAddChargeForm: Boolean(config.showAddChargeForm),
        showWaterReadingForm: Boolean(config.showWaterReadingForm),
        allUnits: config.allUnits ?? [],
        properties: config.properties ?? [],
        waterUnits: config.waterUnits ?? [],
        waterProperties: config.waterProperties ?? [],
        waterTemplatesByUnit: config.waterTemplatesByUnit ?? {},
        utilityTemplatesByUnit: config.utilityTemplatesByUnit ?? {},
        waterReadingUnitIdsByMonth: config.waterReadingUnitIdsByMonth ?? {},
        selectedChargePropertyId: Number(config.selectedChargePropertyId ?? 0),
        selectedChargeUnitId: Number(config.selectedChargeUnitId ?? 0),
        selectedWaterPropertyId: Number(config.selectedWaterPropertyId ?? 0),
        selectedReadingUnitId: Number(config.selectedReadingUnitId ?? 0),
        selectedWaterMonth: String(config.selectedWaterMonth ?? ''),
        showBulkWaterReadings: false,
        defaultPreviousUrl: String(config.defaultPreviousUrl ?? ''),
        waterPrevAutofillOnMount: Boolean(config.waterPrevAutofillOnMount),
        _prevFetchTimer: null,
        _prevFetchToken: 0,

        filteredUnits(propertyId) {
            const pid = Number(propertyId || 0);
            if (!pid) {
                return [];
            }

            return this.allUnits.filter((unit) => Number(unit.property_id) === pid);
        },

        filteredWaterUnits() {
            const pid = Number(this.selectedWaterPropertyId || 0);
            if (!pid) {
                return [];
            }

            return this.waterUnits.filter((unit) => Number(unit.property_id) === pid);
        },

        hasSelectedWaterProperty() {
            return Number(this.selectedWaterPropertyId || 0) > 0;
        },

        syncUnitSelection(scope) {
            if (scope === 'charge') {
                const units = this.filteredUnits(this.selectedChargePropertyId);
                const exists = units.some((unit) => Number(unit.id) === Number(this.selectedChargeUnitId));
                if (!exists) {
                    this.selectedChargeUnitId = Number(units[0]?.id || 0);
                }
                this.syncChargeDefaults();

                return;
            }

            const waterUnits = this.filteredWaterUnits();
            const exists = waterUnits.some((unit) => Number(unit.id) === Number(this.selectedReadingUnitId));
            if (!exists) {
                this.selectedReadingUnitId = Number(waterUnits[0]?.id || 0);
            }
            this.autofillWaterRates();
            this.scheduleFetchWaterPrevious();
        },

        syncChargeDefaults() {
            const unitId = String(this.selectedChargeUnitId || '');
            const form = this.$refs.addChargeForm;
            if (!unitId || !form) {
                return;
            }

            const typeEl = form.querySelector('select[name=charge_type]');
            const rateEl = form.querySelector('input[name=rate_per_unit]');
            const unitsEl = form.querySelector('input[name=units_consumed]');
            const fixedEl = form.querySelector('input[name=fixed_charge]');
            const amountEl = form.querySelector('input[name=amount]');
            if (!(typeEl instanceof HTMLSelectElement)) {
                return;
            }

            const type = String(typeEl.value || '').toLowerCase();
            const byType = this.utilityTemplatesByUnit[unitId] || {};
            const tpl = byType[type];
            if (!tpl) {
                return;
            }

            if (rateEl && (rateEl.value === '' || Number(rateEl.value) === 0)) {
                rateEl.value = Number(tpl.rate_per_unit || 0).toFixed(2);
            }

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
                if (calc > 0) {
                    amountEl.value = Number(calc).toFixed(2);
                }
            }
        },

        selectedChargeTemplate() {
            const unitId = String(this.selectedChargeUnitId || '');
            const form = this.$refs.addChargeForm;
            if (!unitId || !form) {
                return null;
            }

            const typeEl = form.querySelector('select[name=charge_type]');
            if (!(typeEl instanceof HTMLSelectElement)) {
                return null;
            }

            const type = String(typeEl.value || '').toLowerCase();
            const byType = this.utilityTemplatesByUnit[unitId] || {};

            return byType[type] || null;
        },

        selectedChargeTemplateMode() {
            const tpl = this.selectedChargeTemplate();
            if (!tpl) {
                return 'mixed';
            }

            const rate = Number(tpl.rate_per_unit || 0);
            const fixed = Number(tpl.fixed_charge || 0);
            if (rate > 0 && fixed <= 0) {
                return 'rate_only';
            }
            if (fixed > 0 && rate <= 0) {
                return 'fixed_only';
            }

            return 'mixed';
        },

        fixedChargeHelpText() {
            const mode = this.selectedChargeTemplateMode();
            if (mode === 'rate_only') {
                return 'This utility is configured as rate per unit only.';
            }
            if (mode === 'fixed_only') {
                return 'This utility includes a fixed component.';
            }

            return 'Use this when the utility has a fixed component.';
        },

        autofillWaterRates() {
            const unitId = String(this.selectedReadingUnitId || '');
            if (!unitId) {
                return;
            }

            const tpl = this.waterTemplatesByUnit[unitId] || this.utilityTemplatesByUnit?.[unitId]?.water || null;
            if (!tpl) {
                return;
            }

            const singleRate = this.$refs.singleRatePerUnit;
            const singleFixed = this.$refs.singleFixedCharge;
            const bulkRate = this.$refs.bulkRatePerUnit;
            const bulkFixed = this.$refs.bulkFixedCharge;
            if (singleRate) {
                singleRate.value = Number(tpl.rate_per_unit || 0).toFixed(2);
            }
            if (singleFixed && singleFixed.value === '') {
                singleFixed.value = Number(tpl.fixed_charge || 0).toFixed(2);
            }
            if (bulkRate) {
                bulkRate.value = Number(tpl.rate_per_unit || 0).toFixed(2);
            }
            if (bulkFixed && bulkFixed.value === '') {
                bulkFixed.value = Number(tpl.fixed_charge || 0).toFixed(2);
            }
        },

        hasSelectedWaterTemplate() {
            const unitId = String(this.selectedReadingUnitId || '');
            if (!unitId) {
                return false;
            }

            return !!(this.waterTemplatesByUnit[unitId] || this.utilityTemplatesByUnit?.[unitId]?.water);
        },

        selectedWaterTemplateMode() {
            const unitId = String(this.selectedReadingUnitId || '');
            const tpl = unitId ? (this.waterTemplatesByUnit[unitId] || this.utilityTemplatesByUnit?.[unitId]?.water) : null;
            if (!tpl) {
                return 'mixed';
            }

            const rate = Number(tpl.rate_per_unit || 0);
            const fixed = Number(tpl.fixed_charge || 0);
            if (rate > 0 && fixed <= 0) {
                return 'rate_only';
            }
            if (fixed > 0 && rate <= 0) {
                return 'fixed_only';
            }

            return 'mixed';
        },

        waterFixedChargeHelpText() {
            const mode = this.selectedWaterTemplateMode();
            if (mode === 'rate_only') {
                return 'This unit water rule is rate-per-unit only.';
            }
            if (mode === 'fixed_only') {
                return 'This unit water rule includes only fixed charge.';
            }

            return 'Rate/fixed auto-fill from property water template when available.';
        },

        formatWaterPreviousReading(n) {
            const x = Number(n);
            if (!Number.isFinite(x)) {
                return '';
            }

            return String(Number(x.toFixed(3)));
        },

        scheduleFetchWaterPrevious() {
            clearTimeout(this._prevFetchTimer);
            this._prevFetchTimer = setTimeout(() => this.fetchWaterPreviousDefaults(), 220);
        },

        async fetchWaterPreviousDefaults() {
            const pid = Number(this.selectedWaterPropertyId || 0);
            const month = String(this.selectedWaterMonth || '');
            if (!pid || !month || !this.defaultPreviousUrl) {
                return;
            }

            const token = ++this._prevFetchToken;
            const url = new URL(this.defaultPreviousUrl, window.location.origin);
            url.searchParams.set('property_id', String(pid));
            url.searchParams.set('billing_month', month);

            try {
                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    return;
                }

                const data = await res.json();
                if (token !== this._prevFetchToken) {
                    return;
                }

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
                        if (!(el instanceof HTMLInputElement)) {
                            return;
                        }

                        const uid = el.getAttribute('data-water-bulk-prev');
                        if (!uid || !Object.prototype.hasOwnProperty.call(map, uid)) {
                            return;
                        }

                        el.value = this.formatWaterPreviousReading(map[uid]);
                    });
                }
            } catch (e) {
                if (window?.console?.debug) {
                    console.debug('Water previous reading autofill failed', e);
                }
            }
        },

        isReadingRecorded(unitId) {
            const month = String(this.selectedWaterMonth || '');
            if (!month) {
                return false;
            }

            const ids = Array.isArray(this.waterReadingUnitIdsByMonth[month])
                ? this.waterReadingUnitIdsByMonth[month]
                : [];

            return ids.includes(Number(unitId));
        },
    }));
});
