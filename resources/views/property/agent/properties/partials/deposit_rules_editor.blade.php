@php
    $savedDepositRules = is_array($propertyDepositDefinitions ?? null) ? $propertyDepositDefinitions : [];
    $seedDepositRules = old('deposit_definitions');
    if (! is_array($seedDepositRules)) {
        $seedDepositRules = $savedDepositRules;
    }
    $seedDepositRules = array_values(array_map(static function ($row): array {
        $row = is_array($row) ? $row : [];
        $unitId = $row['property_unit_id'] ?? '';

        return [
            'property_unit_id' => ($unitId !== '' && $unitId !== null) ? (string) (int) $unitId : '',
            'deposit_key' => (string) ($row['deposit_key'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'is_required' => ! empty($row['is_required']),
            'amount_mode' => in_array((string) ($row['amount_mode'] ?? 'fixed'), ['fixed', 'percent_rent'], true)
                ? (string) $row['amount_mode']
                : 'fixed',
            'amount_value' => is_numeric($row['amount_value'] ?? null) ? (string) $row['amount_value'] : '',
            'is_refundable' => array_key_exists('is_refundable', $row) ? ! empty($row['is_refundable']) : true,
            'ledger_account' => (string) ($row['ledger_account'] ?? ''),
            'sort_order' => is_numeric($row['sort_order'] ?? null) ? (int) $row['sort_order'] : 0,
            'is_active' => array_key_exists('is_active', $row) ? ! empty($row['is_active']) : true,
        ];
    }, $seedDepositRules));
    $unitLabelMap = collect($units ?? [])->mapWithKeys(fn ($u) => [(string) $u->id => (string) $u->label])->all();
@endphp
<form
    method="post"
    action="{{ route('property.properties.update', $property) }}"
    x-data="{
        formOpen: false,
        unitLabels: @js($unitLabelMap),
        deposits: @js($seedDepositRules),
        editingIndex: null,
        draft: { property_unit_id: '', deposit_key: '', label: '', is_required: false, amount_mode: 'fixed', amount_value: '', is_refundable: true, ledger_account: '', is_active: true },
        emptyDraft() {
            return { property_unit_id: '', deposit_key: '', label: '', is_required: false, amount_mode: 'fixed', amount_value: '', is_refundable: true, ledger_account: '', is_active: true };
        },
        slugKey(value) {
            return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        },
        scopeLabel(row) {
            const unitId = String(row?.property_unit_id || '');
            if (unitId === '') return 'Property default';
            return this.unitLabels[unitId] || ('Unit #' + unitId);
        },
        amountLabel(row) {
            const value = Number(row?.amount_value || 0).toFixed(2);
            return String(row?.amount_mode || '') === 'percent_rent' ? (value + '% rent') : value;
        },
        resetDraft() {
            this.editingIndex = null;
            this.draft = this.emptyDraft();
            this.formOpen = false;
        },
        openAdd() {
            this.editingIndex = null;
            this.draft = this.emptyDraft();
            this.formOpen = true;
        },
        addOrUpdate() {
            const label = String(this.draft.label || '').trim();
            let key = this.slugKey(this.draft.deposit_key || label);
            if (!label || !key) {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'warning', title: 'Label required', text: 'Enter a deposit label (and key if you want a custom code).' });
                }
                return;
            }
            const row = {
                property_unit_id: String(this.draft.property_unit_id || ''),
                deposit_key: key,
                label,
                is_required: !!this.draft.is_required,
                amount_mode: String(this.draft.amount_mode || 'fixed') === 'percent_rent' ? 'percent_rent' : 'fixed',
                amount_value: this.draft.amount_value,
                is_refundable: this.draft.is_refundable !== false,
                ledger_account: String(this.draft.ledger_account || ''),
                sort_order: this.editingIndex === null ? this.deposits.length : this.editingIndex,
                is_active: this.draft.is_active !== false,
            };
            if (this.editingIndex === null) this.deposits.push(row);
            else this.deposits.splice(this.editingIndex, 1, row);
            this.resetDraft();
        },
        editRow(index) {
            const row = this.deposits[index];
            if (!row) return;
            this.editingIndex = index;
            this.draft = {
                property_unit_id: String(row.property_unit_id || ''),
                deposit_key: String(row.deposit_key || ''),
                label: String(row.label || ''),
                is_required: !!row.is_required,
                amount_mode: String(row.amount_mode || 'fixed') === 'percent_rent' ? 'percent_rent' : 'fixed',
                amount_value: row.amount_value ?? '',
                is_refundable: row.is_refundable !== false,
                ledger_account: String(row.ledger_account || ''),
                is_active: row.is_active !== false,
            };
            this.formOpen = true;
        },
        removeRow(index) {
            if (this.editingIndex === index) this.resetDraft();
            else if (this.editingIndex !== null && this.editingIndex > index) this.editingIndex -= 1;
            this.deposits.splice(index, 1);
        }
    }"
    class="rounded-2xl border border-slate-200 bg-white shadow-sm min-w-0"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="name" value="{{ old('name', $property->name) }}" />
    <input type="hidden" name="code" value="{{ old('code', $property->code) }}" />
    <input type="hidden" name="city" value="{{ old('city', $property->city) }}" />
    <input type="hidden" name="address_line" value="{{ old('address_line', $property->address_line) }}" />
    <input type="hidden" name="commission_percent" value="{{ old('commission_percent', $commissionPct ?? '') }}" />
    <input type="hidden" name="deposit_rules_save" value="1" />
    <template x-for="(deposit, index) in deposits" :key="'hidden-deposit-'+index">
        <div class="hidden">
            <input type="hidden" :name="`deposit_definitions[${index}][property_unit_id]`" :value="deposit.property_unit_id" />
            <input type="hidden" :name="`deposit_definitions[${index}][deposit_key]`" :value="deposit.deposit_key" />
            <input type="hidden" :name="`deposit_definitions[${index}][label]`" :value="deposit.label" />
            <input type="hidden" :name="`deposit_definitions[${index}][amount_mode]`" :value="deposit.amount_mode" />
            <input type="hidden" :name="`deposit_definitions[${index}][amount_value]`" :value="deposit.amount_value" />
            <input type="hidden" :name="`deposit_definitions[${index}][ledger_account]`" :value="deposit.ledger_account" />
            <input type="hidden" :name="`deposit_definitions[${index}][is_required]`" :value="deposit.is_required ? 1 : 0" />
            <input type="hidden" :name="`deposit_definitions[${index}][is_refundable]`" :value="deposit.is_refundable ? 1 : 0" />
            <input type="hidden" :name="`deposit_definitions[${index}][sort_order]`" :value="index" />
            <input type="hidden" :name="`deposit_definitions[${index}][is_active]`" :value="deposit.is_active ? 1 : 0" />
        </div>
    </template>

    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Deposit rules</h3>
            <p class="text-xs text-slate-500">Add, edit, or delete deposit types for this property, then save.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="openAdd()" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm shadow-blue-200 hover:bg-blue-700">
                <span aria-hidden="true">+</span>
                <span>Add deposit rule</span>
            </button>
            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save deposit rules</button>
        </div>
    </div>

    <div class="max-h-[22rem] overflow-auto">
        <table class="w-full min-w-[40rem] border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Key</th>
                    <th class="px-4 py-3">Label</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Required</th>
                    <th class="px-4 py-3">Refundable</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(deposit, index) in deposits" :key="'deposit-row-'+index+'-'+(deposit.deposit_key || '')">
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 text-slate-700" x-text="scopeLabel(deposit)"></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700" x-text="deposit.deposit_key || '—'"></td>
                        <td class="px-4 py-3 font-medium text-slate-900" x-text="deposit.label || '—'"></td>
                        <td class="px-4 py-3 tabular-nums text-slate-700" x-text="amountLabel(deposit)"></td>
                        <td class="px-4 py-3 text-slate-700" x-text="deposit.is_required ? 'Yes' : 'No'"></td>
                        <td class="px-4 py-3 text-slate-700" x-text="deposit.is_refundable ? 'Yes' : 'No'"></td>
                        <td class="px-4 py-3 text-right" data-row-ignore-click>
                            <x-property.action-menu width="w-40">
                                <button type="button" @click="editRow(index)" class="block w-full px-3 py-2 text-left text-xs text-blue-700 hover:bg-blue-50">Edit</button>
                                <button type="button" @click="removeRow(index)" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50">Delete</button>
                            </x-property.action-menu>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <p x-show="deposits.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">No saved deposit rules yet for this property.</p>
    </div>

    <x-property.modal show="formOpen" close="resetDraft()" title="" teleport="false" max-width="lg" :close-on-escape="true">
        <x-slot:header>
            <h2 class="text-base font-semibold text-slate-900" x-text="editingIndex === null ? 'Add deposit rule' : 'Edit deposit rule'"></h2>
        </x-slot:header>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-slate-600">Scope</label>
                <select x-model="draft.property_unit_id" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="">Property default</option>
                    @foreach(($units ?? []) as $u)
                        <option value="{{ $u->id }}">{{ $u->label }} (Unit)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Label <span class="text-red-600">*</span></label>
                <input x-model="draft.label" type="text" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="Water deposit" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Deposit key</label>
                <input x-model="draft.deposit_key" type="text" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="water_deposit" />
                <p class="mt-1 text-xs text-slate-500">Leave blank to generate from the label.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Amount mode</label>
                    <select x-model="draft.amount_mode" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                        <option value="fixed">Fixed amount</option>
                        <option value="percent_rent">% of rent</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Default amount / value</label>
                    <input x-model="draft.amount_value" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Ledger map</label>
                <input x-model="draft.ledger_account" type="text" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="Deposit liability account" />
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="draft.is_required">
                    Required
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="draft.is_refundable">
                    Refundable
                </label>
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button type="button" @click="resetDraft()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" @click="addOrUpdate()" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700" x-text="editingIndex === null ? 'Add deposit rule' : 'Update deposit rule'"></button>
            </div>
        </x-slot:footer>
    </x-property.modal>
</form>
