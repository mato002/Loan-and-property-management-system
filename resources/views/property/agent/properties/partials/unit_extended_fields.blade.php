@php
    $u = $unit ?? null;
    $inputClass = 'mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2';
    $labelClass = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
    $seedMeters = old('extra_meters', $u?->extra_meters ?? []);
    if (! is_array($seedMeters)) {
        $seedMeters = [];
    }
    $seedMeters = array_values(array_map(static function ($row): array {
        $row = is_array($row) ? $row : [];

        return [
            'meter_no' => (string) ($row['meter_no'] ?? ''),
            'reading_setup' => (string) ($row['reading_setup'] ?? ''),
        ];
    }, $seedMeters));
    $seedFeatures = old('features', $u?->features ?? []);
    if (! is_array($seedFeatures)) {
        $seedFeatures = [];
    }
    $seedFeatures = array_values(array_map(static function ($row): array {
        $row = is_array($row) ? $row : [];

        return [
            'name' => (string) ($row['name'] ?? ''),
            'feature_type' => (string) ($row['feature_type'] ?? ''),
        ];
    }, $seedFeatures));
    $frequencies = \App\Models\PropertyUnit::chargeFrequencyOptions();
@endphp

<div class="grid gap-3 sm:grid-cols-2 sm:col-span-2">
    <div>
        <label class="{{ $labelClass }}">Market rent (KES)</label>
        <input type="number" name="market_rent" value="{{ old('market_rent', $u?->market_rent ?? '') }}" step="0.01" min="0" class="{{ $inputClass }}" placeholder="Listing / target rent" />
        @error('market_rent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Area (sq ft)</label>
        <input type="number" name="legacy_area" value="{{ old('legacy_area', $u?->legacy_area ?? '') }}" step="0.01" min="0" class="{{ $inputClass }}" />
        @error('legacy_area')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Specified floor</label>
        <input type="text" name="floor" value="{{ old('floor', $u?->floor ?? '') }}" maxlength="32" class="{{ $inputClass }}" placeholder="e.g. Ground, 1, 2" />
        @error('floor')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">General floor no.</label>
        <input type="text" name="floor_number" value="{{ old('floor_number', $u?->floor_number ?? '') }}" maxlength="32" class="{{ $inputClass }}" />
        @error('floor_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Available from</label>
        <input type="date" name="available_from" value="{{ old('available_from', optional($u?->available_from)?->format('Y-m-d')) }}" class="{{ $inputClass }}" />
        @error('available_from')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Take-on letting date</label>
        <input type="date" name="take_on_letting_date" value="{{ old('take_on_letting_date', optional($u?->take_on_letting_date)?->format('Y-m-d')) }}" class="{{ $inputClass }}" />
        @error('take_on_letting_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <input type="hidden" name="furnished" value="0" />
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" name="furnished" value="1" @checked(old('furnished', $u?->furnished ?? false)) class="rounded border-slate-300" />
            Furnished unit
        </label>
        @error('furnished')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<details class="sm:col-span-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/70 dark:bg-slate-900/40 p-3" {{ $u ? 'open' : '' }}>
    <summary class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">More unit records (baths, parking, meters, notes, features)</summary>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div>
            <label class="{{ $labelClass }}">Bathrooms</label>
            <input type="number" name="bathrooms" min="0" max="20" value="{{ old('bathrooms', $u?->bathrooms ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Car spaces / parking</label>
            <input type="number" name="parking_spaces" min="0" max="50" value="{{ old('parking_spaces', $u?->parking_spaces ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Unit sequence</label>
            <input type="number" name="unit_sequence" min="0" value="{{ old('unit_sequence', $u?->unit_sequence ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Rent per area (KES)</label>
            <input type="number" step="0.01" min="0" name="rent_per_area" value="{{ old('rent_per_area', $u?->rent_per_area ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Charge frequency</label>
            <select name="charge_frequency" class="{{ $inputClass }}">
                <option value="">Select…</option>
                @foreach ($frequencies as $value => $label)
                    <option value="{{ $value }}" @selected(old('charge_frequency', $u?->charge_frequency ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Record only. Billing in this system stays monthly unless you change leases/utilities.</p>
        </div>
        <div>
            <label class="{{ $labelClass }}">Electricity account</label>
            <input type="text" name="electricity_account" value="{{ old('electricity_account', $u?->electricity_account ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Electricity meter</label>
            <input type="text" name="electricity_meter" value="{{ old('electricity_meter', $u?->electricity_meter ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Water account</label>
            <input type="text" name="water_account" value="{{ old('water_account', $u?->water_account ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div>
            <label class="{{ $labelClass }}">Water meter</label>
            <input type="text" name="water_meter" value="{{ old('water_meter', $u?->water_meter ?? '') }}" class="{{ $inputClass }}" />
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $labelClass }}">Additional notes</label>
            <textarea name="notes" rows="2" class="{{ $inputClass }}">{{ old('notes', $u?->notes ?? '') }}</textarea>
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $labelClass }}">Unit location notes</label>
            <textarea name="location_notes" rows="2" class="{{ $inputClass }}">{{ old('location_notes', $u?->location_notes ?? '') }}</textarea>
        </div>
    </div>

    <div
        class="mt-3 space-y-2"
        x-data="{
            meters: @js($seedMeters),
            addMeter() { this.meters.push({ meter_no: '', reading_setup: '' }) },
            removeMeter(i) { this.meters.splice(i, 1) }
        }"
    >
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Extra meters</p>
            <button type="button" @click="addMeter()" class="rounded border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Add meter</button>
        </div>
        <template x-for="(meter, index) in meters" :key="'m-'+index">
            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                <input type="text" :name="'extra_meters['+index+'][meter_no]'" x-model="meter.meter_no" class="{{ $inputClass }}" placeholder="Meter no." />
                <input type="text" :name="'extra_meters['+index+'][reading_setup]'" x-model="meter.reading_setup" class="{{ $inputClass }}" placeholder="Reading setup" />
                <button type="button" @click="removeMeter(index)" class="text-xs font-semibold text-rose-700">Remove</button>
            </div>
        </template>
        <p x-show="meters.length === 0" class="text-xs text-slate-500">No extra meters. Water readings for billing stay under Collections → Utilities.</p>
    </div>

    <div
        class="mt-3 space-y-2"
        x-data="{
            features: @js($seedFeatures),
            addFeature() { this.features.push({ name: '', feature_type: '' }) },
            removeFeature(i) { this.features.splice(i, 1) }
        }"
    >
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Unit features</p>
            <button type="button" @click="addFeature()" class="rounded border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Add feature</button>
        </div>
        <template x-for="(feature, index) in features" :key="'f-'+index">
            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                <input type="text" :name="'features['+index+'][name]'" x-model="feature.name" class="{{ $inputClass }}" placeholder="Name" />
                <input type="text" :name="'features['+index+'][feature_type]'" x-model="feature.feature_type" class="{{ $inputClass }}" placeholder="Type" />
                <button type="button" @click="removeFeature(index)" class="text-xs font-semibold text-rose-700">Remove</button>
            </div>
        </template>
        <p class="text-xs text-slate-500">Building-wide amenities stay under Properties → Amenities. Standing service charges stay on the property Utilities tab.</p>
    </div>
</details>
