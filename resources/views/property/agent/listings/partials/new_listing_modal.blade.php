@if ($vacantUnits->isNotEmpty())
    <form
        method="post"
        action="{{ route('property.listings.start') }}"
        class="space-y-4"
    >
        @csrf
        <p class="text-sm text-slate-600 dark:text-slate-400">Pick a vacant unit to open the publish editor.</p>
        <div>
            <label for="property_unit_id_modal" class="block text-sm font-medium text-slate-800 dark:text-slate-100">Vacant unit</label>
            <div class="mt-2">
                <x-property.quick-create-select
                    name="property_unit_id"
                    :required="true"
                    select-id="property_unit_id_modal"
                    placeholder="Select property / unit…"
                    :options="collect($vacantUnits)->map(function ($u) {
                        $suffix = $u->public_listing_published
                            ? ' (featured)'
                            : ($u->publicImages->isNotEmpty() ? ' (photos · '.$u->publicImages->count().')' : ' (on Discover · no photos yet)');
                        return [
                            'value' => $u->id,
                            'label' => $u->property->name.' — '.$u->label.$suffix,
                            'selected' => (string) old('property_unit_id', (string) ($selectedUnit->id ?? '')) === (string) $u->id,
                        ];
                    })->all()"
                    :create="[
                        'mode' => 'ajax',
                        'title' => 'Add unit',
                        'endpoint' => route('property.units.store_json'),
                        'fields' => [
                            ['name' => 'property_id', 'label' => 'Property', 'required' => true, 'span' => '2', 'type' => 'select', 'placeholder' => 'Select property', 'options' => collect($vacantUnits)->map(fn ($u) => ['value' => $u->property_id, 'label' => $u->property->name])->unique('value')->values()->all()],
                            ['name' => 'label', 'label' => 'Unit label', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. A1'],
                            ['name' => 'unit_type', 'label' => 'Unit type', 'required' => false, 'type' => 'select', 'options' => [['value' => 'apartment', 'label' => 'Apartment'], ['value' => 'single_room', 'label' => 'Single room'], ['value' => 'bedsitter', 'label' => 'Bedsitter'], ['value' => 'studio', 'label' => 'Studio'], ['value' => 'bungalow', 'label' => 'Bungalow'], ['value' => 'maisonette', 'label' => 'Maisonette'], ['value' => 'villa', 'label' => 'Villa'], ['value' => 'townhouse', 'label' => 'Townhouse'], ['value' => 'commercial', 'label' => 'Commercial']]],
                            ['name' => 'status', 'label' => 'Status', 'required' => false, 'type' => 'select', 'options' => [['value' => 'vacant', 'label' => 'Vacant'], ['value' => 'occupied', 'label' => 'Occupied'], ['value' => 'notice', 'label' => 'Notice']]],
                        ],
                    ]"
                />
            </div>
            @error('property_unit_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <button
                type="button"
                class="rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                @click="showNewListing = false"
            >
                Cancel
            </button>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Open publish editor
            </button>
        </div>
    </form>
@endif
