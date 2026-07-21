/**
 * Bridge vanilla lease form scripts to Alpine modal state on the lease form root.
 */

const LEASE_CARRY_FORWARD_FIELD_SELECTOR = [
    '[data-lease-arrears-panel] [name]',
    '#opening-arrears-create-wrap [name]',
    '#opening-arrears-edit-wrap [name]',
    '#opening-arrears-edit-modal [name]',
].join(', ');

export function leaseCreateFormAlpineState(config = {}) {
    const arrearsTypeLabels = config.arrearsTypeLabels ?? {};

    return {
        showOptionalFieldsModal: !!config.openOptional,
        showOpeningArrearsModal: !!config.openArrears,
        showArrearsLineModal: false,
        showChargeTypeModal: false,
        showOpeningArrearsSection: !!config.openArrearsSection,
        arrearsItems: Array.isArray(config.arrearsItems) ? config.arrearsItems : [],
        arrearsTypeLabels,
        addArrearsItem() {
            this.arrearsItems.push({ type: 'water', label: '', period: '', amount: '', reference: '' });
        },
        removeArrearsItem(index) {
            this.arrearsItems.splice(index, 1);
        },
        setDefaultLabel(item) {
            if ((item.label ?? '').trim() !== '') {
                return;
            }
            item.label = arrearsTypeLabels[item.type] ?? '';
        },
    };
}

export function leaseFormAlpine(formEl) {
    if (!(formEl instanceof HTMLElement)) {
        return null;
    }

    const root = formEl.closest('[data-lease-form-root]');
    const target = root instanceof HTMLElement ? root : formEl;

    return window.Alpine?.$data(target) ?? null;
}

export function openLeaseSubmodal(formId, which) {
    const data = leaseFormAlpine(document.getElementById(formId));
    if (!data) {
        return false;
    }

    if (which === 'optional') {
        data.showOptionalFieldsModal = true;
    } else if (which === 'arrears') {
        data.showOpeningArrearsModal = true;
    } else if (which === 'arrearsLine') {
        data.showArrearsLineModal = true;
    } else if (which === 'chargeType') {
        data.showChargeTypeModal = true;
    } else {
        return false;
    }

    return true;
}

export function closeLeaseSubmodal(formId, which) {
    const data = leaseFormAlpine(document.getElementById(formId));
    if (!data) {
        return false;
    }

    if (which === 'optional') {
        data.showOptionalFieldsModal = false;
    } else if (which === 'arrears') {
        data.showOpeningArrearsModal = false;
    } else if (which === 'arrearsLine') {
        data.showArrearsLineModal = false;
    } else if (which === 'chargeType') {
        data.showChargeTypeModal = false;
    } else {
        return false;
    }

    return true;
}

function isLeaseCarryForwardFieldName(name) {
    return typeof name === 'string' && name.startsWith('opening_');
}

