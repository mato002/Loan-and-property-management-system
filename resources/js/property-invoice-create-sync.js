/**
 * Lease ↔ tenant ↔ unit sync on Create Invoice (avoids change-event feedback loops).
 * Also binds “+” add invoice type on the create form.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function resolveInvoiceTypeSelects(button) {
    const selects = [];
    const add = (el) => {
        if (el instanceof HTMLSelectElement && !selects.includes(el)) {
            selects.push(el);
        }
    };

    if (button instanceof HTMLElement) {
        add(button.parentElement?.querySelector('[data-invoice-type-select], select[name="invoice_type"]'));
        button.closest('[data-property-modal]')
            ?.querySelectorAll('[data-invoice-type-select], select[name="invoice_type"]')
            .forEach(add);
        button.closest('form')
            ?.querySelectorAll('[data-invoice-type-select], select[name="invoice_type"]')
            .forEach(add);
    }

    if (selects.length === 0) {
        document.querySelectorAll('form[data-lease-info-url] [data-invoice-type-select], form[data-lease-info-url] select[name="invoice_type"]').forEach(add);
    }

    return selects;
}

function normalizeInvoiceTypeOptions(options, typeMeta) {
    const normalized = [];
    const seen = new Set();

    if (Array.isArray(options)) {
        options.forEach((opt) => {
            const value = String(opt?.value ?? '').trim();
            if (value === '' || seen.has(value)) {
                return;
            }
            seen.add(value);
            normalized.push({
                value,
                label: String(opt?.label ?? opt?.value ?? value),
            });
        });
    }

    const selectedValue = String(typeMeta?.value ?? '').trim();
    const selectedLabel = String(typeMeta?.label ?? selectedValue).trim();
    if (selectedValue !== '' && !seen.has(selectedValue)) {
        normalized.push({ value: selectedValue, label: selectedLabel || selectedValue });
    }

    return { normalized, selectedValue };
}

function rebuildInvoiceTypeSelect(select, options, selectedValue) {
    if (!select || !Array.isArray(options)) {
        return;
    }
    const previous = selectedValue ?? select.value;
    select.innerHTML = '';
    options.forEach((opt) => {
        const option = document.createElement('option');
        option.value = String(opt.value ?? '');
        option.textContent = String(opt.label ?? opt.value ?? '');
        select.appendChild(option);
    });
    if (previous && [...select.options].some((o) => o.value === previous)) {
        select.value = previous;
    }
}

function applyInvoiceTypeOptions(button, options, typeMeta) {
    const { normalized, selectedValue } = normalizeInvoiceTypeOptions(options, typeMeta);
    const selects = resolveInvoiceTypeSelects(button);

    selects.forEach((select) => {
        rebuildInvoiceTypeSelect(select, normalized, selectedValue);
        if (selectedValue !== '') {
            select.value = selectedValue;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

async function promptAndCreateInvoiceType(button) {
    const endpoint = button.getAttribute('data-endpoint') || '';
    if (!endpoint) {
        return;
    }

    let label = '';
    if (window.Swal) {
        const result = await window.Swal.fire({
            title: 'Add charge type',
            text: 'Name the charge (e.g. Security, Parking, Internet). It will appear in the Charge type list.',
            input: 'text',
            inputPlaceholder: 'Charge type name',
            showCancelButton: true,
            confirmButtonText: 'Add',
            cancelButtonText: 'Cancel',
            allowOutsideClick: false,
            allowEscapeKey: false,
            inputValidator: (value) => {
                if (!String(value || '').trim()) {
                    return 'Type name is required.';
                }

                return null;
            },
        });
        if (!result.isConfirmed) {
            return;
        }
        label = String(result.value || '').trim();
    } else {
        label = String(window.prompt('Add invoice type (e.g. Internet, Security)', '') || '').trim();
        if (!label) {
            return;
        }
    }

    button.disabled = true;
    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ label }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data?.ok) {
            const message = data?.message || 'Could not add invoice type.';
            if (window.swalAlert) {
                await window.swalAlert(message, 'error');
            } else if (window.Swal) {
                await window.Swal.fire({ icon: 'error', title: message });
            } else {
                window.alert(message);
            }

            return;
        }

        applyInvoiceTypeOptions(button, data.options || [], data.type || { value: label, label });
        if (window.swalAlert) {
            await window.swalAlert(`Added “${data.type?.label || label}”.`, 'success');
        }
    } catch {
        if (window.swalAlert) {
            await window.swalAlert('Could not add invoice type.', 'error');
        }
    } finally {
        button.disabled = false;
    }
}

let invoiceTypeAddDelegated = false;

/** Delegated handler (capture) — teleported modals use @click.stop on the panel, which blocks bubble. */
function bindInvoiceTypeAddButtons() {
    if (invoiceTypeAddDelegated) {
        return;
    }
    invoiceTypeAddDelegated = true;

    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            const button = target.closest('[data-invoice-type-add]');
            if (!(button instanceof HTMLElement)) {
                return;
            }
            event.preventDefault();
            promptAndCreateInvoiceType(button);
        },
        true,
    );
}

