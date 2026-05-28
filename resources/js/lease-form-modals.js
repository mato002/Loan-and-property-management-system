/**
 * Bridge vanilla lease form scripts to Alpine modal state on the lease form root.
 */

export function leaseFormAlpine(formEl) {
    if (!(formEl instanceof HTMLElement)) {
        return null;
    }

    return window.Alpine?.$data(formEl) ?? null;
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
    if (!(holder instanceof HTMLElement) || !(flag instanceof HTMLInputElement)) {
        return false;
    }

    holder.replaceChildren('');

    document.querySelectorAll(`[form="${formId}"][name]`).forEach((el) => {
        if (
            !(el instanceof HTMLInputElement)
            && !(el instanceof HTMLSelectElement)
            && !(el instanceof HTMLTextAreaElement)
        ) {
            return;
        }

        const name = el.name || '';
        if (!name.startsWith('opening_') && name !== 'carry_forward_touched') {
            return;
        }
        if (name === 'carry_forward_submitted') {
            return;
        }

        if (el instanceof HTMLInputElement && (el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
            return;
        }

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = el.value ?? '';
        holder.appendChild(hidden);
    });

    const touchedModal = touched instanceof HTMLInputElement && touched.value === '1';
    const hasOpeningFields = holder.querySelector('input[name^="opening_arrears["]') !== null;
    flag.value = touchedModal || hasOpeningFields ? '1' : '0';

    return true;
}

export function markLeaseCarryForwardTouched(formId) {
    const form = document.getElementById(formId);
    const touched = form?.querySelector('input[name="carry_forward_touched"]');
    if (touched instanceof HTMLInputElement) {
        touched.value = '1';
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

window.leaseFormAlpine = leaseFormAlpine;
window.openLeaseSubmodal = openLeaseSubmodal;
window.closeLeaseSubmodal = closeLeaseSubmodal;
window.syncLeaseCarryForwardToForm = syncLeaseCarryForwardToForm;
window.markLeaseCarryForwardTouched = markLeaseCarryForwardTouched;
window.revealLeaseField = revealLeaseField;
