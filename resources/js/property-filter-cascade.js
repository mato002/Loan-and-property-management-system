/**
 * Dependent property → unit → tenant filter dropdowns on workspace list pages.
 */

import { submitPropertyFilterForm } from './property-auto-filter';

/** @typedef {{ id: string, label: string, property_id: string, property_name: string }} CascadeUnit */
/** @typedef {{ id: string, name: string, unit_ids: string[], property_ids: string[] }} CascadeTenant */
/** @typedef {{ units?: CascadeUnit[], tenants?: CascadeTenant[] }} CascadeCatalog */

const ALL_VALUES = new Set(['', '0']);

/**
 * @param {HTMLSelectElement|null} select
 * @param {Array<{ value: string, label: string }>} options
 * @param {string} allLabel
 * @param {string} preferredValue
 */
function rebuildSelect(select, options, allLabel, preferredValue) {
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }

    const nextValue = preferredValue || '0';
    select.innerHTML = '';

    const allOption = document.createElement('option');
    allOption.value = '0';
    allOption.textContent = allLabel;
    select.appendChild(allOption);

    options.forEach(({ value, label }) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.appendChild(option);
    });

    const hasPreferred = [...select.options].some((option) => option.value === nextValue);
    select.value = hasPreferred ? nextValue : '0';
}

/**
 * @param {string|null|undefined} value
 */
function isAllValue(value) {
    return ALL_VALUES.has(String(value ?? ''));
}

/**
 * @param {HTMLElement} toolbar
 */
function cascadeConfig(toolbar) {
    let catalog = { units: [], tenants: [] };

    try {
        catalog = JSON.parse(toolbar.dataset.filterCascadeCatalog || '{}');
    } catch {
        catalog = { units: [], tenants: [] };
    }

    return {
        catalog,
        tenantField: toolbar.dataset.filterCascadeTenantField || 'tenant_id',
        autoApply: toolbar.dataset.filterCascadeAutoApply !== 'false',
        mode: toolbar.dataset.filterCascade || 'property-unit-tenant',
    };
}

/**
 * @param {HTMLFormElement} form
 * @param {CascadeCatalog} catalog
 * @param {{ tenantField: string, autoApply: boolean, mode: string }} config
 */
function wirePropertyUnitTenantCascadeForm(form, catalog, config) {
    if (form.dataset.filterCascadeBound === '1') {
        return;
    }

    const propertySelect = form.querySelector('[name="property_id"]');
    const unitSelect = form.querySelector('[name="unit_id"]');
    const tenantSelect = config.mode === 'property-unit'
        ? null
        : form.querySelector(`[name="${config.tenantField}"]`);

    if (!(propertySelect instanceof HTMLSelectElement) || !(unitSelect instanceof HTMLSelectElement)) {
        return;
    }

    if (config.mode !== 'property-unit' && !(tenantSelect instanceof HTMLSelectElement)) {
        return;
    }

    form.dataset.filterCascadeBound = '1';

    const units = Array.isArray(catalog.units) ? catalog.units : [];
    const tenants = Array.isArray(catalog.tenants) ? catalog.tenants : [];

    const maybeAutoApply = () => {
        if (!config.autoApply) {
            return;
        }

        submitPropertyFilterForm(form, 'apply');
    };

    const sync = (resetUnit = false, resetTenant = false) => {
        const propertyId = isAllValue(propertySelect.value) ? '0' : propertySelect.value;
        let unitId = resetUnit ? '0' : (isAllValue(unitSelect.value) ? '0' : unitSelect.value);

        const visibleUnits = units.filter((unit) => propertyId === '0' || unit.property_id === propertyId);
        rebuildSelect(
            unitSelect,
            visibleUnits.map((unit) => ({
                value: unit.id,
                label: propertyId !== '0'
                    ? unit.label
                    : (unit.property_name ? `${unit.property_name}/${unit.label}` : unit.label),
            })),
            'Unit: All',
            unitId,
        );

        if (!(tenantSelect instanceof HTMLSelectElement)) {
            return;
        }

        unitId = isAllValue(unitSelect.value) ? '0' : unitSelect.value;
        const visibleTenants = tenants.filter((tenant) => {
            if (unitId !== '0') {
                return tenant.unit_ids.includes(unitId);
            }

            if (propertyId !== '0') {
                return tenant.property_ids.includes(propertyId);
            }

            return true;
        });

        const tenantValue = resetTenant ? '0' : (isAllValue(tenantSelect.value) ? '0' : tenantSelect.value);
        rebuildSelect(
            tenantSelect,
            visibleTenants.map((tenant) => ({
                value: tenant.id,
                label: tenant.name,
            })),
            'Tenant: All',
            tenantValue,
        );
    };

    propertySelect.addEventListener('change', () => {
        sync(true, true);
        maybeAutoApply();
    });
    unitSelect.addEventListener('change', () => {
        sync(false, true);
        maybeAutoApply();
    });

    sync(false, false);
}

/**
 * @param {HTMLElement} toolbar
 */
function wirePropertyUnitTenantCascadeToolbar(toolbar) {
    if (!(toolbar instanceof HTMLElement)) {
        return;
    }

    const config = cascadeConfig(toolbar);

    toolbar.querySelectorAll('form[method="get"]').forEach((form) => {
        if (form instanceof HTMLFormElement) {
            wirePropertyUnitTenantCascadeForm(form, config.catalog, config);
        }
    });
}

export function wirePropertyFilterCascades(scopeRoot) {
    const root = scopeRoot instanceof HTMLElement ? scopeRoot : document;
    root.querySelectorAll('[data-filter-cascade="property-unit-tenant"], [data-filter-cascade="property-unit"]')
        .forEach(wirePropertyUnitTenantCascadeToolbar);
}

function bindFilterCascadeLifecycle() {
    const run = (scope) => wirePropertyFilterCascades(scope || document);

    document.addEventListener('DOMContentLoaded', () => run(document));
    document.addEventListener('turbo:load', () => run(document));
    document.addEventListener('turbo:frame-load', (event) => {
        run(event.target instanceof HTMLElement ? event.target : document);
    });
    document.addEventListener('livewire:navigated', () => run(document));
    document.addEventListener('alpine:navigated', () => run(document));
}

bindFilterCascadeLifecycle();
