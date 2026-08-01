/**
 * Dependent property → unit → tenant filter dropdowns on workspace list pages.
 */

/** @typedef {{ id: string, label: string, property_id: string, property_name: string }} CascadeUnit */
/** @typedef {{ id: string, name: string, unit_ids: string[], property_ids: string[] }} CascadeTenant */
/** @typedef {{ units?: CascadeUnit[], tenants?: CascadeTenant[] }} CascadeCatalog */

const ALL_VALUES = new Set(['', '0']);

/**
 * @param {string|null|undefined} value
 */
function asId(value) {
    return String(value ?? '');
}

/**
 * @param {string|null|undefined} value
 */
function isAllValue(value) {
    return ALL_VALUES.has(asId(value));
}

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

    const nextValue = isAllValue(preferredValue) ? '0' : asId(preferredValue);
    select.innerHTML = '';

    const allOption = document.createElement('option');
    allOption.value = '0';
    allOption.textContent = allLabel;
    select.appendChild(allOption);

    options.forEach(({ value, label }) => {
        const option = document.createElement('option');
        option.value = asId(value);
        option.textContent = label;
        select.appendChild(option);
    });

    const hasPreferred = [...select.options].some((option) => option.value === nextValue);
    select.value = hasPreferred ? nextValue : '0';
}

/**
 * @param {HTMLElement} toolbar
 * @returns {CascadeCatalog}
 */
function readCascadeCatalog(toolbar) {
    const scriptEl = toolbar.querySelector('script[data-filter-cascade-catalog-json]');
    const raw = (scriptEl?.textContent || '').trim()
        || toolbar.getAttribute('data-filter-cascade-catalog')
        || '{}';

    try {
        const parsed = JSON.parse(raw);

        return {
            units: Array.isArray(parsed?.units) ? parsed.units : [],
            tenants: Array.isArray(parsed?.tenants) ? parsed.tenants : [],
        };
    } catch {
        return { units: [], tenants: [] };
    }
}

/**
 * @param {HTMLElement} toolbar
 */
function cascadeConfig(toolbar) {
    const catalog = readCascadeCatalog(toolbar);

    return {
        catalog,
        tenantField: toolbar.dataset.filterCascadeTenantField || 'tenant_id',
        mode: toolbar.dataset.filterCascade || 'property-unit-tenant',
    };
}

/**
 * @param {HTMLFormElement} form
 * @param {CascadeCatalog} catalog
 * @param {{ tenantField: string, mode: string }} config
 */
function wirePropertyUnitTenantCascadeForm(form, catalog, config) {
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

    const units = Array.isArray(catalog.units) ? catalog.units : [];
    const tenants = Array.isArray(catalog.tenants) ? catalog.tenants : [];

    if (units.length === 0) {
        return;
    }

    if (form.dataset.filterCascadeBound === '1') {
        return;
    }

    form.dataset.filterCascadeBound = '1';

    const sync = (resetUnit = false, resetTenant = false) => {
        const propertyId = isAllValue(propertySelect.value) ? '0' : asId(propertySelect.value);
        let unitId = resetUnit ? '0' : (isAllValue(unitSelect.value) ? '0' : asId(unitSelect.value));

        const visibleUnits = units.filter((unit) => {
            return propertyId === '0' || asId(unit.property_id) === propertyId;
        });

        rebuildSelect(
            unitSelect,
            visibleUnits.map((unit) => ({
                value: asId(unit.id),
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

        unitId = isAllValue(unitSelect.value) ? '0' : asId(unitSelect.value);
        const visibleTenants = tenants.filter((tenant) => {
            const unitIds = (tenant.unit_ids || []).map(asId);
            const propertyIds = (tenant.property_ids || []).map(asId);

            if (unitId !== '0') {
                return unitIds.includes(unitId);
            }

            if (propertyId !== '0') {
                return propertyIds.includes(propertyId);
            }

            return true;
        });

        const tenantValue = resetTenant ? '0' : (isAllValue(tenantSelect.value) ? '0' : asId(tenantSelect.value));
        rebuildSelect(
            tenantSelect,
            visibleTenants.map((tenant) => ({
                value: asId(tenant.id),
                label: tenant.name,
            })),
            'Tenant: All',
            tenantValue,
        );
    };

    propertySelect.addEventListener('change', () => {
        sync(true, true);
    });
    unitSelect.addEventListener('change', () => {
        sync(false, true);
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
    document.addEventListener('property:filter-drawer-open', () => {
        run(document);
    });
}

bindFilterCascadeLifecycle();
