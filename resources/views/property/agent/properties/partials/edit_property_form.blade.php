<form method="post" action="{{ route('property.properties.update', $property) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
    @csrf
    @method('PATCH')
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Property details</h3>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
        <input type="text" name="name" value="{{ old('name', $property->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Code</label>
            <input type="text" name="code" value="{{ old('code', $property->code) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">City</label>
            <select name="city" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select…</option>
                @foreach (config('kenya.cities', []) as $city)
                    <option value="{{ $city }}" @selected(old('city', $property->city) === $city)>{{ $city }}</option>
                @endforeach
            </select>
            @error('city')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Address</label>
        <input
            type="text"
            name="address_line"
            value="{{ old('address_line', $property->address_line) }}"
            class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
            placeholder="Start typing an address…"
            autocomplete="off"
            data-ke-address-autocomplete
            data-ke-address-endpoint="{{ route('property.geo.kenya_addresses', absolute: false) }}"
            list="ke-address-suggestions-property-edit"
        />
        <datalist id="ke-address-suggestions-property-edit"></datalist>
        @error('address_line')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent due day (property default)</label>
        <input type="number" name="rent_due_day" value="{{ old('rent_due_day', $property->rent_due_day) }}" min="1" max="31" class="mt-1 w-full sm:max-w-xs rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Blank = system default" />
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">If blank, uses system default ({{ app(\App\Services\Property\RentDueDayResolver::class)->systemDefaultDueDay() }}). Individual leases may override.</p>
        @error('rent_due_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Commission %</label>
        <input type="number" name="commission_percent" value="{{ old('commission_percent', $propertyCommissionPercent ?? null) }}" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional (uses default if empty)" />
        @error('commission_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
</form>
