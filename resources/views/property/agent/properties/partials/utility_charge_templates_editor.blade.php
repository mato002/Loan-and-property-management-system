@php
    $savedChargeTemplates = is_array($propertyChargeTemplates ?? null) ? $propertyChargeTemplates : [];
    $seedChargeTemplates = old('charge_templates');
    if (! is_array($seedChargeTemplates)) {
        $seedChargeTemplates = $savedChargeTemplates;
    }
    $seedChargeTemplates = array_values(array_map(static function ($row): array {
        $row = is_array($row) ? $row : [];
        $unitId = $row['property_unit_id'] ?? '';

            $rawMode = strtolower(trim((string) ($row['amount_mode'] ?? '')));
            $type = strtolower(trim((string) ($row['charge_type'] ?? 'garbage'))) ?: 'garbage';
            if (in_array($rawMode, ['variable', 'manual', 'monthly'], true)) {
                $amountMode = 'variable';
            } elseif ($rawMode === 'fixed') {
                $amountMode = 'fixed';
            } elseif ((float) ($row['fixed_charge'] ?? 0) > 0 || (float) ($row['rate_per_unit'] ?? 0) > 0) {
                $amountMode = 'fixed';
            } else {
                $amountMode = $type === 'water' ? 'variable' : 'fixed';
            }

        return [
            'property_unit_id' => ($unitId !== '' && $unitId !== null) ? (string) (int) $unitId : '',
            'charge_type' => $type,
            'label' => (string) ($row['label'] ?? ''),
            'amount_mode' => $amountMode,
            'rate_per_unit' => is_numeric($row['rate_per_unit'] ?? null) ? (string) $row['rate_per_unit'] : '',
            'fixed_charge' => is_numeric($row['fixed_charge'] ?? null) ? (string) $row['fixed_charge'] : '',
            'vat_rate' => is_numeric($row['vat_rate'] ?? null) ? (string) $row['vat_rate'] : '',
            'escalates_with_rent' => ! empty($row['escalates_with_rent']),
            'notes' => (string) ($row['notes'] ?? ''),
        ];
    }, $seedChargeTemplates));
    $unitLabelMap = collect($units ?? [])->mapWithKeys(fn ($u) => [(string) $u->id => (string) $u->label])->all();
