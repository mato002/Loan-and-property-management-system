@php
            $utilityCreateFormHasErrors = $errors->has('charge_type')
                || $errors->has('billing_month')
                || $errors->has('property_id')
                || $errors->has('property_unit_id')
                || $errors->has('label')
                || $errors->has('amount')
                || $errors->has('notes')
                || $errors->has('current_reading')
                || $errors->has('current_readings')
                || $errors->has('previous_reading')
                || $errors->has('previous_readings')
                || $errors->has('rate_per_unit')
                || $errors->has('fixed_charge')
                || $errors->has('due_date');
            $unitOptions = collect($units ?? [])
                ->map(fn ($u) => [
                    'id' => (int) $u->id,
                    'property_id' => (int) $u->property_id,
                    'property_name' => (string) ($u->property->name ?? ''),
                    'label' => (string) $u->label,
                ])
                ->values();
            $propertyOptions = $unitOptions
                ->unique('property_id')
                ->map(fn ($u) => [
                    'id' => (int) $u['property_id'],
                    'name' => (string) $u['property_name'],
                ])
                ->sortBy('name')
                ->values()
                ->all();
            $waterChargePropertyIds = collect($waterChargePropertyIds ?? [])->map(fn ($id) => (int) $id)->all();
            $waterUnitOptions = $unitOptions
                ->filter(fn ($u) => in_array((int) $u['property_id'], $waterChargePropertyIds, true))
                ->values();
            $waterPropertyOptions = $waterUnitOptions
                ->unique('property_id')
                ->map(fn ($u) => [
                    'id' => (int) $u['property_id'],
                    'name' => (string) $u['property_name'],
                ])
                ->sortBy('name')
                ->values()
                ->all();
            $oldChargeUnitId = (int) old('property_unit_id', 0);
            $oldChargePropertyId = (int) ($unitOptions->firstWhere('id', $oldChargeUnitId)['property_id'] ?? 0);
            $oldWaterUnitId = (int) old('property_unit_id', 0);
            $oldWaterPropertyId = (int) old('property_id', ($waterUnitOptions->firstWhere('id', $oldWaterUnitId)['property_id'] ?? 0));
            $skipWaterPrevAutofill = false;
            foreach ($errors->keys() as $_wrErrKey) {
                if (! is_string($_wrErrKey)) {
                    continue;
                }
                if (in_array($_wrErrKey, ['current_reading', 'previous_reading', 'billing_month', 'current_readings'], true)) {
                    $skipWaterPrevAutofill = true;
                    break;
                }
                if (str_starts_with($_wrErrKey, 'current_readings.') || str_starts_with($_wrErrKey, 'previous_readings.')) {
                    $skipWaterPrevAutofill = true;
                    break;
                }
            }
        @endphp
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Utilities"
    subtitle="Standing extras live on each lease. Readings, billing, and charge lines are the monthly operational work."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="[]"
    empty-title="No utility charges"
    empty-hint="Use the Register, Readings, Billing, and Charge lines tabs."