function bindInvoiceCreateForm(form) {
    if (!form || form.dataset.invoiceCreateSyncBound === '1') {
        return;
    }
    const leaseInfoTemplate = form.getAttribute('data-lease-info-url');
    if (!leaseInfoTemplate) {
        return;
    }

    form.dataset.invoiceCreateSyncBound = '1';

    const byId = (id) => document.getElementById(id);
    const byName = (name) => form.querySelector(`[name="${name}"]`);

    const tenantSel = byId('invoice-tenant') || byName('pm_tenant_id');
    const leaseSel = byId('invoice-lease') || byName('pm_lease_id');
    const unitSel = byId('invoice-unit') || byName('property_unit_id');
    const amountInput = byId('invoice-amount') || byName('amount');
    const issueInput = byId('invoice-issue-date') || byName('issue_date');
    const descInput = byId('invoice-description') || byName('description');
    const billingPeriodInput = byName('billing_period');
    const invoiceTypeSel = byName('invoice_type');

    if (!leaseSel) {
        return;
    }

    let leaseFetchToken = 0;
    let quietSync = false;

    const setFieldValueSilent = (name, value) => {
        if (window.pmSetFieldValue) {
            return window.pmSetFieldValue(name, value, form, { silent: true });
        }
        const el = byName(name);
        if (!el) {
            return false;
        }
        el.value = String(value);

        return true;
    };

    const syncLeaseFieldsFromOption = () => {
        const opt = leaseSel.options?.[leaseSel.selectedIndex];
        if (!opt?.value) {
            return false;
        }

        quietSync = true;
        try {
            const tenantId = (opt.getAttribute('data-tenant-id') || '').trim();
            const unitIdsRaw = opt.getAttribute('data-unit-ids') || '';
            const firstUnitId = unitIdsRaw.split(',').map((s) => s.trim()).find(Boolean);
            const rent = parseFloat(opt.getAttribute('data-rent') || '0') || 0;

            if (tenantId) {
                setFieldValueSilent('pm_tenant_id', tenantId);
            }
            if (firstUnitId) {
                setFieldValueSilent('property_unit_id', firstUnitId);
            }
            if (amountInput && rent > 0 && (!amountInput.value || parseFloat(amountInput.value) <= 0)) {
                amountInput.value = String(rent);
            }

            return Boolean(tenantId || firstUnitId);
        } finally {
            quietSync = false;
        }
    };

    const getSelectedUnitMeta = () => {
        const unitEl = byName('property_unit_id') || unitSel;
        if (!unitEl) {
            return { rent: null, label: '' };
        }
        const o = unitEl.options?.[unitEl.selectedIndex];
        if (!o) {
            return { rent: null, label: '' };
        }
        const rent = parseFloat(o.getAttribute('data-rent') || '0') || null;
        const label = (o.getAttribute('data-unit-label') || '').trim();

        return { rent, label };
    };

    const monthLabel = () => {
        const v = (issueInput?.value || '').trim();
        if (!v) {
            return '';
        }
        try {
            const d = new Date(`${v}T00:00:00`);
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');

            return `${y}-${m}`;
        } catch {
            return '';
        }
    };

    const maybeSetDescription = () => {
        if (!descInput) {
            return;
        }
        if (descInput.value && descInput.value.trim() !== '') {
            return;
        }
        const { label } = getSelectedUnitMeta();
        const m = monthLabel();
        if (label && m) {
            descInput.value = `Rent  -  ${label}  -  ${m}`;
        }
    };

    const maybeSetBillingPeriod = () => {
        if (!billingPeriodInput || (billingPeriodInput.value || '').trim() !== '') {
            return;
        }
        const type = (invoiceTypeSel?.value || 'rent').toLowerCase();
        if (type !== 'rent') {
            return;
        }
        const m = monthLabel();
        if (m) {
            billingPeriodInput.value = m;
        }
    };

    const syncLeaseRequired = () => {
        const leaseEl = byName('pm_lease_id') || leaseSel;
        if (!leaseEl) {
            return;
        }
        const type = (invoiceTypeSel?.value || 'rent').toLowerCase();
        leaseEl.required = type === 'rent';
    };

    const applyLease = () => {
        if (quietSync) {
            return;
        }

        const leaseId = (leaseSel.value || '').toString();
        if (!leaseId) {
            return;
        }

        syncLeaseFieldsFromOption();

        const fetchId = ++leaseFetchToken;
        const url = leaseInfoTemplate.replace('LEASE_ID', encodeURIComponent(leaseId));

        fetch(url, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((data) => {
                if (fetchId !== leaseFetchToken) {
                    return;
                }
                if (!data || !data.ok) {
                    return;
                }

                quietSync = true;
                try {
                    if (data.tenant && data.tenant.id) {
                        setFieldValueSilent('pm_tenant_id', data.tenant.id);
                    }
                    const firstUnitId =
                        data.unit && data.unit.id
                            ? data.unit.id
                            : (data.unit_ids || [])[0] || null;
                    if (firstUnitId) {
                        setFieldValueSilent('property_unit_id', firstUnitId);
                    }
                    if (amountInput && (!amountInput.value || parseFloat(amountInput.value) <= 0)) {
                        const rent = parseFloat(String(data.monthly_rent || '0')) || 0;
                        if (rent > 0) {
                            amountInput.value = String(rent);
                        }
                    }
                    maybeSetDescription();
                    maybeSetBillingPeriod();
                } finally {
                    quietSync = false;
                }
            })
            .catch(() => {});
    };

    const onTenantChange = () => {
        if (quietSync) {
            return;
        }

        const tenantEl = byName('pm_tenant_id') || tenantSel;
        const tid = (tenantEl?.value || '').toString();
        if (!tid) {
            return;
        }

        const prevLeaseId = (leaseSel.value || '').toString();
        for (let i = 0; i < leaseSel.options.length; i++) {
            const o = leaseSel.options[i];
            if ((o.getAttribute('data-tenant-id') || '') !== tid) {
                continue;
            }
            const newLeaseId = (o.value || '').toString();
            if (newLeaseId && newLeaseId !== prevLeaseId) {
                quietSync = true;
                try {
                    setFieldValueSilent('pm_lease_id', newLeaseId);
                } finally {
                    quietSync = false;
                }
                applyLease();
            }
            break;
        }
    };

    const onLeaseChange = () => {
        if (quietSync) {
            return;
        }
        applyLease();
    };

    if (tenantSel) {
        tenantSel.addEventListener('change', onTenantChange);
    }
    leaseSel.addEventListener('change', onLeaseChange);
    if (unitSel) {
        unitSel.addEventListener('change', maybeSetDescription);
    }
    if (issueInput) {
        issueInput.addEventListener('change', () => {
            maybeSetDescription();
            maybeSetBillingPeriod();
        });
    }
    if (invoiceTypeSel) {
        invoiceTypeSel.addEventListener('change', syncLeaseRequired);
    }
    syncLeaseRequired();

    const initialLease = (leaseSel.value || '').toString();
    const initialTenant = form.getAttribute('data-initial-tenant-id') || '';

    if (initialLease !== '') {
        applyLease();
    } else if (!initialTenant) {
        onTenantChange();
    }
}

function scanInvoiceCreateForms(root = document) {
    root.querySelectorAll('form[data-lease-info-url]').forEach(bindInvoiceCreateForm);
    bindInvoiceTypeAddButtons();
}

function rescanInvoiceCreateModal(event) {
    const modalId = event?.detail?.id;
    if (modalId) {
        const modal = document.querySelector(`[data-property-modal-id="${modalId}"]`);
        if (modal) {
            scanInvoiceCreateForms(modal);

            return;
        }
    }
    scanInvoiceCreateForms(document);
}

document.addEventListener('DOMContentLoaded', () => scanInvoiceCreateForms(document));
document.addEventListener('alpine:initialized', () => scanInvoiceCreateForms(document));
document.addEventListener('turbo:load', () => scanInvoiceCreateForms(document));
document.addEventListener('turbo:frame-load', (e) => scanInvoiceCreateForms(e.target));
window.addEventListener('property-modal:open', rescanInvoiceCreateModal);

export { bindInvoiceCreateForm, scanInvoiceCreateForms };
