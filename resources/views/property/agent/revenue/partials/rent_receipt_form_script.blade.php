<script>
    window.rentReceiptForm = function (config) {
        return {
            tenants: [],
            invoices: config.invoices || [],
            propertyId: config.old?.property_id || '',
            tenantId: config.old?.tenant_id || '',
            tenantQuery: '',
            pickerOpen: false,
            phone: '',
            rows: [],
            amount: config.old?.amount || '',
            amountTouched: Boolean(config.old?.amount),
            oldAllocations: config.old?.allocations || {},
            init() {
                const byTenant = {};
                this.invoices.forEach((invoice) => {
                    if (!invoice.property_id) {
                        return;
                    }
                    byTenant[invoice.tenant_id] = byTenant[invoice.tenant_id] || [];
                    if (!byTenant[invoice.tenant_id].includes(invoice.property_id)) {
                        byTenant[invoice.tenant_id].push(invoice.property_id);
                    }
                });
                this.tenants = (config.tenants || []).map((tenant) => ({
                    ...tenant,
                    label: [tenant.name, tenant.account, tenant.phone].filter(Boolean).join(' · '),
                    propertyIds: byTenant[tenant.id] || [],
                }));
                if (this.tenantId) {
                    const selected = this.tenants.find((tenant) => String(tenant.id) === String(this.tenantId));
                    if (selected) {
                        this.selectTenant(selected, false);
                    }
                }
            },
            get filteredTenants() {
                const query = this.tenantQuery.trim().toLowerCase();
                const propertyId = String(this.propertyId || '');
                const matches = this.tenants.filter((tenant) => {
                    if (propertyId && !(tenant.propertyIds || []).map(String).includes(propertyId)) {
                        return false;
                    }
                    if (!query) {
                        return true;
                    }
                    return (tenant.label || '').toLowerCase().includes(query);
                });
                return matches.slice(0, query ? 40 : 25);
            },
            get outstanding() {
                return this.rows.reduce((sum, row) => sum + Number(row.due || 0), 0);
            },
            get onAccount() {
                const received = Number(this.amount || 0);
                const applied = this.rows.reduce((sum, row) => sum + (Number(row.payment) || 0), 0);
                return Math.max(0, received - applied);
            },
            onPropertyChange() {
                if (!this.tenantId) {
                    return;
                }
                const selected = this.tenants.find((tenant) => String(tenant.id) === String(this.tenantId));
                if (selected && String(this.propertyId || '') && !(selected.propertyIds || []).map(String).includes(String(this.propertyId))) {
                    this.tenantId = '';
                    this.tenantQuery = '';
                    this.phone = '';
                    this.rows = [];
                    return;
                }
                this.loadRows();
            },
            selectTenant(tenant, clearPayments = true) {
                if (!tenant) {
                    return;
                }
                this.tenantId = String(tenant.id);
                this.tenantQuery = tenant.label;
                this.phone = tenant.phone || '';
                this.pickerOpen = false;
                if (clearPayments) {
                    this.oldAllocations = {};
                    this.amountTouched = false;
                    this.amount = '';
                }
                this.loadRows();
            },
            loadRows() {
                const propertyId = String(this.propertyId || '');
                this.rows = this.invoices
                    .filter((invoice) => String(invoice.tenant_id) === String(this.tenantId))
                    .filter((invoice) => !propertyId || String(invoice.property_id) === propertyId)
                    .map((invoice) => ({
                        ...invoice,
                        payment: this.oldAllocations[invoice.id] ?? this.oldAllocations[String(invoice.id)] ?? '',
                    }));
            },
            fillDue(row) {
                row.payment = Number(row.due || 0).toFixed(2);
                this.onPaymentInput();
            },
            onPaymentInput() {
                if (this.amountTouched) {
                    return;
                }
                const sum = this.rows.reduce((total, row) => total + (Number(row.payment) || 0), 0);
                this.amount = sum > 0 ? sum.toFixed(2) : '';
            },
            rowBalance(row) {
                return Math.max(0, Number(row.due || 0) - (Number(row.payment) || 0));
            },
            money(value) {
                return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            resetForm(form) {
                if (form) {
                    form.reset();
                }
                this.propertyId = '';
                this.tenantId = '';
                this.tenantQuery = '';
                this.phone = '';
                this.rows = [];
                this.amount = '';
                this.amountTouched = false;
                this.oldAllocations = {};
            },
        };
    };
</script>