>
    <x-slot name="pageModalsAttributes"
        x-data="utilityRevenuePageModals({!! \Illuminate\Support\Js::from([
            'showAddChargeForm' => $utilityCreateFormHasErrors,
            'showWaterReadingForm' => $utilityCreateFormHasErrors,
            'showBillingActionsForm' => false,
            'allUnits' => $unitOptions,
            'properties' => $propertyOptions,
            'waterUnits' => $waterUnitOptions,
            'waterProperties' => $waterPropertyOptions,
            'waterTemplatesByUnit' => $waterTemplateByUnit ?? [],
            'utilityTemplatesByUnit' => $utilityTemplateByUnit ?? [],
            'waterReadingUnitIdsByMonth' => $waterReadingUnitIdsByMonth ?? [],
            'selectedChargePropertyId' => $oldChargePropertyId,
            'selectedChargeUnitId' => $oldChargeUnitId,
            'selectedWaterPropertyId' => $oldWaterPropertyId,
            'selectedReadingUnitId' => $oldWaterUnitId,
            'selectedWaterMonth' => old('billing_month', now()->format('Y-m')),
            'defaultPreviousUrl' => route('property.revenue.utilities.water_readings.default_previous', [], true),
            'waterPrevAutofillOnMount' => ! $skipWaterPrevAutofill,
        ]) !!})"
        x-init="if (!$store.utilityUi) { Alpine.store('utilityUi', { showBillingActions: false, showWaterReadingsTable: false, showReadiness: true }); } this.bulkPrevious = this.bulkPrevious || {}; const fillBulkPrevious = async () => { const pid = Number(this.selectedWaterPropertyId || 0); const month = String(this.selectedWaterMonth || ''); if (!pid || !month || !this.defaultPreviousUrl) return; try { const url = new URL(this.defaultPreviousUrl, window.location.origin); url.searchParams.set('property_id', String(pid)); url.searchParams.set('billing_month', month); const res = await fetch(url.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }); if (!res.ok) return; const map = (await res.json()).previous_by_unit || {}; const next = {}; Object.keys(map).forEach((uid) => { const n = Number(map[uid]); if (!Number.isFinite(n)) return; next[String(uid)] = String(Number(n.toFixed(3))); }); this.bulkPrevious = next; document.querySelectorAll('[data-water-bulk-prev]').forEach((el) => { const uid = el.getAttribute('data-water-bulk-prev'); if (!uid || !Object.prototype.hasOwnProperty.call(next, uid)) return; el.value = next[uid]; }); } catch (e) {} }; const origSchedule = this.scheduleFetchWaterPrevious.bind(this); this.scheduleFetchWaterPrevious = () => { origSchedule(); setTimeout(fillBulkPrevious, 80); }; $watch('selectedReadingUnitId', () => { autofillWaterRates(); scheduleFetchWaterPrevious(); }); $watch('selectedWaterMonth', () => scheduleFetchWaterPrevious()); $watch('selectedWaterPropertyId', () => scheduleFetchWaterPrevious()); $watch('showWaterReadingForm', (open) => { if (open) scheduleFetchWaterPrevious(); }); $watch('selectedChargeUnitId', () => syncChargeDefaults()); if (this.waterPrevAutofillOnMount) { $nextTick(() => scheduleFetchWaterPrevious()); }"
    ></x-slot>

    <x-slot name="actions">
        <a
            href="{{ route('property.revenue.utilities', array_merge(request()->query(), ['ops_tab' => 'standing']), false) }}"
            data-turbo-frame="property-main"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800"
        >
            <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
            <span>Register</span>
        </a>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            data-property-modal-open="showAddChargeForm" @click="showAddChargeForm = true"
        >
            <i class="fa-solid fa-bolt" aria-hidden="true"></i>
            <span>Add charge line</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-cyan-300 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-900 hover:bg-cyan-100 dark:border-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-100 dark:hover:bg-cyan-950/60"
            data-property-modal-open="showWaterReadingForm" @click="showWaterReadingForm = true"
        >
            <i class="fa-solid fa-droplet" aria-hidden="true"></i>
            <span>Water reading</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-300 bg-violet-50 px-3 py-2 text-sm font-semibold text-violet-900 hover:bg-violet-100"
            data-property-modal-open="showBillingActionsForm" @click="showBillingActionsForm = true"
        >
            <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
            <span>Billing</span>
        </button>
    </x-slot>

    <x-slot name="modals">
        <x-property.modal
            show="showAddChargeForm"
            close="showAddChargeForm = false"
            name="utility-charge-create"
            title="Add charge line"
            max-width="2xl"
        >
