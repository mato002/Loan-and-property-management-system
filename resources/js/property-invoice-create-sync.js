/**
 * Lease ↔ tenant ↔ unit sync on Create Invoice (avoids change-event feedback loops).
 */

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
}

document.addEventListener('DOMContentLoaded', () => scanInvoiceCreateForms(document));
document.addEventListener('turbo:load', () => scanInvoiceCreateForms(document));
document.addEventListener('turbo:frame-load', (e) => scanInvoiceCreateForms(e.target));

export { bindInvoiceCreateForm, scanInvoiceCreateForms };
