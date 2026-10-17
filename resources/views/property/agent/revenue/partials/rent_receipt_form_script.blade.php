<script>
    window.rentReceiptForm = function (config) {
        return {
            tenants: [],
            invoices: [],
            optionsUrl: config.optionsUrl || '',
            propertyId: config.old?.property_id || '',
            tenantId: config.old?.tenant_id || '',
            tenantQuery: '',
            pickerOpen: false,
            tenantMenuStyle: 'position:fixed;z-index:12000;',
            phone: '',
            rows: [],
            loadingInvoices: false,
            amount: config.old?.amount || '',
            amountTouched: Boolean(config.old?.amount),
            oldAllocations: config.old?.allocations || {},
            creditConfirmed: false,
            init() {
                if (this.tenantId) this.loadRows();
                this._placeTenantMenu = () => {
                    if (this.pickerOpen) {
                        this.placeTenantMenu();
                    }
                };
                window.addEventListener('scroll', this._placeTenantMenu, true);
                window.addEventListener('resize', this._placeTenantMenu);
            },
            destroy() {
                window.removeEventListener('scroll', this._placeTenantMenu, true);
                window.removeEventListener('resize', this._placeTenantMenu);
            },
            openTenantPicker() {
                this.pickerOpen = true;
                this.searchTenants();
                this.$nextTick(() => this.placeTenantMenu());
            },
            async searchTenants() {
                if (!this.optionsUrl) return;
                this._tenantController?.abort();
                this._tenantController = new AbortController();
                const params = new URLSearchParams({ q: this.tenantQuery, property_id: this.propertyId || '' });
                try {
                    const response = await fetch(`${this.optionsUrl}?${params}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        signal: this._tenantController.signal,
                    });
                    if (!response.ok) throw new Error('Could not search tenants');
                    this.tenants = (await response.json()).tenants || [];
                } catch (error) {
                    if (error.name !== 'AbortError') this.tenants = [];
                }
            },
            placeTenantMenu() {
                const input = this.$refs.tenantInput;
                if (!input) {
                    return;
                }
                const rect = input.getBoundingClientRect();
                if (rect.width < 1) {
                    this.pickerOpen = false;
                    return;
                }
                const width = Math.max(rect.width, 220);
                const left = Math.min(Math.max(8, rect.left), Math.max(8, window.innerWidth - width - 8));
                const z = window.PropertyModalManager?.dropdownZIndex?.() ?? 12000;
                this.tenantMenuStyle = `position:fixed;z-index:${z};top:${Math.round(rect.bottom + 4)}px;left:${Math.round(left)}px;width:${Math.round(width)}px;`;
            },
            get filteredTenants() {
                return this.tenants.slice(0, 40);
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
            async loadRows() {
                if (!this.optionsUrl || !this.tenantId) {
                    this.rows = [];
                    return;
                }
                const tenantId = String(this.tenantId);
                const params = new URLSearchParams({ tenant_id: tenantId, property_id: this.propertyId || '' });
                const requestId = this._invoiceRequest = (this._invoiceRequest || 0) + 1;
                this.loadingInvoices = true;
                try {
                    const response = await fetch(`${this.optionsUrl}?${params}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) throw new Error('Could not load tenant invoices');
                    const data = await response.json();
                    if (requestId !== this._invoiceRequest || tenantId !== String(this.tenantId)) return;
                    const tenant = data.tenant;
                    if (tenant) {
                        this.phone = tenant.phone || '';
                        if (!this.tenantQuery) this.tenantQuery = tenant.label;
                    }
                    this.invoices = data.invoices || [];
                    this.rows = this.invoices.map((invoice) => ({
                        ...invoice,
                        payment: this.oldAllocations[invoice.id] ?? this.oldAllocations[String(invoice.id)] ?? '',
                    }));
                } catch (error) {
                    if (requestId === this._invoiceRequest) this.rows = [];
                } finally {
                    if (requestId === this._invoiceRequest) this.loadingInvoices = false;
                }
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
            onAmountInput(value) {
                this.amount = value;
                this.amountTouched = true;
                let remainingCents = Math.max(0, Math.round((Number(value) || 0) * 100));
                this.rows.forEach((row) => {
                    const dueCents = Math.max(0, Math.round((Number(row.due) || 0) * 100));
                    const paymentCents = Math.min(dueCents, remainingCents);
                    row.payment = paymentCents > 0 ? (paymentCents / 100).toFixed(2) : '';
                    remainingCents -= paymentCents;
                });
            },
            async confirmOnAccount(event) {
                if (this.creditConfirmed) {
                    this.creditConfirmed = false;
                    return;
                }

                const credit = this.onAccount;
                if (credit < 0.01) {
                    return;
                }

                event.preventDefault();
                const form = event.currentTarget;
                const submitter = event.submitter;
                const amountText = `KES ${this.money(credit)}`;
                const result = window.Swal?.fire
                    ? await window.Swal.fire({
                        icon: 'warning',
                        title: 'Record extra amount as tenant credit?',
                        text: `${amountText} will be held on the tenant account for future invoices. Continue?`,
                        showCancelButton: true,
                        confirmButtonText: 'Save as credit',
                        cancelButtonText: 'Review amount',
                        confirmButtonColor: '#2563eb',
                        cancelButtonColor: '#64748b',
                    })
                    : { isConfirmed: window.confirm(`${amountText} will be held on the tenant account as credit. Continue?`) };

                if (!result.isConfirmed) {
                    return;
                }

                this.creditConfirmed = true;
                if (typeof form.requestSubmit === 'function') {
                    submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                } else {
                    form.submit();
                }
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
