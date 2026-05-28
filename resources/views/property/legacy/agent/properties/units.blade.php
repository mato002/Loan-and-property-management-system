<x-property.workspace
    title="Unit status"
    subtitle="Occupied, vacant, notice — linked to leases and rent roll. Add listing description here, then manage photos, main image, and publish under Listings → Vacant units."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No units"
    empty-hint="Add units per property; vacant units can be attached when creating a lease."
>
    <x-slot name="above">
        @php
            $unitCreateFormHasErrors = $errors->has('property_id')
                || $errors->has('unit_count')
                || $errors->has('label')
                || $errors->has('unit_type')
                || $errors->has('bedrooms')
                || $errors->has('rent_amount')
                || $errors->has('status')
                || $errors->has('unit_groups')
                || $errors->has('vacant_count')
                || $errors->has('occupied_count')
                || $errors->has('notice_count');
            $unitFieldCfg = $unitFields ?? [];
            $unitEnabled = fn (string $k, bool $d = true) => (bool) (($unitFieldCfg[$k]['enabled'] ?? $d));
            $unitRequired = fn (string $k, bool $d = false) => (bool) (($unitFieldCfg[$k]['required'] ?? $d) && $unitEnabled($k, $d));
        @endphp
        <div x-data="{ showUnitCreateForms: @js($unitCreateFormHasErrors) }" class="space-y-4">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.properties.units', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All units</a>
                <a href="{{ route('property.properties.units', array_merge((array) ($filters ?? []), ['status' => 'vacant']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Vacant</a>
                <a href="{{ route('property.properties.units', array_merge((array) ($filters ?? []), ['status' => 'occupied']), absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Occupied</a>
                <a href="{{ route('property.properties.units', array_merge((array) ($filters ?? []), ['status' => 'notice']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Notice</a>
                <a href="{{ route('property.properties.units.export', request()->query(), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
            </div>
        </div>

        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Setup flow: Units → Tenants → Rent</p>
            <p class="mt-1 text-sm text-slate-600">Add units (doors) here. Then allocate a vacant unit to a tenant using a Lease, then bill rent using an Invoice.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.tenants.directory', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Add tenant
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Allocate unit (Lease)
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.listings.vacant', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Publish vacancy
                    <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="max-w-3xl">
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-4 text-base font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 sm:w-auto"
                @click="showUnitCreateForms = !showUnitCreateForms"
            >
                <i class="fa-solid fa-door-open text-lg" aria-hidden="true"></i>
                <span x-text="showUnitCreateForms ? 'Hide unit creation forms' : 'Add units'"></span>
            </button>
        </div>

        <div x-show="showUnitCreateForms" x-cloak class="grid gap-4 xl:grid-cols-2 items-start">
        <form
            method="post"
            action="{{ route('property.units.store') }}"
            x-data="{ statusMode: '{{ old('status_mode', 'single') }}' }"
            class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 h-fit"
        >
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add unit</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property <span class="text-red-600">*</span></label>
                    <x-property.quick-create-select
                        name="property_id"
                        :required="true"
                        :options="collect($properties)->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'selected' => (string) old('property_id', request('property_id')) === (string) $p->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create property',
                            'endpoint' => route('property.properties.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Property name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Prady Court'],
                                ['name' => 'code', 'label' => 'Code (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Auto if blank'],
                                ['name' => 'address_line', 'label' => 'Address (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Street / building'],
                                ['name' => 'city', 'label' => 'City (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Nairobi'],
                            ],
                        ]"
                    />
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Only properties without units are shown to prevent duplicate allocation.</p>
                </div>
                <div id="unit_count_wrapper">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">How many similar units? <span class="text-red-600">*</span></label>
                    <input id="unit_count" type="number" name="unit_count" value="{{ old('unit_count', 1) }}" min="1" max="5000" step="1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('unit_count')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Set 1 for single entry. Example: 100 creates 100 units in one click.</p>
                </div>
                <div id="label_wrapper">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label</label>
                    <input id="label_input" type="text" name="label" value="{{ old('label') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. A1" />
                    @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Required only when units = 1.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label prefix (optional)</label>
                    <input type="text" name="label_prefix" value="{{ old('label_prefix') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. A, B, BLOCK-1-" />
                    @error('label_prefix')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">For bulk only. Tip: A1 becomes A1,A2... and 1 becomes 1,2,3...</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Start number (for bulk)</label>
                    <input type="number" name="label_start" value="{{ old('label_start', 1) }}" min="1" step="1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('label_start')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @if ($unitEnabled('unit_type', true))
                    <div>
                    <div class="flex items-center justify-between gap-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit type @if($unitRequired('unit_type', true))<span class="text-red-600">*</span>@endif</label>
                        <button type="button" data-unit-meta-open="type" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add unit type</button>
                    </div>
                    <select id="unit_type" name="unit_type" @required($unitRequired('unit_type', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select unit type...</option>
                        @foreach ($unitTypes as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected(old('unit_type') === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    @error('unit_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endif
                @if ($unitEnabled('bedrooms', true))
                    <div id="bedrooms_wrapper">
                    <div class="flex items-center justify-between gap-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bedrooms / room setup @if($unitRequired('bedrooms'))<span class="text-red-600">*</span>@endif</label>
                        <button type="button" data-unit-meta-open="bedrooms" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add bedroom count</button>
                    </div>
                    <select id="bedrooms" name="bedrooms" @required($unitRequired('bedrooms')) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select bedroom setup...</option>
                    </select>
                    @error('bedrooms')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Use Unit type to distinguish Single room, Bedsitter, and Studio.</p>
                    </div>
                @endif
                @if ($unitEnabled('rent_amount', true))
                    <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent (KES) @if($unitRequired('rent_amount', true))<span class="text-red-600">*</span>@endif</label>
                    <input type="number" name="rent_amount" value="{{ old('rent_amount') }}" step="0.01" min="0" @required($unitRequired('rent_amount', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('rent_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endif
                @if ($unitEnabled('status', true))
                    <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status mode <span class="text-red-600">*</span></label>
                    <select id="status_mode" name="status_mode" x-model="statusMode" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="single" @selected(old('status_mode', 'single') === 'single')>One status for all units</option>
                        <option value="split" @selected(old('status_mode') === 'split')>Split by counts (vacant/occupied/notice)</option>
                    </select>
                    @error('status_mode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                <div id="status_single_wrapper" x-show="statusMode !== 'split'">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status @if($unitRequired('status', true))<span class="text-red-600">*</span>@endif</label>
                    <select id="status_single" name="status" x-bind:required="statusMode !== 'split'" x-bind:disabled="statusMode === 'split'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="vacant" @selected(old('status') === 'vacant')>Vacant</option>
                        <option value="occupied" @selected(old('status') === 'occupied')>Occupied</option>
                        <option value="notice" @selected(old('status') === 'notice')>Notice</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div id="status_split_wrapper" x-show="statusMode === 'split'" class="sm:col-span-2 rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status split (optional for bulk)</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">If units are mixed (e.g. 6 vacant, 6 occupied), enter counts below. They must add up to “How many similar units?”.</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Vacant count</label>
                            <input id="vacant_count" x-bind:disabled="statusMode !== 'split'" type="number" name="vacant_count" value="{{ old('vacant_count') }}" min="0" max="5000" step="1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('vacant_count')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Occupied count</label>
                            <input id="occupied_count" x-bind:disabled="statusMode !== 'split'" type="number" name="occupied_count" value="{{ old('occupied_count') }}" min="0" max="5000" step="1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('occupied_count')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notice count</label>
                            <input id="notice_count" x-bind:disabled="statusMode !== 'split'" type="number" name="notice_count" value="{{ old('notice_count') }}" min="0" max="5000" step="1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('notice_count')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p id="split_status_error" class="mt-2 text-xs text-red-600 hidden"></p>
                </div>
                @endif
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
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save unit(s)</button>
        </form>

        <div
            x-data="{
                rows: [
                    { unit_count: 1, label_prefix: 'A', label_start: 1, unit_type: '', bedrooms: '', rent_amount: '', status: 'vacant', public_listing_description: '' }
                ],
                addRow() {
                    const last = this.rows[this.rows.length - 1] || { label_prefix: '', label_start: 1, unit_count: 1 };
                    const nextStart = Math.max(1, Number(last.label_start || 1) + Number(last.unit_count || 1));
                    this.rows.push({
                        unit_count: 1,
                        label_prefix: String(last.label_prefix || ''),
                        label_start: nextStart,
                        unit_type: '',
                        bedrooms: '',
                        rent_amount: '',
                        status: 'vacant',
                        public_listing_description: ''
                    });
                    queueMicrotask(() => {
                        if (typeof window.applyCustomUnitMetaOptions === 'function') {
                            window.applyCustomUnitMetaOptions();
                        }
                    });
                },
                removeRow(i) {
                    if (this.rows.length > 1) this.rows.splice(i, 1);
                },
                noBedroom(type) {
                    return ['single_room', 'bedsitter', 'studio'].includes(type);
                }
            }"
            class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4 h-fit"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Mixed units mode (recommended for mixed properties)</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Add multiple categories in one submit (e.g. 1BR vacant + 2BR occupied + studios).</p>
                </div>
                <div class="flex items-center gap-2">
                <button type="button" @click="addRow()" class="rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">
                    + Add group
                </button>
                </div>
            </div>

            <form method="post" action="{{ route('property.units.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="mixed_units_mode" value="1" />
                <div class="max-w-md">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property <span class="text-red-600">*</span></label>
                    <x-property.quick-create-select
                        name="property_id"
                        :required="true"
                        :options="collect($properties)->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'selected' => (string) old('property_id', request('property_id')) === (string) $p->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create property',
                            'endpoint' => route('property.properties.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Property name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Prady Court'],
                                ['name' => 'code', 'label' => 'Code (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Auto if blank'],
                                ['name' => 'address_line', 'label' => 'Address (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Street / building'],
                                ['name' => 'city', 'label' => 'City (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Nairobi'],
                            ],
                        ]"
                    />
                </div>

                <template x-for="(row, idx) in rows" :key="idx">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Group <span x-text="idx + 1"></span></p>
                            <button type="button" @click="removeRow(idx)" class="text-xs text-rose-600 hover:text-rose-700">Remove</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Count <span class="text-red-600">*</span></label>
                                <input x-model.number="row.unit_count" :name="'unit_groups['+idx+'][unit_count]'" type="number" min="1" max="5000" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label prefix <span class="text-red-600">*</span></label>
                                <input x-model="row.label_prefix" :name="'unit_groups['+idx+'][label_prefix]'" type="text" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="A, B, BLK1-" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Start # <span class="text-red-600">*</span></label>
                                <input x-model.number="row.label_start" :name="'unit_groups['+idx+'][label_start]'" type="number" min="1" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit type <span class="text-red-600">*</span></label>
                                    <button type="button" data-unit-meta-open="type" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add type</button>
                                </div>
                                <select x-model="row.unit_type" :name="'unit_groups['+idx+'][unit_type]'" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                    <option value="">Select type...</option>
                                    @foreach ($unitTypes as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="!noBedroom(row.unit_type)">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bedrooms <span class="text-red-600">*</span></label>
                                    <button type="button" data-unit-meta-open="bedrooms" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add bedrooms</button>
                                </div>
                                <select x-model.number="row.bedrooms" :name="'unit_groups['+idx+'][bedrooms]'" :required="!noBedroom(row.unit_type)" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                    <option value="">Select bedrooms...</option>
                                </select>
                            </div>
                            <input x-show="noBedroom(row.unit_type)" type="hidden" :name="'unit_groups['+idx+'][bedrooms]'" value="0" />
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent (KES) <span class="text-red-600">*</span></label>
                                <input x-model="row.rent_amount" :name="'unit_groups['+idx+'][rent_amount]'" type="number" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status <span class="text-red-600">*</span></label>
                                <select x-model="row.status" :name="'unit_groups['+idx+'][status]'" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                    <option value="vacant">Vacant</option>
                                    <option value="occupied">Occupied</option>
                                    <option value="notice">Notice</option>
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Description (optional)</label>
                                <textarea x-model="row.public_listing_description" :name="'unit_groups['+idx+'][public_listing_description]'" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"></textarea>
                            </div>
                        </div>
                    </div>
                </template>

                @error('unit_groups')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save all groups</button>
            </form>
        </div>
        </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.properties.units') }}" class="w-full grid gap-2 sm:grid-cols-2 lg:grid-cols-9">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search unit, property, type..." class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 lg:col-span-2" />
            <select name="property_id" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All properties</option>
                @foreach (($allProperties ?? []) as $p)
                    <option value="{{ $p->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Status: All</option>
                <option value="vacant" @selected(($filters['status'] ?? '') === 'vacant')>Vacant</option>
                <option value="occupied" @selected(($filters['status'] ?? '') === 'occupied')>Occupied</option>
                <option value="notice" @selected(($filters['status'] ?? '') === 'notice')>Notice</option>
            </select>
            <select name="unit_type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Type: All</option>
                @foreach (($unitTypes ?? []) as $tv => $tl)
                    <option value="{{ $tv }}" @selected(($filters['unit_type'] ?? '') === $tv)>{{ $tl }}</option>
                @endforeach
            </select>
            <input type="number" name="rent_min" value="{{ $filters['rent_min'] ?? '' }}" min="0" step="0.01" placeholder="Min rent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="number" name="rent_max" value="{{ $filters['rent_max'] ?? '' }}" min="0" step="0.01" placeholder="Max rent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <div>
                <select name="per_page" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                    @foreach ([10, 30, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? request('per_page', 30)) === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.properties.units', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>
    </x-slot>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} unit(s)
                </p>
                <div>
                    {{ $paginator->links() }}
                </div>
            </div>
        @endisset
    </x-slot>
    <div id="unit_meta_modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/40" data-unit-meta-close></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 id="unit_meta_modal_title" class="text-base font-semibold text-slate-900">Add option</h3>
                <button type="button" class="text-slate-500 hover:text-slate-700" data-unit-meta-close>&times;</button>
            </div>
            <p id="unit_meta_modal_hint" class="mt-1 text-xs text-slate-500">These options are added to the current page immediately and saved when you submit units.</p>
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
        window.initUnitTypeBedroomsDependency = window.initUnitTypeBedroomsDependency || function () {
            const unitCount = document.getElementById('unit_count');
            const labelInput = document.getElementById('label_input');
            const labelWrapper = document.getElementById('label_wrapper');
            const statusMode = document.getElementById('status_mode');
            const statusSingle = document.getElementById('status_single');
            const statusSingleWrapper = document.getElementById('status_single_wrapper');
            const statusSplitWrapper = document.getElementById('status_split_wrapper');
            const vacantCount = document.getElementById('vacant_count');
            const occupiedCount = document.getElementById('occupied_count');
            const noticeCount = document.getElementById('notice_count');
            const unitType = document.getElementById('unit_type');
            const bedrooms = document.getElementById('bedrooms');
            const bedroomsWrapper = document.getElementById('bedrooms_wrapper');
            if (!unitType || !bedrooms || !bedroomsWrapper || !unitCount || !labelInput || !labelWrapper || !statusMode || !statusSingle || !statusSingleWrapper || !statusSplitWrapper || !vacantCount || !occupiedCount || !noticeCount) {
                return;
            }

            const noBedroomTypes = new Set(['single_room', 'bedsitter', 'studio']);
            let bedroomOptionsByType = @json($bedroomOptionsByType ?? []);
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
            const allBedroomsSelects = () => Array.from(document.querySelectorAll('select[name="bedrooms"], select[name$="[bedrooms]"]'));
            const oldSingleBedrooms = @json((string) old('bedrooms', ''));

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
                        allUnitTypeSelects().forEach((select) => ensureOption(select, value, label));
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

            window.applyCustomUnitMetaOptions = applyStoredOptions;

            const renderBedroomsOptions = (select, typeValue, selectedValue = '') => {
                if (!select) return;
                const normalizedType = String(typeValue || '').trim();
                const currentSelected = String(selectedValue ?? '');
                select.innerHTML = '<option value="">Select bedroom setup...</option>';
                const options = bedroomOptionsByType[normalizedType] || {};
                Object.keys(options)
                    .map((value) => Number.parseInt(String(value), 10))
                    .filter((value) => Number.isFinite(value))
                    .sort((a, b) => a - b)
                    .forEach((value) => {
                        ensureOption(select, value, options[String(value)] || bedroomText(value));
                    });
                if (currentSelected !== '' && [...select.options].some((o) => o.value === currentSelected)) {
                    select.value = currentSelected;
                }
            };

            const syncGroupBedroomsForTypeSelect = (typeSelect) => {
                const rowGrid = typeSelect ? typeSelect.closest('.grid') : null;
                if (!rowGrid) return;
                const rowBedrooms = rowGrid.querySelector('select[name$="[bedrooms]"]');
                if (!rowBedrooms) return;
                renderBedroomsOptions(rowBedrooms, typeSelect.value, rowBedrooms.value);
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

            const syncLabel = () => {
                const count = parseInt(unitCount.value || '1', 10);
                const isBulk = Number.isFinite(count) && count > 1;
                if (isBulk) {
                    labelInput.required = false;
                    labelInput.disabled = true;
                    labelWrapper.classList.add('hidden');
                } else {
                    labelInput.disabled = false;
                    labelInput.required = true;
                    labelWrapper.classList.remove('hidden');
                }
            };

            const syncBedrooms = () => {
                const requiresNoBedroom = noBedroomTypes.has(unitType.value);
                if (requiresNoBedroom) {
                    bedrooms.value = '0';
                    bedrooms.disabled = true;
                    bedroomsWrapper.classList.add('hidden');
                } else {
                    renderBedroomsOptions(bedrooms, unitType.value, bedrooms.value || oldSingleBedrooms);
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
                        allUnitTypeSelects().forEach((select) => ensureOption(select, typeValue, rawTypeLabel.trim()));
                        saveMetaOption(typeValue, rawTypeLabel.trim(), NaN);
                        if (unitType) {
                            unitType.value = typeValue;
                            unitType.dispatchEvent(new Event('change'));
                        }
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
                        allBedroomsSelects().forEach((select) => ensureOption(select, bedroomsValue, bedroomText(bedroomsValue)));
                        bedroomOptionsByType[selectedType] = bedroomOptionsByType[selectedType] || {};
                        bedroomOptionsByType[selectedType][bedroomsValue] = bedroomText(bedroomsValue);
                        saveMetaOption(selectedType, '', bedroomsValue);
                        if (unitType && unitType.value === selectedType && bedrooms && !noBedroomTypes.has(selectedType)) {
                            renderBedroomsOptions(bedrooms, selectedType, String(bedroomsValue));
                            bedrooms.value = String(bedroomsValue);
                        }
                        document.querySelectorAll('select[name$="[unit_type]"]').forEach((groupTypeSelect) => {
                            if ((groupTypeSelect).value === selectedType) {
                                syncGroupBedroomsForTypeSelect(groupTypeSelect);
                            }
                        });
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

            const syncStatusMode = () => {
                const split = statusMode.value === 'split';
                if (split) {
                    statusSingleWrapper.classList.add('hidden');
                    statusSingle.disabled = true;
                    statusSingle.required = false;
                    statusSplitWrapper.classList.remove('hidden');
                    vacantCount.disabled = false;
                    occupiedCount.disabled = false;
                    noticeCount.disabled = false;
                } else {
                    statusSingleWrapper.classList.remove('hidden');
                    statusSingle.disabled = false;
                    statusSingle.required = true;
                    statusSplitWrapper.classList.add('hidden');
                    vacantCount.disabled = true;
                    occupiedCount.disabled = true;
                    noticeCount.disabled = true;
                }
            };

            const syncSplitValidation = () => {
                const splitError = document.getElementById('split_status_error');
                if (!splitError) {
                    return;
                }
                const total = parseInt(unitCount.value || '0', 10);
                const vacant = parseInt(vacantCount.value || '0', 10);
                const occupied = parseInt(occupiedCount.value || '0', 10);
                const notice = parseInt(noticeCount.value || '0', 10);
                const sum = (Number.isFinite(vacant) ? vacant : 0)
                    + (Number.isFinite(occupied) ? occupied : 0)
                    + (Number.isFinite(notice) ? notice : 0);
                const isSplit = statusMode.value === 'split';

                if (!isSplit) {
                    splitError.classList.add('hidden');
                    splitError.textContent = '';
                    vacantCount.setCustomValidity('');
                    occupiedCount.setCustomValidity('');
                    noticeCount.setCustomValidity('');
                    return;
                }

                if (sum > total) {
                    const msg = `Split total (${sum}) cannot exceed total units (${total}).`;
                    splitError.textContent = msg;
                    splitError.classList.remove('hidden');
                    vacantCount.setCustomValidity(msg);
                    occupiedCount.setCustomValidity(msg);
                    noticeCount.setCustomValidity(msg);
                } else {
                    splitError.classList.add('hidden');
                    splitError.textContent = '';
                    vacantCount.setCustomValidity('');
                    occupiedCount.setCustomValidity('');
                    noticeCount.setCustomValidity('');
                }
            };

            unitCount.addEventListener('input', syncLabel);
            syncLabel();
            applyStoredOptions();
            unitCount.addEventListener('input', syncSplitValidation);
            unitType.addEventListener('change', syncBedrooms);
            syncBedrooms();
            document.querySelectorAll('select[name$="[unit_type]"]').forEach((groupTypeSelect) => {
                groupTypeSelect.addEventListener('change', () => syncGroupBedroomsForTypeSelect(groupTypeSelect));
                syncGroupBedroomsForTypeSelect(groupTypeSelect);
            });
            statusMode.addEventListener('change', syncStatusMode);
            syncStatusMode();
            statusMode.addEventListener('change', syncSplitValidation);
            vacantCount.addEventListener('input', syncSplitValidation);
            occupiedCount.addEventListener('input', syncSplitValidation);
            noticeCount.addEventListener('input', syncSplitValidation);
            syncSplitValidation();

            if (window.__unitMetaDelegatedClickHandler) {
                document.removeEventListener('click', window.__unitMetaDelegatedClickHandler);
            }
            window.__unitMetaDelegatedClickHandler = (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                const openBtn = target.closest('[data-unit-meta-open]');
                if (openBtn instanceof Element) {
                    event.preventDefault();
                    metaMode = openBtn.getAttribute('data-unit-meta-open') === 'bedrooms' ? 'bedrooms' : 'type';
                    openMetaModal();
                    return;
                }

                const closeBtn = target.closest('[data-unit-meta-close]');
                if (closeBtn instanceof Element) {
                    event.preventDefault();
                    closeMetaModal();
                }
            };
            document.addEventListener('click', window.__unitMetaDelegatedClickHandler);
        };

        // Prevent duplicate global listeners when Turbo re-renders this frame.
        if (!window.__unitTypeBedroomsDependencyBound) {
            window.__unitTypeBedroomsDependencyBound = true;
            document.addEventListener('DOMContentLoaded', window.initUnitTypeBedroomsDependency);
            document.addEventListener('turbo:load', window.initUnitTypeBedroomsDependency);
            document.addEventListener('turbo:frame-load', (event) => {
                const frame = event.target;
                if (frame && frame.id === 'property-main') {
                    window.initUnitTypeBedroomsDependency();
                }
            });
        }
    </script>
</x-property.workspace>