@endphp
<form
    method="post"
    action="{{ route('property.properties.update', $property) }}"
    x-data="{
        formOpen: false,
        chargeTypeOptions: ['water', 'garbage', 'service_charge', 'other'],
        unitLabels: @js($unitLabelMap),
        charges: @js($seedChargeTemplates),
        editingIndex: null,
        draft: { property_unit_id: '', charge_type: 'garbage', label: '', amount_mode: 'fixed', rate_per_unit: '', fixed_charge: '', vat_rate: '', escalates_with_rent: false, notes: '' },
        emptyDraft() {
            return { property_unit_id: '', charge_type: 'garbage', label: '', amount_mode: 'fixed', rate_per_unit: '', fixed_charge: '', vat_rate: '', escalates_with_rent: false, notes: '' };
        },
        init() {
            this.charges.forEach((charge) => {
                const type = String(charge?.charge_type || '').trim().toLowerCase();
                if (type !== '' && !this.chargeTypeOptions.includes(type)) this.chargeTypeOptions.push(type);
            });
        },
        typeLabel(type) {
            return String(type || 'other').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        },
        scopeLabel(charge) {
            const unitId = String(charge?.property_unit_id || '');
            if (unitId === '') return 'Property default';
            return this.unitLabels[unitId] || ('Unit #' + unitId);
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
        billingLabel(charge) {
            const type = String(charge?.charge_type || '').trim().toLowerCase();
            const mode = String(charge?.amount_mode || '').trim().toLowerCase();
            if (type === 'water') return 'Meter reading';
            return mode === 'variable' ? 'Enter monthly' : 'Fixed';
        },
        onDraftTypeChange() {
            if (String(this.draft.charge_type || '').toLowerCase() === 'water' && this.draft.amount_mode === 'fixed' && !this.draft.fixed_charge) {
                this.draft.amount_mode = 'variable';
            }
        },
        addOrUpdateCharge() {
            const type = String(this.draft.charge_type || '').trim();
            if (!type) return;
            const mode = String(this.draft.amount_mode || 'fixed') === 'variable' ? 'variable' : 'fixed';
            if (mode === 'fixed' && !(Number(this.draft.fixed_charge || 0) > 0) && !(Number(this.draft.rate_per_unit || 0) > 0)) {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'warning', title: 'Amount required', text: 'Enter a fixed monthly amount, or switch billing to “Enter each month”.' });
                }
                return;
            }
            const row = {
                property_unit_id: String(this.draft.property_unit_id || ''),
                charge_type: type,
                label: String(this.draft.label || ''),
                amount_mode: mode,
                rate_per_unit: mode === 'variable' ? '' : this.draft.rate_per_unit,
                fixed_charge: mode === 'variable' ? '' : this.draft.fixed_charge,
                vat_rate: this.draft.vat_rate,
                escalates_with_rent: !!this.draft.escalates_with_rent,
                notes: String(this.draft.notes || ''),
            };
            if (this.editingIndex === null) {
                this.charges.push(row);
            } else {
                this.charges.splice(this.editingIndex, 1, row);
            }
            this.resetDraft();
        },
        editCharge(index) {
            const charge = this.charges[index];
            if (!charge) return;
            this.editingIndex = index;
            this.draft = {
                property_unit_id: String(charge.property_unit_id || ''),
                charge_type: String(charge.charge_type || 'garbage'),
                label: String(charge.label || ''),
                amount_mode: String(charge.amount_mode || 'fixed') === 'variable' ? 'variable' : 'fixed',
                rate_per_unit: charge.rate_per_unit ?? '',
                fixed_charge: charge.fixed_charge ?? '',
                vat_rate: charge.vat_rate ?? '',
                escalates_with_rent: !!charge.escalates_with_rent,
                notes: String(charge.notes || ''),
            };
            const type = this.draft.charge_type;
            if (type && !this.chargeTypeOptions.includes(type)) this.chargeTypeOptions.push(type);
            this.formOpen = true;
        },
        removeCharge(index) {
            if (this.editingIndex === index) this.resetDraft();
            else if (this.editingIndex !== null && this.editingIndex > index) this.editingIndex -= 1;
            this.charges.splice(index, 1);
        },
        async addChargeType() {
            let raw = '';
            if (window.Swal) {
                const result = await window.Swal.fire({
                    title: 'Add charge type',
                    text: 'Examples: internet, security, sewer',
                    input: 'text',
                    inputPlaceholder: 'Enter charge type',
                    showCancelButton: true,
                    confirmButtonText: 'Add',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => {
                        if (!String(value || '').trim()) return 'Charge type is required.';
                        return null;
                    },
                });
                if (!result.isConfirmed) return;
                raw = String(result.value || '').trim();
            }
            if (!raw) return;
            const normalized = String(raw).trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            if (!normalized) return;
            if (!this.chargeTypeOptions.includes(normalized)) this.chargeTypeOptions.push(normalized);
            this.draft.charge_type = normalized;
        }
    }"
    class="md:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm min-w-0"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="name" value="{{ old('name', $property->name) }}" />
    <input type="hidden" name="code" value="{{ old('code', $property->code) }}" />
    <input type="hidden" name="city" value="{{ old('city', $property->city) }}" />
    <input type="hidden" name="address_line" value="{{ old('address_line', $property->address_line) }}" />
    <input type="hidden" name="commission_percent" value="{{ old('commission_percent', $commissionPct ?? '') }}" />
    <input type="hidden" name="utility_templates_save" value="1" />
    <template x-for="(charge, index) in charges" :key="'hidden-'+index">
        <div class="hidden">
            <input type="hidden" :name="`charge_templates[${index}][property_unit_id]`" :value="charge.property_unit_id" />
            <input type="hidden" :name="`charge_templates[${index}][charge_type]`" :value="charge.charge_type" />
            <input type="hidden" :name="`charge_templates[${index}][label]`" :value="charge.label" />
            <input type="hidden" :name="`charge_templates[${index}][amount_mode]`" :value="charge.amount_mode || 'fixed'" />
            <input type="hidden" :name="`charge_templates[${index}][rate_per_unit]`" :value="charge.rate_per_unit" />
            <input type="hidden" :name="`charge_templates[${index}][fixed_charge]`" :value="charge.fixed_charge" />
            <input type="hidden" :name="`charge_templates[${index}][vat_rate]`" :value="charge.vat_rate" />
            <input type="hidden" :name="`charge_templates[${index}][escalates_with_rent]`" :value="charge.escalates_with_rent ? 1 : 0" />
            <input type="hidden" :name="`charge_templates[${index}][notes]`" :value="charge.notes" />
        </div>
    </template>

    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Saved utility charge templates</h3>
            <p class="text-xs text-slate-500">Add, edit, or delete charges, then save. Changes apply to this property’s leases and future monthly billing.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="openAdd()" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm shadow-blue-200 hover:bg-blue-700">
                <span aria-hidden="true">+</span>
                <span>Add charge</span>
            </button>
            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save utility templates</button>
        </div>
    </div>

    <div class="max-h-[22rem] overflow-auto">
        <table class="w-full min-w-[40rem] border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Charge type</th>
                    <th class="px-4 py-3">Label</th>
                    <th class="px-4 py-3">Billing</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Notes</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(charge, index) in charges" :key="'row-'+index+'-'+(charge.property_unit_id || 'all')+'-'+(charge.charge_type || '')">
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 text-slate-700" x-text="scopeLabel(charge)"></td>
                        <td class="px-4 py-3 font-medium text-slate-900" x-text="typeLabel(charge.charge_type)"></td>
                        <td class="px-4 py-3 text-slate-700" x-text="charge.label || '—'"></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                  :class="billingLabel(charge) === 'Fixed' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'"
                                  x-text="billingLabel(charge)"></span>
                        </td>
                        <td class="px-4 py-3 tabular-nums" x-text="billingLabel(charge) === 'Fixed' ? Number(charge.fixed_charge || charge.rate_per_unit || 0).toFixed(2) : '—'"></td>
                        <td class="px-4 py-3 text-slate-600" x-text="charge.notes || '—'"></td>
                        <td class="px-4 py-3 text-right" data-row-ignore-click>
                            <x-property.action-menu width="w-40">
                                <button type="button" @click="editCharge(index)" class="block w-full px-3 py-2 text-left text-xs text-blue-700 hover:bg-blue-50">Edit</button>
                                <button type="button" @click="removeCharge(index)" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50">Delete</button>
                            </x-property.action-menu>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <p x-show="charges.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">No saved utility templates yet for this property.</p>
    </div>

    <x-property.modal show="formOpen" close="resetDraft()" title="" teleport="false" max-width="lg" :close-on-escape="true">
        <x-slot:header>
            <h2 class="text-base font-semibold text-slate-900" x-text="editingIndex === null ? 'Add charge' : 'Edit charge'"></h2>
        </x-slot:header>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-slate-600">Scope <span class="font-normal text-slate-400">(optional)</span></label>
                <select x-model="draft.property_unit_id" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="">Property default</option>
                    @foreach(($units ?? []) as $u)
                        <option value="{{ $u->id }}">{{ $u->label }} (Unit)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-medium text-slate-600">Charge type <span class="text-red-600">*</span></label>
                    <button type="button" @click="addChargeType()" class="rounded border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">+</button>
                </div>
                <select x-model="draft.charge_type" @change="onDraftTypeChange()" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <template x-for="type in chargeTypeOptions" :key="'draft-type-'+type">
                        <option :value="type" x-text="typeLabel(type)"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Label <span class="font-normal text-slate-400">(optional)</span></label>
                <input x-model="draft.label" type="text" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="e.g. Water bill" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Billing</label>
                <select x-model="draft.amount_mode" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="fixed">Fixed monthly amount</option>
                    <option value="variable">Variable — enter each month</option>
                </select>
                <p class="mt-1 text-xs text-slate-500" x-show="draft.amount_mode === 'variable'">This will not auto-bill. The agent must post this charge (or a meter reading for water) every month for each occupied unit.</p>
            </div>
            <div x-show="draft.amount_mode !== 'variable'">
                <label class="block text-xs font-medium text-slate-600">Fixed charge <span class="text-red-600">*</span></label>
                <input x-model="draft.fixed_charge" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div x-show="draft.amount_mode !== 'variable'">
                <label class="block text-xs font-medium text-slate-600">Per area / measure (optional)</label>
                <input x-model="draft.rate_per_unit" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600">VAT rate %</label>
                    <input x-model="draft.vat_rate" type="number" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="e.g. 16" />
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 sm:mt-7">
                    <input type="checkbox" x-model="draft.escalates_with_rent" class="rounded border-slate-300" />
                    Escalates with rent
                </label>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Notes</label>
                <input x-model="draft.notes" type="text" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="Optional notes" />
            </div>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button type="button" @click="resetDraft()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" @click="addOrUpdateCharge()" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700" x-text="editingIndex === null ? 'Add charge' : 'Update charge'"></button>
            </div>
        </x-slot:footer>
    </x-property.modal>
</form>
