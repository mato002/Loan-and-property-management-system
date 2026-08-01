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
    title="Utility Charge Lines Ledger"
    subtitle="Monthly-style charge lines per unit (water, service charge, etc.). Invoice integration can follow; rent stays on the rent roll."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="[]"
    empty-title="No utility charges"
    empty-hint="Add a line below — amounts are stored separately from core rent."
>
    <x-slot name="pageModalsAttributes"
        x-data="utilityRevenuePageModals({!! \Illuminate\Support\Js::from([
            'showAddChargeForm' => $utilityCreateFormHasErrors,
            'showWaterReadingForm' => $utilityCreateFormHasErrors,
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
        x-init="if (!$store.utilityUi) { Alpine.store('utilityUi', { showBillingActions: false, showWaterReadingsTable: false, showReadiness: true }); } $watch('selectedReadingUnitId', () => { autofillWaterRates(); scheduleFetchWaterPrevious(); }); $watch('selectedWaterMonth', () => scheduleFetchWaterPrevious()); $watch('selectedChargeUnitId', () => syncChargeDefaults()); if (this.waterPrevAutofillOnMount) { $nextTick(() => scheduleFetchWaterPrevious()); }"
    ></x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.utilities', ['filters' => $filters])
    </x-slot>

    <x-slot name="actions">
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
                    <input type="number" name="units_consumed" value="{{ old('units_consumed') }}" step="0.001" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Usage units" />
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
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="fixedChargeHelpText()"></p>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">If usage/rate is entered, amount is calculated as (units ├ù rate) + fixed.</p>
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
                <p x-show="!hasSelectedWaterProperty()" x-cloak class="mt-1 text-xs text-amber-600">Select a water-enabled property to load units.</p>
                <p x-show="hasSelectedWaterProperty() && filteredWaterUnits().length === 0" x-cloak class="mt-1 text-xs text-amber-600">No units found for this property.</p>
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
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Previous reading <span class="font-normal text-slate-400">(optional)</span></label>
                        <input type="number" step="0.001" min="0" name="previous_reading" x-ref="singlePreviousReadingInput" value="{{ old('previous_reading') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Loads from last reading when you pick unit & month" />
                        @error('previous_reading')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Auto-fills from the last saved <span class="font-medium">current</span> reading for this unit (before the billing month). Edit if you need a different baseline (new meter, correction).</p>
                    </div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Current reading</label><input type="number" step="0.001" min="0" name="current_reading" value="{{ old('current_reading') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label><input x-ref="singleRatePerUnit" type="number" step="0.01" min="0" name="rate_per_unit" value="{{ old('rate_per_unit') }}" required :readonly="hasSelectedWaterTemplate()" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white read-only:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" /></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                    <input x-ref="singleFixedCharge" type="number" step="0.01" min="0" name="fixed_charge" :disabled="selectedWaterTemplateMode() === 'rate_only'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white disabled:bg-slate-100 dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="waterFixedChargeHelpText()"></p>
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
                    <p class="text-xs text-slate-600 dark:text-slate-400">Uses the same property selected above. Fill many units and save once.</p>
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
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Shared notes (optional)</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('current_readings')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2">Unit</th>
                                <th class="px-3 py-2">Previous <span class="font-normal normal-case text-slate-400">(opt.)</span></th>
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
                                            value="{{ old('previous_readings.'.(int) $unit['id']) }}"
                                            class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                                            placeholder="Loads when month/property set"
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
                                            placeholder="Leave blank to skip"
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
    </x-slot>

    <x-slot name="secondary">
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100"
                @click="$store.utilityUi.showReadiness = !$store.utilityUi.showReadiness"
            >
                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                <span x-text="$store.utilityUi.showReadiness ? 'Hide billing readiness' : 'Billing readiness'"></span>
            </button>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-300 bg-violet-50 px-3 py-2 text-sm font-medium text-violet-800 hover:bg-violet-100"
                @click="$store.utilityUi.showBillingActions = !$store.utilityUi.showBillingActions"
            >
                <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
                <span x-text="$store.utilityUi.showBillingActions ? 'Hide billing actions' : 'Billing actions'"></span>
            </button>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200 dark:hover:bg-slate-700/80"
                @click="$store.utilityUi.showWaterReadingsTable = !$store.utilityUi.showWaterReadingsTable"
            >
                <i class="fa-solid fa-table" aria-hidden="true"></i>
                <span x-text="$store.utilityUi.showWaterReadingsTable ? 'Hide water readings' : 'Water readings'"></span>
            </button>
        </div>
    </x-slot>