<form method="post" action="{{ route('property.revenue.utilities.store') }}" x-ref="addChargeForm" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add charge line</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
                    <select name="charge_type" @change="syncChargeDefaults()" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="other" @selected(old('charge_type') === 'other')>Other</option>
                        <option value="water" @selected(old('charge_type') === 'water')>Water</option>
                        <option value="electricity" @selected(old('charge_type') === 'electricity')>Electricity</option>
                        <option value="service" @selected(old('charge_type') === 'service')>Service</option>
                        <option value="garbage" @selected(old('charge_type') === 'garbage')>Garbage</option>
                    </select>
                    @error('charge_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Billing month</label>
                    <input type="month" name="billing_month" value="{{ old('billing_month') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <select x-model.number="selectedChargePropertyId" @change="syncUnitSelection('charge')" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select property...</option>
                    <template x-for="property in properties" :key="'charge-property-' + property.id">
                        <option :value="property.id" x-text="property.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                <select name="property_unit_id" x-model.number="selectedChargeUnitId" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select unit...</option>
                    <template x-for="unit in filteredUnits(selectedChargePropertyId)" :key="'charge-unit-' + unit.id">
                        <option :value="unit.id" x-text="unit.label"></option>
                    </template>
                </select>
                @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label</label>
                <input type="text" name="label" value="{{ old('label') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Water / Service charge" />
                @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Units consumed</label>
                    <input type="number" name="units_consumed" value="{{ old('units_consumed') }}" step="0.001" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('units_consumed')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label>
                    <input type="number" name="rate_per_unit" value="{{ old('rate_per_unit') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('rate_per_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                    <input
                        type="number"
                        name="fixed_charge"
                        value="{{ old('fixed_charge') }}"
                        step="0.01"
                        min="0"
                        :disabled="selectedChargeTemplateMode() === 'rate_only'"
                        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white disabled:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2"
                    />
                    @error('fixed_charge')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save charge</button>
        </form>
        </x-property.modal>

        <x-property.modal
            show="showWaterReadingForm"
            close="showWaterReadingForm = false"
            name="utility-water-reading"
            title="Water meter reading"
            max-width="4xl"
        >
<h3 class="text-sm font-semibold text-slate-900 dark:text-white">Water meter reading</h3>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (water-enabled)</label>
                <select x-model.number="selectedWaterPropertyId" @change="syncUnitSelection('reading')" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select property...</option>
                    <template x-for="property in waterProperties" :key="'water-property-' + property.id">
                        <option :value="property.id" x-text="property.name"></option>
                    </template>
                </select>
                @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 lg:grid-cols-2 items-start">
            <form method="post" action="{{ route('property.revenue.utilities.water_readings.store') }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <select name="property_unit_id" x-model.number="selectedReadingUnitId" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select unit...</option>
                        <template x-for="unit in filteredWaterUnits()" :key="'reading-unit-' + unit.id">
                            <option :value="unit.id" :disabled="isReadingRecorded(unit.id)" x-text="isReadingRecorded(unit.id) ? `${unit.label} (already recorded)` : unit.label"></option>
                        </template>
                    </select>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label><input type="month" x-model="selectedWaterMonth" name="billing_month" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Previous reading</label>
                        <input type="number" step="0.001" min="0" name="previous_reading" x-ref="singlePreviousReadingInput" value="{{ old('previous_reading') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('previous_reading')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Current reading</label><input type="number" step="0.001" min="0" name="current_reading" value="{{ old('current_reading') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label><input x-ref="singleRatePerUnit" type="number" step="0.01" min="0" name="rate_per_unit" value="{{ old('rate_per_unit') }}" required :readonly="hasSelectedWaterTemplate()" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white read-only:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" /></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                    <input x-ref="singleFixedCharge" type="number" step="0.01" min="0" name="fixed_charge" :disabled="selectedWaterTemplateMode() === 'rate_only'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white disabled:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <button type="submit" :disabled="!hasSelectedWaterProperty()" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-400">Save reading</button>
            </form>
                <form
                    method="post"
                    action="{{ route('property.revenue.utilities.water_readings.bulk') }}"
                    class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    @csrf
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Bulk water readings</h4>
                <input type="hidden" name="property_id" :value="selectedWaterPropertyId || ''" />
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                        <input type="month" name="billing_month" x-model="selectedWaterMonth" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label>
                            <input x-ref="bulkRatePerUnit" type="number" name="rate_per_unit" value="{{ old('rate_per_unit') }}" step="0.01" min="0" required :readonly="hasSelectedWaterTemplate()" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white read-only:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                        <input x-ref="bulkFixedCharge" type="number" name="fixed_charge" value="{{ old('fixed_charge') }}" step="0.01" min="0" :disabled="selectedWaterTemplateMode() === 'rate_only'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white disabled:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                </div>
                @error('current_readings')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2">Unit</th>
                                <th class="px-3 py-2">Previous</th>
                                <th class="px-3 py-2">Current reading</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($waterUnitOptions as $unit)
                                <tr x-show="Number(selectedWaterPropertyId) === {{ (int) $unit['property_id'] }}" x-cloak>
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $unit['label'] }}</td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name="previous_readings[{{ (int) $unit['id'] }}]"
                                            data-water-bulk-prev="{{ (int) $unit['id'] }}"
                                            x-model="bulkPrevious['{{ (int) $unit['id'] }}']"
                                            value="{{ old('previous_readings.'.(int) $unit['id']) }}"
                                            class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                        />
                                        @error('previous_readings.'.(int) $unit['id'])<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name="current_readings[{{ (int) $unit['id'] }}]"
                                            value="{{ old('current_readings.'.(int) $unit['id']) }}"
                                            class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                        />
                                        @error('current_readings.'.(int) $unit['id'])<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" :disabled="!hasSelectedWaterProperty()" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-400">Save bulk readings</button>
                </form>
        </x-property.modal>

        <x-property.modal
            show="showBillingActionsForm"
            close="showBillingActionsForm = false"
            name="utility-billing-actions"
            title="Billing actions"
            max-width="3xl"
        >
            <p class="text-sm text-slate-600 mb-3">Garbage, service charge, and other fixed extras become charge lines, then invoices. Water uses meter readings separately.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <form method="post" action="{{ route('property.revenue.utilities.attached.materialize') }}" class="space-y-2 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <label class="block text-xs text-slate-500">Billing month</label>
                    <input type="month" name="billing_month" required class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2" />
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900">Create charge lines</button>
                </form>
                <form method="post" action="{{ route('property.revenue.utilities.water_invoices.generate') }}" class="space-y-2 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <label class="block text-xs text-slate-500">Billing month</label>
                    <input type="month" name="billing_month" required class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2" />
                    <label class="block text-xs text-slate-500">Due date</label>
                    <input type="date" name="due_date" required class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2" />
                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Generate water invoices</button>
                </form>
                <form method="post" action="{{ route('property.revenue.utilities.invoices.generate') }}" class="space-y-2 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <label class="block text-xs text-slate-500">Billing month</label>
                    <input type="month" name="billing_month" required class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2" />
                    <label class="block text-xs text-slate-500">Due date</label>
                    <input type="date" name="due_date" required class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2" />
                    <button type="submit" class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700">Generate other utility invoices</button>
                </form>
                <form method="post" action="{{ route('property.revenue.utilities.water_penalties.apply') }}" class="space-y-2 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <p class="text-xs text-slate-500">Overdue water penalties</p>
                    <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700" data-swal-confirm="Apply overdue water penalties now?">Apply penalties</button>
                </form>
            </div>
        </x-property.modal>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.utilities', get_defined_vars())
    </x-slot>

    <x-slot name="above">
        @include('property.agent.revenue.utilities._workspace')
    </x-slot>
</x-property.workspace>
