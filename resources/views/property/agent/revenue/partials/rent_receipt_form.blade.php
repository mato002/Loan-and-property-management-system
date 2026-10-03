@php
    $field = 'mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2';
    $receiptConfig = [
        'tenants' => $receiptTenants ?? [],
        'invoices' => $receiptInvoices ?? [],
        'old' => [
            'property_id' => (string) old('property_id', ''),
            'tenant_id' => (string) old('pm_tenant_id', ''),
            'amount' => (string) old('amount', ''),
            'allocations' => old('allocations', []),
        ],
    ];
@endphp

<form
    method="post"
    action="{{ route('property.payments.store') }}"
    class="space-y-3"
    x-data="rentReceiptForm({{ \Illuminate\Support\Js::from($receiptConfig) }})"
    x-init="init()"
>
    @csrf
    <input type="hidden" name="payment_form" value="invoice" />
    <input type="hidden" name="pm_tenant_id" :value="tenantId" />

    <div class="flex flex-wrap items-end justify-between gap-3">
        <p class="text-sm font-semibold text-rose-700">Rent receipt</p>
        <p class="text-xs text-slate-500">Receipt no. is assigned when this is saved.</p>
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        <div class="lg:col-span-2 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select name="property_id" x-model="propertyId" @change="onPropertyChange()" class="{{ $field }}">
                    <option value="">All properties</option>
                    @foreach (($properties ?? collect()) as $property)
                        <option value="{{ (int) $property->id }}">{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant / resident</label>
                <input
                    type="search"
                    x-ref="tenantInput"
                    x-model="tenantQuery"
                    @focus="openTenantPicker()"
                    @input="openTenantPicker()"
                    placeholder="Name, account, or phone"
                    autocomplete="off"
                    class="{{ $field }}"
                />
                @error('pm_tenant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <template x-teleport="body">
                <div
                    x-show="pickerOpen"
                    x-cloak
                    @click.outside="pickerOpen = false"
                    :style="tenantMenuStyle"
                    class="fixed max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-gray-900"
                    style="z-index: 8200;"
                >
                    <template x-for="tenant in filteredTenants" :key="tenant.id">
                        <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="selectTenant(tenant)">
                            <span x-text="tenant.label"></span>
                        </button>
                    </template>
                    <p x-show="filteredTenants.length === 0" class="px-3 py-2 text-xs text-slate-500">No tenant matches.</p>
                </div>
            </template>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                <input type="text" x-model="phone" readonly class="{{ $field }} bg-slate-50" />
            </div>
            <label class="flex items-end gap-2 pb-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="skip_notification" value="1" class="rounded border-slate-300" @checked(old('payment_form') !== 'invoice' || old('skip_notification')) />
                Skip SMS/email notification
            </label>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900/40">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding</p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-white" x-text="money(outstanding)"></p>
            <p class="mt-1 text-xs text-slate-500">On account <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="money(onAccount)"></span></p>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Record date</label>
            <input type="date" name="record_date" value="{{ old('record_date', now()->toDateString()) }}" required class="{{ $field }}" />
            @error('record_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Banking date</label>
            <input type="date" name="banking_date" value="{{ old('banking_date', now()->toDateString()) }}" required class="{{ $field }}" />
            @error('banking_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payment method</label>
            <select name="channel" required class="{{ $field }}">
                <option value="">Select…</option>
                @foreach (['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payment ref. no.</label>
            <input type="text" name="external_ref" value="{{ old('external_ref') }}" placeholder="Cheque, slip, or M-Pesa code" class="{{ $field }}" />
            @error('external_ref')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payer bank</label>
            <input type="text" name="payer_bank" value="{{ old('payer_bank') }}" list="rent-receipt-banks" class="{{ $field }}" />
            <datalist id="rent-receipt-banks">
                @foreach (['KCB', 'Equity', 'Co-operative', 'NCBA', 'Absa', 'Stanbic', 'Standard Chartered', 'DTB', 'I&M', 'Family Bank', 'M-Pesa'] as $bank)
                    <option value="{{ $bank }}"></option>
                @endforeach
            </datalist>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Currency</label>
            <input type="text" value="Kenyan Shilling (KES)" readonly class="{{ $field }} bg-slate-50" />
        </div>
        <div>
            <span class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount VAT</span>
            <div class="mt-2 flex gap-3 text-sm">
                <label class="inline-flex items-center gap-1"><input type="radio" name="vat_mode" value="inclusive" @checked(old('vat_mode', 'inclusive') === 'inclusive') /> Inclusive</label>
                <label class="inline-flex items-center gap-1"><input type="radio" name="vat_mode" value="exclusive" @checked(old('vat_mode') === 'exclusive') /> Exclusive</label>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount received</label>
            <input type="number" name="amount" x-model="amount" @input="amountTouched = true" step="0.01" min="0.01" required class="{{ $field }} font-semibold" />
            @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <span class="block text-xs font-medium text-slate-600 dark:text-slate-400">Receipt to</span>
            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                <label class="inline-flex items-center gap-1"><input type="radio" name="receipt_to" value="landlord" @checked(old('receipt_to') === 'landlord') /> Landlord</label>
                <label class="inline-flex items-center gap-1"><input type="radio" name="receipt_to" value="general_ledger" @checked(old('receipt_to', 'general_ledger') === 'general_ledger') /> General ledger</label>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bank / cash</label>
            <select name="bank_account_id" class="{{ $field }}">
                <option value="">Select account…</option>
                @foreach (($cashAccounts ?? collect()) as $account)
                    <option value="{{ (int) $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>
                        {{ trim(($account->code ? $account->code.' — ' : '').$account->name) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Memo</label>
            <input type="text" name="memo" value="{{ old('memo') }}" class="{{ $field }}" />
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="min-w-[920px] w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <tr>
                    <th class="px-2 py-2 font-semibold">Invoice #</th>
                    <th class="px-2 py-2 font-semibold">Inv. date</th>
                    <th class="px-2 py-2 font-semibold">Due date</th>
                    <th class="px-2 py-2 font-semibold">Particulars</th>
                    <th class="px-2 py-2 font-semibold text-right">Inv. amount</th>
                    <th class="px-2 py-2 font-semibold text-right">WHT</th>
                    <th class="px-2 py-2 font-semibold text-right">Paid</th>
                    <th class="px-2 py-2 font-semibold text-right">Amount due</th>
                    <th class="px-2 py-2 font-semibold text-right">Payment</th>
                    <th class="px-2 py-2 font-semibold text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in rows" :key="row.id">
                    <tr class="border-t border-slate-100 dark:border-slate-700">
                        <td class="px-2 py-1.5 font-medium" x-text="row.invoice_no"></td>
                        <td class="px-2 py-1.5" x-text="row.issue_date"></td>
                        <td class="px-2 py-1.5" x-text="row.due_date"></td>
                        <td class="px-2 py-1.5" x-text="row.particulars"></td>
                        <td class="px-2 py-1.5 text-right tabular-nums" x-text="money(row.amount)"></td>
                        <td class="px-2 py-1.5 text-right tabular-nums">0.00</td>
                        <td class="px-2 py-1.5 text-right tabular-nums" x-text="money(row.paid)"></td>
                        <td class="px-2 py-1.5 text-right tabular-nums">
                            <button type="button" class="font-semibold text-indigo-700 underline" @click="fillDue(row)" x-text="money(row.due)"></button>
                        </td>
                        <td class="px-2 py-1.5 text-right">
                            <input type="number" step="0.01" min="0" :max="row.due" :name="'allocations[' + row.id + ']'" x-model="row.payment" @input="onPaymentInput()" class="w-24 rounded border border-slate-200 px-2 py-1 text-right text-sm dark:border-slate-600 dark:bg-gray-900" />
                        </td>
                        <td class="px-2 py-1.5 text-right tabular-nums" x-text="money(rowBalance(row))"></td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0">
                    <td colspan="10" class="px-3 py-6 text-center text-slate-500">Select a tenant to load outstanding invoices. Leave the payment column blank to hold the whole amount on account.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700" @click="resetForm($el.form)">Reset</button>
        <button type="submit" name="save_and_print" value="0" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800">Save</button>
        <button type="submit" name="save_and_print" value="1" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save &amp; print</button>
    </div>
</form>
{{-- Script is included from the payments page so it is not trapped inside the modal template. --}}
<script>
    window.rentReceiptForm = window.rentReceiptForm || function (config) {
        return {
            tenants: [],
            invoices: config.invoices || [],
            propertyId: config.old?.property_id || '',
            tenantId: config.old?.tenant_id || '',
            tenantQuery: '',
            pickerOpen: false,
            tenantMenuStyle: 'position:fixed;z-index:8200;',
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
                this.$nextTick(() => this.placeTenantMenu());
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
                this.tenantMenuStyle = `position:fixed;z-index:8200;top:${Math.round(rect.bottom + 4)}px;left:${Math.round(left)}px;width:${Math.round(width)}px;`;
            },
            get filteredTenants() {
                const query = this.tenantQuery.trim().toLowerCase();
                const propertyId = String(this.propertyId || '');
                return this.tenants.filter((tenant) => {
                    if (propertyId && !(tenant.propertyIds || []).map(String).includes(propertyId)) {
                        return false;
                    }
                    if (!query) {
                        return true;
                    }
                    return (tenant.label || '').toLowerCase().includes(query);
                }).slice(0, 40);
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
                form.reset();
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
