<x-property.workspace
    title="Unit status"
    subtitle="Occupied, vacant, notice — linked to leases and rent roll. Add listing description here, then manage photos, main image, and publish under Listings → Vacant units."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No units"
    empty-hint="Add units per property; vacant units can be attached when creating a lease."
>
    <x-slot name="above">
        <form method="post" action="{{ route('property.units.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add unit</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                    <select name="property_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @foreach ($properties as $p)
                            <option value="{{ $p->id }}" @selected(old('property_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label</label>
                    <input type="text" name="label" value="{{ old('label') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. A1" />
                    @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit type</label>
                    <select id="unit_type" name="unit_type" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach ($unitTypes as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected(old('unit_type', 'apartment') === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    @error('unit_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div id="bedrooms_wrapper">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bedrooms / room setup</label>
                    <select id="bedrooms" name="bedrooms" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="0" @selected((string) old('bedrooms', '1') === '0')>No separate bedroom</option>
                        @for ($b = 1; $b <= 10; $b++)
                            <option value="{{ $b }}" @selected((string) old('bedrooms', '1') === (string) $b)>{{ $b }} {{ Str::plural('bedroom', $b) }}</option>
                        @endfor
                    </select>
                    @error('bedrooms')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Use Unit type to distinguish Single room, Bedsitter, and Studio.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent (KES)</label>
                    <input type="number" name="rent_amount" value="{{ old('rent_amount') }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('rent_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="vacant" @selected(old('status') === 'vacant')>Vacant</option>
                        <option value="occupied" @selected(old('status') === 'occupied')>Occupied</option>
                        <option value="notice" @selected(old('status') === 'notice')>Notice</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Public listing description</label>
                    <textarea
                        name="public_listing_description"
                        rows="4"
                        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                        placeholder="Describe highlights seen on the public property page."
                    >{{ old('public_listing_description') }}</textarea>
                    @error('public_listing_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Photos and main image are managed in Listings for each vacant unit.</p>
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save unit</button>
        </form>
    </x-slot>

    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Status: All</option>
            <option value="vacant">Vacant</option>
            <option value="occupied">Occupied</option>
            <option value="reserved">Reserved</option>
        </select>
    </x-slot>
    <script>
        const initUnitTypeBedroomsDependency = () => {
            const unitType = document.getElementById('unit_type');
            const bedrooms = document.getElementById('bedrooms');
            const bedroomsWrapper = document.getElementById('bedrooms_wrapper');
            if (!unitType || !bedrooms || !bedroomsWrapper) {
                return;
            }

            const noBedroomTypes = new Set(['single_room', 'bedsitter', 'studio']);

            const syncBedrooms = () => {
                const requiresNoBedroom = noBedroomTypes.has(unitType.value);
                if (requiresNoBedroom) {
                    bedrooms.value = '0';
                    bedrooms.disabled = true;
                    bedroomsWrapper.classList.add('hidden');
                } else {
                    bedrooms.disabled = false;
                    bedroomsWrapper.classList.remove('hidden');
                }
            };

            unitType.addEventListener('change', syncBedrooms);
            syncBedrooms();
        };

        document.addEventListener('DOMContentLoaded', initUnitTypeBedroomsDependency);
        document.addEventListener('turbo:load', initUnitTypeBedroomsDependency);
    </script>
</x-property.workspace>
