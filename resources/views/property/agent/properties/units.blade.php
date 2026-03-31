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

        <div class="grid gap-4 xl:grid-cols-2 items-start">
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
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">For bulk only. Tip: you can type A1 here and it will start from A1 automatically.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Start number (for bulk)</label>
                    <input type="number" name="label_start" value="{{ old('label_start', 1) }}" min="1" step="1" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('label_start')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit type <span class="text-red-600">*</span></label>
                    <select id="unit_type" name="unit_type" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach ($unitTypes as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected(old('unit_type', 'apartment') === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    @error('unit_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div id="bedrooms_wrapper">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bedrooms / room setup <span class="text-red-600">*</span></label>
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
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent (KES) <span class="text-red-600">*</span></label>
                    <input type="number" name="rent_amount" value="{{ old('rent_amount') }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('rent_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status mode <span class="text-red-600">*</span></label>
                    <select id="status_mode" name="status_mode" x-model="statusMode" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="single" @selected(old('status_mode', 'single') === 'single')>One status for all units</option>
                        <option value="split" @selected(old('status_mode') === 'split')>Split by counts (vacant/occupied/notice)</option>
                    </select>
                    @error('status_mode')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div id="status_single_wrapper" x-show="statusMode !== 'split'">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status <span class="text-red-600">*</span></label>
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
                    { unit_count: 1, label_prefix: 'A', label_start: 1, unit_type: 'apartment', bedrooms: 1, rent_amount: '', status: 'vacant', public_listing_description: '' }
                ],
                addRow() {
                    const last = this.rows[this.rows.length - 1] || { label_prefix: '', label_start: 1, unit_count: 1 };
                    const nextStart = Math.max(1, Number(last.label_start || 1) + Number(last.unit_count || 1));
                    this.rows.push({
                        unit_count: 1,
                        label_prefix: String(last.label_prefix || ''),
                        label_start: nextStart,
                        unit_type: 'apartment',
                        bedrooms: 1,
                        rent_amount: '',
                        status: 'vacant',
                        public_listing_description: ''
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
                <button type="button" @click="addRow()" class="rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">
                    + Add group
                </button>
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
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit type <span class="text-red-600">*</span></label>
                                <select x-model="row.unit_type" :name="'unit_groups['+idx+'][unit_type]'" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                    @foreach ($unitTypes as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="!noBedroom(row.unit_type)">
                                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bedrooms <span class="text-red-600">*</span></label>
                                <select x-model.number="row.bedrooms" :name="'unit_groups['+idx+'][bedrooms]'" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                    @for ($b = 0; $b <= 10; $b++)
                                        <option value="{{ $b }}">{{ $b === 0 ? 'No separate bedroom' : $b.' '.Str::plural('bedroom', $b) }}</option>
                                    @endfor
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
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.properties.units') }}" class="w-full grid gap-2 sm:grid-cols-2 lg:grid-cols-8">
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
            <div class="flex items-center gap-2 lg:col-span-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.properties.units', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>
    </x-slot>
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
                    bedrooms.disabled = false;
                    bedroomsWrapper.classList.remove('hidden');
                }
            };

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
            unitCount.addEventListener('input', syncSplitValidation);
            unitType.addEventListener('change', syncBedrooms);
            syncBedrooms();
            statusMode.addEventListener('change', syncStatusMode);
            syncStatusMode();
            statusMode.addEventListener('change', syncSplitValidation);
            vacantCount.addEventListener('input', syncSplitValidation);
            occupiedCount.addEventListener('input', syncSplitValidation);
            noticeCount.addEventListener('input', syncSplitValidation);
            syncSplitValidation();
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