<div x-show="$store.utilityUi?.showReadiness" x-cloak class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/40 p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Billing readiness</h3>
            <form method="get" action="{{ route('property.revenue.utilities', absolute: false) }}" class="flex flex-wrap items-end gap-2">
                <label class="text-xs text-slate-600">Month</label>
                <input type="month" name="rr_month" value="{{ $billingReadiness['month'] ?? now()->format('Y-m') }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}" />
                <input type="hidden" name="charge_type" value="{{ $filters['charge_type'] ?? '' }}" />
                <input type="hidden" name="month" value="{{ $filters['month'] ?? '' }}" />
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'id' }}" />
                <input type="hidden" name="dir" value="{{ $filters['dir'] ?? 'desc' }}" />
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 30 }}" />
                <input type="hidden" name="wr_q" value="{{ $filters['wr_q'] ?? '' }}" />
                <input type="hidden" name="wr_month" value="{{ $filters['wr_month'] ?? '' }}" />
                <input type="hidden" name="wr_status" value="{{ $filters['wr_status'] ?? '' }}" />
                <input type="hidden" name="wr_property_id" value="{{ $filters['wr_property_id'] ?? 0 }}" />
                <input type="hidden" name="wr_per_page" value="{{ $filters['wr_per_page'] ?? 20 }}" />
                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">Check</button>
            </form>
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <p class="text-xs text-slate-500">Water-enabled units</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ (int) ($billingReadiness['water_enabled_units'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <p class="text-xs text-slate-500">Recorded this month</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ (int) ($billingReadiness['recorded_units'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <p class="text-xs text-slate-500">Missing readings</p>
                <p class="mt-1 text-lg font-semibold text-rose-700">{{ collect($billingReadiness['missing'] ?? [])->count() }}</p>
            </div>
        </div>
        <div class="grid gap-3 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
                <h4 class="text-sm font-semibold text-slate-900">Missing units</h4>
                @if (collect($billingReadiness['missing'] ?? [])->isEmpty())
                    <p class="text-sm text-emerald-700">All water-enabled units have readings for this month.</p>
                @else
                    <ul class="space-y-1 text-sm text-slate-700">
                        @foreach (($billingReadiness['missing'] ?? []) as $row)
                            <li>{{ $row['property_name'] ?? '—' }} / {{ $row['unit_label'] ?? '—' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
                <h4 class="text-sm font-semibold text-slate-900">Usage anomalies</h4>
                @if (collect($billingReadiness['anomalies'] ?? [])->isEmpty())
                    <p class="text-sm text-emerald-700">No unusual usage patterns detected for this month.</p>
                @else
                    <ul class="space-y-1 text-sm text-slate-700">
                        @foreach (($billingReadiness['anomalies'] ?? []) as $row)
                            <li>
                                {{ $row['property_name'] ?? '—' }} / {{ $row['unit_label'] ?? '—' }}:
                                {{ number_format((float) ($row['units_used'] ?? 0), 3) }} units
                                @if ((float) ($row['avg_units_used'] ?? 0) > 0)
                                    (avg {{ number_format((float) $row['avg_units_used'], 3) }})
                                @endif
                                — {{ $row['reason'] ?? 'Check reading' }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        @include('property.agent.revenue.utilities._water_rate_adjustments')
    </div>

    <div x-show="$store.utilityUi?.showBillingActions" x-cloak class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Billing actions</h3>
        <div class="grid gap-3 lg:grid-cols-3">
            <form method="post" action="{{ route('property.revenue.utilities.water_invoices.generate') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3">
                @csrf
                <div><label class="block text-xs text-slate-500">Billing month</label><input type="month" name="billing_month" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <div><label class="block text-xs text-slate-500">Due date</label><input type="date" name="due_date" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Generate water invoices</button>
            </form>
            <form method="post" action="{{ route('property.revenue.utilities.invoices.generate') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 p-3">
                @csrf
                <div><label class="block text-xs text-slate-500">Billing month</label><input type="month" name="billing_month" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <div><label class="block text-xs text-slate-500">Due date</label><input type="date" name="due_date" required class="mt-1 rounded-lg border border-slate-200 text-sm px-3 py-2" /></div>
                <button type="submit" class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700">Generate utility invoices</button>
            </form>
            <form method="post" action="{{ route('property.revenue.utilities.water_penalties.apply') }}" class="flex items-end rounded-xl border border-slate-200 p-3">
                @csrf
                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">Apply overdue water penalties</button>
            </form>
        </div>
    </div>

    <div x-show="$store.utilityUi?.showWaterReadingsTable" x-cloak class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Water readings</h3>
            <form method="get" action="{{ route('property.revenue.utilities', absolute: false) }}" class="flex flex-wrap items-end gap-2">
                <input type="search" name="wr_q" value="{{ $filters['wr_q'] ?? '' }}" placeholder="Search unit/property/notes" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                <input type="month" name="wr_month" value="{{ $filters['wr_month'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                <select name="wr_status" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="">Status: All</option>
                    <option value="recorded" @selected(($filters['wr_status'] ?? '') === 'recorded')>Recorded</option>
                    <option value="invoiced" @selected(($filters['wr_status'] ?? '') === 'invoiced')>Invoiced</option>
                </select>
                <select name="wr_property_id" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="0">Property: All</option>
                    @foreach(($wrProperties ?? []) as $p)
                        <option value="{{ (int) $p->id }}" @selected((int) ($filters['wr_property_id'] ?? 0) === (int) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                <select name="wr_per_page" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    @foreach([10,20,30,50,100,200] as $s)
                        <option value="{{ $s }}" @selected((int) ($filters['wr_per_page'] ?? 20) === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white">Filter</button>
            </form>
        </div>

        <form method="post" action="{{ route('property.revenue.utilities.water_readings.bulk_action') }}" class="space-y-2">
            @csrf
            <div class="flex flex-wrap items-center gap-2">
                <select name="action" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="delete">Delete selected (uninvoiced only)</option>
                </select>
                <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700">Apply bulk action</button>
                @error('reading_ids')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
                <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                    <thead><tr><th class="px-3 py-2"></th><th class="px-3 py-2">Month</th><th class="px-3 py-2">Unit</th><th class="px-3 py-2">Prev</th><th class="px-3 py-2">Curr</th><th class="px-3 py-2">Used</th><th class="px-3 py-2">Amount</th><th class="px-3 py-2">Status</th></tr></thead>
                    <tbody>
                        @forelse (($waterReadings ?? collect()) as $r)
                            <tr class="border-t border-slate-100 dark:border-slate-700/80">
                                <td class="px-3 py-2"><input type="checkbox" name="reading_ids[]" value="{{ (int) $r->id }}" @disabled($r->pm_invoice_id !== null) /></td>
                                <td class="px-3 py-2">{{ $r->billing_month }}</td>
                                <td class="px-3 py-2">{{ $r->unit->property->name ?? '—' }} / {{ $r->unit->label ?? '—' }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ number_format((float) $r->previous_reading, 3) }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ number_format((float) $r->current_reading, 3) }}</td>
                                <td class="px-3 py-2">{{ number_format((float) $r->units_used, 3) }}</td>
                                <td class="px-3 py-2">{{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</td>
                                <td class="px-3 py-2">{{ ucfirst((string) $r->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-6 text-slate-500">No readings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        @if (method_exists($waterReadings, 'links'))
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Showing {{ $waterReadings->firstItem() ?? 0 }}-{{ $waterReadings->lastItem() ?? 0 }} of {{ $waterReadings->total() }} reading(s)</p>
                {{ $waterReadings->links() }}
            </div>
        @endif
    </div>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Label</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Unit</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Usage</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Added</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Amount</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Notes</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charges as $c)
                    @php
                        $ft = mb_strtolower($c->label.' '.$c->unit->property->name.' '.$c->unit->label.' '.$c->created_at->format('Y-m'));
                    @endphp
                    <tr
                        class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        data-filter-text="{{ e($ft) }}"
                    >
                        <td class="px-3 sm:px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $c->label }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">{{ $c->unit->property->name }} / {{ $c->unit->label }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                            @if (($c->units_consumed ?? null) !== null || ($c->rate_per_unit ?? null) !== null || ($c->fixed_charge ?? null) !== null)
                                U: {{ number_format((float) ($c->units_consumed ?? 0), 3) }} |
                                R: {{ number_format((float) ($c->rate_per_unit ?? 0), 2) }} |
                                F: {{ number_format((float) ($c->fixed_charge ?? 0), 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $c->created_at->format('Y-m-d') }}</td>
                        <td class="px-3 sm:px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $c->amount) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400 max-w-xs truncate">{{ $c->notes ?? '—' }}</td>
                        <td class="px-3 sm:px-4 py-3">
                            <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" data-swal-confirm="Delete this charge line?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-14 text-center text-slate-600 dark:text-slate-400">No utility charges yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-slot name="footer">
        @if (method_exists($charges, 'links'))
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $charges->firstItem() ?? 0 }}-{{ $charges->lastItem() ?? 0 }} of {{ $charges->total() }} charge line(s)
                </p>
                {{ $charges->links() }}
            </div>
        @endif
    </x-slot>
</x-property.workspace>
