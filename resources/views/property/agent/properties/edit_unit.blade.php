<x-property.workspace
    title="Edit unit"
    subtitle="Update unit details such as label, type, bedrooms, rent, and status."
    back-route="property.properties.units"
    :stats="[
        ['label' => 'Property', 'value' => $unit->property?->name ?? '—', 'hint' => 'Building'],
        ['label' => 'Unit', 'value' => $unit->label, 'hint' => 'Current label'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.units.update', $unit) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
        @csrf
        @method('PATCH')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Unit details</h3>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                <input type="text" value="{{ $unit->property?->name }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-100 dark:bg-gray-900/50 text-sm px-3 py-2 text-slate-600 dark:text-slate-300" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label <span class="text-red-600">*</span></label>
                <input type="text" name="label" value="{{ old('label', $unit->label) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit type <span class="text-red-600">*</span></label>
                    <button type="button" data-unit-meta-open="type" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add unit type</button>
                </div>
                <select id="unit_type" name="unit_type" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach ($unitTypes as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(old('unit_type', $unit->unit_type) === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
                @error('unit_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div id="bedrooms_wrapper">
                <div class="flex items-center justify-between gap-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bedrooms / room setup <span class="text-red-600">*</span></label>
                    <button type="button" data-unit-meta-open="bedrooms" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add bedroom count</button>
                </div>
                <select id="bedrooms" name="bedrooms" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select bedroom setup...</option>
                </select>
                @error('bedrooms')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent (KES) <span class="text-red-600">*</span></label>
                <input type="number" name="rent_amount" value="{{ old('rent_amount', $unit->rent_amount) }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('rent_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status <span class="text-red-600">*</span></label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="vacant" @selected(old('status', $unit->status) === 'vacant')>Vacant</option>
                    <option value="occupied" @selected(old('status', $unit->status) === 'occupied')>Occupied</option>
                    <option value="notice" @selected(old('status', $unit->status) === 'notice')>Notice</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Public listing description</label>
                <textarea
                    name="public_listing_description"
                    rows="5"
                    class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                    placeholder="Describe highlights seen on the public property page."
                >{{ old('public_listing_description', $unit->public_listing_description) }}</textarea>
                @error('public_listing_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.properties.units', ['property_id' => $unit->property_id], absolute: false) }}" data-turbo-frame="property-main" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</a>
        </div>
    </form>

    <div id="unit_meta_modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40" data-unit-meta-close></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 id="unit_meta_modal_title" class="text-base font-semibold text-slate-900">Add option</h3>
                <button type="button" class="text-slate-500 hover:text-slate-700" data-unit-meta-close>&times;</button>
            </div>
            <p id="unit_meta_modal_hint" class="mt-1 text-xs text-slate-500">Add your own values and use them immediately in this form.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div id="modal_type_label_wrap" class="sm:col-span-2">
                    <label for="modal_unit_type_label" class="block text-xs font-medium text-slate-600">Unit type label <span class="text-red-600">*</span></label>
                    <input id="modal_unit_type_label" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Penthouse Duplex" />
                </div>
                <div id="modal_bedrooms_type_wrap" class="sm:col-span-2 hidden">
                    <label for="modal_bedrooms_unit_type" class="block text-xs font-medium text-slate-600">Apply to unit type <span class="text-red-600">*</span></label>
                    <select id="modal_bedrooms_unit_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select unit type...</option>
                    </select>
                </div>
                <div id="modal_bedrooms_value_wrap" class="hidden">
                    <label for="modal_bedrooms_value" class="block text-xs font-medium text-slate-600">Bedrooms count <span class="text-red-600">*</span></label>
                    <input id="modal_bedrooms_value" type="number" min="0" max="20" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 3" />
                </div>
            </div>
            <p id="unit_meta_modal_error" class="mt-2 hidden text-xs text-red-600"></p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="unit_meta_clear_btn" class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Clear saved options</button>
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700" data-unit-meta-close>Cancel</button>
                <button type="button" id="unit_meta_save_btn" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Add option</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const unitType = document.getElementById('unit_type');
            const bedrooms = document.getElementById('bedrooms');
            const bedroomsWrapper = document.getElementById('bedrooms_wrapper');
            const metaModal = document.getElementById('unit_meta_modal');
            const metaModalError = document.getElementById('unit_meta_modal_error');
            const metaSaveBtn = document.getElementById('unit_meta_save_btn');
            const metaClearBtn = document.getElementById('unit_meta_clear_btn');
            const metaTitle = document.getElementById('unit_meta_modal_title');
            const metaHint = document.getElementById('unit_meta_modal_hint');
            const modalTypeLabelWrap = document.getElementById('modal_type_label_wrap');
            const modalBedroomsTypeWrap = document.getElementById('modal_bedrooms_type_wrap');
            const modalBedroomsValueWrap = document.getElementById('modal_bedrooms_value_wrap');
            const modalTypeLabel = document.getElementById('modal_unit_type_label');
            const modalBedroomsUnitType = document.getElementById('modal_bedrooms_unit_type');
            const modalBedroomsValue = document.getElementById('modal_bedrooms_value');
            const storageKey = 'property_unit_meta_options_v1';
            let metaMode = 'type';
            if (!unitType || !bedrooms || !bedroomsWrapper) {
                return;
            }

            const noBedroomTypes = new Set(['single_room', 'bedsitter', 'studio']);
            let bedroomOptionsByType = @json($bedroomOptionsByType ?? []);
            const slugify = (text) => String(text || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');

            const bedroomText = (count) => {
                if (count === 0) return 'No separate bedroom';
                return `${count} ${count === 1 ? 'bedroom' : 'bedrooms'}`;
            };

            const ensureOption = (select, value, label) => {
                if (!select || !value) return;
                if ([...select.options].some((o) => o.value === String(value))) return;
                const option = document.createElement('option');
                option.value = String(value);
                option.textContent = label;
                select.appendChild(option);
            };

            const allUnitTypeSelects = () => Array.from(document.querySelectorAll('select[name="unit_type"], select[name$="[unit_type]"]'));
            const oldBedroomsValue = @json((string) old('bedrooms', $unit->bedrooms));

            const readStoredMeta = () => {
                try {
                    const raw = window.localStorage ? window.localStorage.getItem(storageKey) : null;
                    const parsed = raw ? JSON.parse(raw) : null;
                    const types = Array.isArray(parsed?.types) ? parsed.types : [];
                    const bedroomsByType = parsed?.bedroomsByType && typeof parsed.bedroomsByType === 'object'
                        ? parsed.bedroomsByType
                        : {};
                    return { types, bedroomsByType };
                } catch (_) {
                    return { types: [], bedroomsByType: {} };
                }
            };

            const writeStoredMeta = (payload) => {
                try {
                    if (!window.localStorage) return;
                    window.localStorage.setItem(storageKey, JSON.stringify(payload));
                } catch (_) {
                    // Ignore storage write failures (private mode/full quota).
                }
            };

            const clearStoredMeta = () => {
                try {
                    if (!window.localStorage) return;
                    window.localStorage.removeItem(storageKey);
                } catch (_) {
                    // Ignore storage failures.
                }
            };

            const saveMetaOption = (typeValue, typeLabel, bedroomsValue) => {
                const state = readStoredMeta();
                const cleanTypeValue = String(typeValue || '').trim();
                const cleanTypeLabel = String(typeLabel || '').trim();
                const cleanBedrooms = Number.parseInt(String(bedroomsValue), 10);
                if (cleanTypeValue !== '' && cleanTypeLabel !== '' && !state.types.some((t) => String(t?.value) === cleanTypeValue)) {
                    state.types.push({ value: cleanTypeValue, label: cleanTypeLabel });
                }
                if (Number.isFinite(cleanBedrooms) && cleanBedrooms >= 0 && cleanBedrooms <= 20 && cleanTypeValue !== '') {
                    const values = Array.isArray(state.bedroomsByType?.[cleanTypeValue]) ? state.bedroomsByType[cleanTypeValue] : [];
                    if (!values.includes(cleanBedrooms)) {
                        values.push(cleanBedrooms);
                        values.sort((a, b) => a - b);
                    }
                    state.bedroomsByType[cleanTypeValue] = values;
                }
                writeStoredMeta(state);
            };

            const applyStoredOptions = () => {
                const state = readStoredMeta();
                state.types.forEach((entry) => {
                    const value = String(entry?.value || '');
                    const label = String(entry?.label || '').trim();
                    if (value !== '' && label !== '') {
                        ensureOption(unitType, value, label);
                    }
                });
                Object.entries(state.bedroomsByType || {}).forEach(([typeValue, counts]) => {
                    if (!Array.isArray(counts)) return;
                    bedroomOptionsByType[typeValue] = bedroomOptionsByType[typeValue] || {};
                    counts.forEach((count) => {
                        const numeric = Number.parseInt(String(count), 10);
                        if (!Number.isFinite(numeric) || numeric < 0 || numeric > 20) return;
                        bedroomOptionsByType[typeValue][numeric] = bedroomText(numeric);
                    });
                });
            };

            const renderBedroomsOptions = (typeValue, selectedValue = '') => {
                const normalizedType = String(typeValue || '').trim();
                const currentSelected = String(selectedValue ?? '');
                bedrooms.innerHTML = '<option value="">Select bedroom setup...</option>';
                const options = bedroomOptionsByType[normalizedType] || {};
                Object.keys(options)
                    .map((value) => Number.parseInt(String(value), 10))
                    .filter((value) => Number.isFinite(value))
                    .sort((a, b) => a - b)
                    .forEach((value) => {
                        ensureOption(bedrooms, value, options[String(value)] || bedroomText(value));
                    });
                if (currentSelected !== '' && [...bedrooms.options].some((o) => o.value === currentSelected)) {
                    bedrooms.value = currentSelected;
                }
            };

            const openMetaModal = () => {
                if (!metaModal) return;
                const typeMode = metaMode === 'type';
                if (metaTitle) {
                    metaTitle.textContent = typeMode ? 'Add unit type' : 'Add bedroom count';
                }
                if (metaHint) {
                    metaHint.textContent = typeMode
                        ? 'Add a unit type option only.'
                        : 'Select an existing unit type, then add a bedroom count option.';
                }
                if (modalTypeLabelWrap) modalTypeLabelWrap.classList.toggle('hidden', !typeMode);
                if (modalBedroomsTypeWrap) modalBedroomsTypeWrap.classList.toggle('hidden', typeMode);
                if (modalBedroomsValueWrap) modalBedroomsValueWrap.classList.toggle('hidden', typeMode);

                if (!typeMode && modalBedroomsUnitType) {
                    modalBedroomsUnitType.innerHTML = '<option value="">Select unit type...</option>';
                    const seen = new Set();
                    allUnitTypeSelects().forEach((select) => {
                        [...select.options].forEach((opt) => {
                            const value = String(opt.value || '').trim();
                            const label = String(opt.textContent || '').trim();
                            if (value === '' || seen.has(value)) return;
                            seen.add(value);
                            ensureOption(modalBedroomsUnitType, value, label);
                        });
                    });
                }
                metaModal.classList.remove('hidden');
                metaModal.classList.add('flex');
                if (metaModalError) {
                    metaModalError.classList.add('hidden');
                    metaModalError.textContent = '';
                }
                if (typeMode && modalTypeLabel) {
                    modalTypeLabel.focus();
                } else if (!typeMode && modalBedroomsUnitType) {
                    modalBedroomsUnitType.focus();
                }
            };

            const closeMetaModal = () => {
                if (!metaModal) return;
                metaModal.classList.add('hidden');
                metaModal.classList.remove('flex');
                if (modalTypeLabel) modalTypeLabel.value = '';
                if (modalBedroomsUnitType) modalBedroomsUnitType.value = '';
                if (modalBedroomsValue) modalBedroomsValue.value = '';
            };

            const syncBedrooms = () => {
                const requiresNoBedroom = noBedroomTypes.has(unitType.value);
                if (requiresNoBedroom) {
                    bedrooms.value = '0';
                    bedrooms.disabled = true;
                    bedroomsWrapper.classList.add('hidden');
                } else {
                    renderBedroomsOptions(unitType.value, bedrooms.value || oldBedroomsValue);
                    bedrooms.disabled = false;
                    bedroomsWrapper.classList.remove('hidden');
                }
            };

            if (metaSaveBtn) {
                metaSaveBtn.addEventListener('click', () => {
                    if (metaMode === 'type') {
                        const rawTypeLabel = modalTypeLabel ? modalTypeLabel.value : '';
                        const typeValue = slugify(rawTypeLabel);
                        if (!typeValue) {
                            if (metaModalError) {
                                metaModalError.textContent = 'Enter a unit type label.';
                                metaModalError.classList.remove('hidden');
                            }
                            return;
                        }
                        ensureOption(unitType, typeValue, rawTypeLabel.trim());
                        saveMetaOption(typeValue, rawTypeLabel.trim(), NaN);
                        unitType.value = typeValue;
                        unitType.dispatchEvent(new Event('change'));
                    } else {
                        const selectedType = String(modalBedroomsUnitType ? modalBedroomsUnitType.value : '').trim();
                        const bedroomsValue = Number.parseInt(modalBedroomsValue ? modalBedroomsValue.value : '', 10);
                        if (!selectedType) {
                            if (metaModalError) {
                                metaModalError.textContent = 'Select unit type first.';
                                metaModalError.classList.remove('hidden');
                            }
                            return;
                        }
                        if (!Number.isFinite(bedroomsValue) || bedroomsValue < 0 || bedroomsValue > 20) {
                            if (metaModalError) {
                                metaModalError.textContent = 'Bedrooms count must be between 0 and 20.';
                                metaModalError.classList.remove('hidden');
                            }
                            return;
                        }
                        bedroomOptionsByType[selectedType] = bedroomOptionsByType[selectedType] || {};
                        bedroomOptionsByType[selectedType][bedroomsValue] = bedroomText(bedroomsValue);
                        saveMetaOption(selectedType, '', bedroomsValue);
                        if (unitType.value === selectedType && !noBedroomTypes.has(selectedType)) {
                            renderBedroomsOptions(selectedType, String(bedroomsValue));
                            bedrooms.value = String(bedroomsValue);
                        }
                    }
                    closeMetaModal();
                });
            }

            if (metaClearBtn) {
                metaClearBtn.addEventListener('click', () => {
                    clearStoredMeta();
                    if (metaModalError) {
                        metaModalError.textContent = 'Saved custom options cleared for this browser.';
                        metaModalError.classList.remove('hidden');
                    }
                });
            }

            document.querySelectorAll('[data-unit-meta-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    metaMode = button.getAttribute('data-unit-meta-open') === 'bedrooms' ? 'bedrooms' : 'type';
                    openMetaModal();
                });
            });
            document.querySelectorAll('[data-unit-meta-close]').forEach((button) => {
                button.addEventListener('click', closeMetaModal);
            });

            unitType.addEventListener('change', syncBedrooms);
            applyStoredOptions();
            syncBedrooms();
        })();
    </script>
</x-property.workspace>