function isSubmittableField(el) {
    if (
        !(el instanceof HTMLInputElement)
        && !(el instanceof HTMLSelectElement)
        && !(el instanceof HTMLTextAreaElement)
    ) {
        return false;
    }

    if (el instanceof HTMLInputElement && (el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
        return false;
    }

    return isLeaseCarryForwardFieldName(el.name);
}

function fieldHasCarryForwardValue(el) {
    const value = (el.value ?? '').trim();
    if (value === '') {
        return false;
    }

    if (el instanceof HTMLInputElement && el.type === 'number') {
        return Number.isFinite(Number(value)) && Number(value) > 0;
    }

    return true;
}

/**
 * Collect carry-forward controls that are not submitted with the main form (teleported modals).
 *
 * @return {Array<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>}
 */
export function collectExternalLeaseCarryForwardFields(form) {
    if (!(form instanceof HTMLFormElement)) {
        return [];
    }

    const formId = form.id || '';
    const seen = new Set();
    const fields = [];

    const consider = (el) => {
        if (!isSubmittableField(el)) {
            return;
        }
        if (form.contains(el)) {
            return;
        }
        const key = `${el.name}::${el.value}`;
        if (seen.has(key)) {
            return;
        }
        seen.add(key);
        fields.push(el);
    };

    if (formId !== '') {
        document.querySelectorAll(`[form="${formId}"][name]`).forEach(consider);
    }

    document.querySelectorAll(LEASE_CARRY_FORWARD_FIELD_SELECTOR).forEach(consider);

    return fields;
}

/**
 * Copy carry-forward fields from teleported modals into the main lease form so PUT/POST includes them.
 */
export function syncLeaseCarryForwardToForm(formId) {
    const form = document.getElementById(formId);
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }

    const holder = form.querySelector('[data-lease-carry-forward-sync]');
    const flag = form.querySelector('input[name="carry_forward_submitted"]');
    const touched = form.querySelector('input[name="carry_forward_touched"]');
    if (!(holder instanceof HTMLElement)) {
        return false;
    }

    holder.replaceChildren('');

    const externalFields = collectExternalLeaseCarryForwardFields(form);
    let hasOpeningValues = false;

    externalFields.forEach((el) => {
        if (fieldHasCarryForwardValue(el)) {
            hasOpeningValues = true;
        }

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = el.name;
        hidden.value = el.value ?? '';
        holder.appendChild(hidden);
    });

    form.querySelectorAll('[name^="opening_"]').forEach((el) => {
        if (!isSubmittableField(el)) {
            return;
        }
        if (fieldHasCarryForwardValue(el)) {
            hasOpeningValues = true;
        }
    });

    const touchedModal = touched instanceof HTMLInputElement && touched.value === '1';
    const shouldSubmit = touchedModal || hasOpeningValues;

    if (flag instanceof HTMLInputElement) {
        flag.value = shouldSubmit ? '1' : '0';
    }
    if (touched instanceof HTMLInputElement && shouldSubmit) {
        touched.value = '1';
    }

    return true;
}

export function markLeaseCarryForwardTouched(formId) {
    const form = document.getElementById(formId);
    const touched = form?.querySelector('input[name="carry_forward_touched"]');
    const submitted = form?.querySelector('input[name="carry_forward_submitted"]');
    if (touched instanceof HTMLInputElement) {
        touched.value = '1';
    }
    if (submitted instanceof HTMLInputElement) {
        submitted.value = '1';
    }
}

export function revealLeaseField(field, formId) {
    if (!(field instanceof HTMLElement)) {
        return;
    }

    if (field.closest('[data-lease-optional-panel]')) {
        openLeaseSubmodal(formId, 'optional');
    }

    if (field.closest('[data-lease-arrears-panel]')) {
        openLeaseSubmodal(formId, 'arrears');
    }
}

function bindLeaseCarryForwardSubmitSync() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (!form.querySelector('[data-lease-carry-forward-sync]')) {
            return;
        }
        if (form.id) {
            syncLeaseCarryForwardToForm(form.id);
        }
    }, true);
}

export const LEASE_CREATE_FORM_STORAGE_KEY = 'property.leases.createFormOpen';

export function clearLeaseCreateFormPersistedOpen() {
    try {
        sessionStorage.removeItem(LEASE_CREATE_FORM_STORAGE_KEY);
    } catch {
        // ignore private mode / quota errors
    }
}

window.leaseCreateFormAlpineState = leaseCreateFormAlpineState;
window.leaseFormAlpine = leaseFormAlpine;
window.openLeaseSubmodal = openLeaseSubmodal;
window.closeLeaseSubmodal = closeLeaseSubmodal;
window.syncLeaseCarryForwardToForm = syncLeaseCarryForwardToForm;
window.markLeaseCarryForwardTouched = markLeaseCarryForwardTouched;
window.revealLeaseField = revealLeaseField;
window.clearLeaseCreateFormPersistedOpen = clearLeaseCreateFormPersistedOpen;

bindLeaseCarryForwardSubmitSync();
