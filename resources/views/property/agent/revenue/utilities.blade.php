<x-property.workspace
    title="Utilities &amp; charges"
    subtitle="Monthly-style charge lines per unit (water, service charge, etc.). Invoice integration can follow; rent stays on the rent roll."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="[]"
    empty-title="No utility charges"
    empty-hint="Add a line below — amounts are stored separately from core rent."
>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.utilities', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Search label or unit…" class="w-full min-w-0 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="charge_type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Type: All</option>
                <option value="water" @selected(($filters['charge_type'] ?? '') === 'water')>Water</option>
                <option value="service" @selected(($filters['charge_type'] ?? '') === 'service')>Service</option>
                <option value="garbage" @selected(($filters['charge_type'] ?? '') === 'garbage')>Garbage</option>
                <option value="other" @selected(($filters['charge_type'] ?? '') === 'other')>Other</option>
            </select>
            <input type="month" name="month" value="{{ $filters['month'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="id" @selected(($filters['sort'] ?? 'id') === 'id')>Sort: ID</option>
                <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Added date</option>
                <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Sort: Amount</option>
                <option value="label" @selected(($filters['sort'] ?? '') === 'label')>Sort: Label</option>
                <option value="billing_month" @selected(($filters['sort'] ?? '') === 'billing_month')>Sort: Billing month</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
                <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.revenue.utilities', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'csv']), false),
                'xlsUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'xls']), false),
                'pdfUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'pdf']), false),
            ])
        </form>
    </x-slot>

    <x-slot name="above">
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
        @endphp
        <div
            x-data="{
                showUtilityCreateForms: @js($utilityCreateFormHasErrors),
                allUnits: @js($unitOptions),
                properties: @js($propertyOptions),
                waterUnits: @js($waterUnitOptions),
                waterProperties: @js($waterPropertyOptions),
                waterTemplatesByUnit: @js($waterTemplateByUnit ?? []),
                selectedChargePropertyId: @js($oldChargePropertyId),
                selectedChargeUnitId: @js($oldChargeUnitId),
                selectedWaterPropertyId: @js($oldWaterPropertyId),
                selectedReadingUnitId: @js($oldWaterUnitId),
                filteredUnits(propertyId) {
                    const pid = Number(propertyId || 0);
                    if (!pid) return [];
                    return this.allUnits.filter((unit) => Number(unit.property_id) === pid);
                },
                filteredWaterUnits() {
                    const pid = Number(this.selectedWaterPropertyId || 0);
                    if (!pid) return [];
                    return this.waterUnits.filter((unit) => Number(unit.property_id) === pid);
                },
                hasSelectedWaterProperty() {
                    return Number(this.selectedWaterPropertyId || 0) > 0;
                },
                syncUnitSelection(scope) {
                    if (scope === 'charge') {
                        const exists = this.filteredUnits(this.selectedChargePropertyId).some((unit) => Number(unit.id) === Number(this.selectedChargeUnitId));
                        if (!exists) this.selectedChargeUnitId = 0;
                        return;
                    }
                    const exists = this.filteredWaterUnits().some((unit) => Number(unit.id) === Number(this.selectedReadingUnitId));
                    if (!exists) this.selectedReadingUnitId = 0;
                    this.autofillWaterRates();
                },
                autofillWaterRates() {
                    const unitId = String(this.selectedReadingUnitId || '');
                    if (!unitId || !this.waterTemplatesByUnit[unitId]) return;
                    const tpl = this.waterTemplatesByUnit[unitId];
                    const singleRate = this.$refs.singleRatePerUnit;
                    const singleFixed = this.$refs.singleFixedCharge;
                    const bulkRate = this.$refs.bulkRatePerUnit;
                    const bulkFixed = this.$refs.bulkFixedCharge;
                    if (singleRate && (singleRate.value === '' || Number(singleRate.value) === 0)) singleRate.value = tpl.rate_per_unit ?? '';
                    if (singleFixed && singleFixed.value === '') singleFixed.value = tpl.fixed_charge ?? '';
                    if (bulkRate && (bulkRate.value === '' || Number(bulkRate.value) === 0)) bulkRate.value = tpl.rate_per_unit ?? '';
                    if (bulkFixed && bulkFixed.value === '') bulkFixed.value = tpl.fixed_charge ?? '';
                },
            }"
            x-init="$watch('selectedReadingUnitId', () => autofillWaterRates())"
            class="space-y-4"
        >
        <div class="max-w-2xl">
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-4 text-base font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 sm:w-auto"
                @click="showUtilityCreateForms = !showUtilityCreateForms"
            >
                <i class="fa-solid fa-bolt text-lg" aria-hidden="true"></i>
                <span x-text="showUtilityCreateForms ? 'Hide utility forms' : 'Add utility charge / reading'"></span>
            </button>
        </div>

        <div x-show="showUtilityCreateForms" x-cloak class="grid gap-4 lg:grid-cols-2">
        <form method="post" action="{{ route('property.revenue.utilities.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add charge line</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
                    <select name="charge_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="other" @selected(old('charge_type') === 'other')>Other</option>
                        <option value="water" @selected(old('charge_type') === 'water')>Water</option>
                        <option value="service" @selected(old('charge_type') === 'service')>Service</option>
                        <option value="garbage" @selected(old('charge_type') === 'garbage')>Garbage</option>
                    </select>
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
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save charge</button>
        </form>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
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
            <form method="post" action="{{ route('property.revenue.utilities.water_readings.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <select name="property_unit_id" x-model.number="selectedReadingUnitId" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select unit...</option>
                        <template x-for="unit in filteredWaterUnits()" :key="'reading-unit-' + unit.id">
                            <option :value="unit.id" x-text="unit.label"></option>
                        </template>
                    </select>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label><input type="month" name="billing_month" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Current reading</label><input type="number" step="0.001" min="0" name="current_reading" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                    <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label><input x-ref="singleRatePerUnit" type="number" step="0.01" min="0" name="rate_per_unit" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                    <input x-ref="singleFixedCharge" type="number" step="0.01" min="0" name="fixed_charge" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Rate/fixed auto-fill from property water template when available.</p>
                </div>
                <button type="submit" :disabled="!hasSelectedWaterProperty()" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-400">Save reading</button>
            </form>
            <form method="post" action="{{ route('property.revenue.utilities.water_readings.bulk') }}" class="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                @csrf
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Bulk water readings</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400">Uses the same property selected above. Fill many units and save once.</p>
                <input type="hidden" name="property_id" :value="selectedWaterPropertyId || ''" />
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Month</label>
                        <input type="month" name="billing_month" value="{{ old('billing_month') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label>
                        <input x-ref="bulkRatePerUnit" type="number" name="rate_per_unit" value="{{ old('rate_per_unit') }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                        <input x-ref="bulkFixedCharge" type="number" name="fixed_charge" value="{{ old('fixed_charge') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
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
            <form method="post" action="{{ route('property.revenue.utilities.water_invoices.generate') }}" class="flex flex-wrap items-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                @csrf
                <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Billing month</label><input type="month" name="billing_month" required class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                <div><label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due date</label><input type="date" name="due_date" required class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" /></div>
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Generate water invoices</button>
            </form>
            <form method="post" action="{{ route('property.revenue.utilities.water_penalties.apply') }}">
                @csrf
                <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">Apply overdue water penalties</button>
            </form>
        </div>
        </div>
        </div>
    </x-slot>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Label</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Unit</th>
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
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $c->created_at->format('Y-m-d') }}</td>
                        <td class="px-3 sm:px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $c->amount) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400 max-w-xs truncate">{{ $c->notes ?? '—' }}</td>
                        <td class="px-3 sm:px-4 py-3">
                            <form method="post" action="{{ route('property.revenue.utilities.destroy', $c) }}" onsubmit="return confirm('Delete this charge line?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-14 text-center text-slate-600 dark:text-slate-400">No utility charges yet.</td>
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

    <div class="mt-6 overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">Water readings</h3>
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead><tr><th class="px-3 py-2">Month</th><th class="px-3 py-2">Unit</th><th class="px-3 py-2">Used</th><th class="px-3 py-2">Amount</th><th class="px-3 py-2">Status</th></tr></thead>
            <tbody>
                @forelse (($waterReadings ?? collect()) as $r)
                    <tr class="border-t border-slate-100 dark:border-slate-700/80">
                        <td class="px-3 py-2">{{ $r->billing_month }}</td>
                        <td class="px-3 py-2">{{ $r->unit->property->name ?? '—' }} / {{ $r->unit->label ?? '—' }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $r->units_used, 3) }}</td>
                        <td class="px-3 py-2">{{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</td>
                        <td class="px-3 py-2">{{ ucfirst((string) $r->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-slate-500">No readings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
