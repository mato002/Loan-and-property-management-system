<x-property.workspace
    title="Setup a public listing"
    back-route="property.listings.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="toolbar">
        <a
            href="#listing-setup"
            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
            Setup flow
        </a>
        <a
            href="#vacant-roster"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Vacant roster
        </a>
        <a
            href="#listing-publish"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Publish editor
        </a>
        <a
            href="{{ route('property.properties.units', absolute: false) }}"
            data-turbo-frame="property-main"
            data-property-nav="property.properties.units"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Properties → Units
        </a>
    </x-slot>

    <div id="listing-setup" class="space-y-6 max-w-2xl">
        @if ($vacantUnits->isEmpty())
            <div class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/30 p-6">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">No vacant units yet</p>
                <p class="mt-2 text-sm text-amber-900/80 dark:text-amber-200/90">Add a unit and set status to vacant before you can create a public listing.</p>
                <a
                    href="{{ route('property.properties.units', absolute: false) }}"
                    data-turbo-frame="property-main"
                    data-property-nav="property.properties.units"
                    class="mt-4 inline-flex rounded-xl bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600"
                >Go to Units</a>
            </div>
        @else
            <form
                method="post"
                action="{{ route('property.listings.start') }}"
                class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4"
            >
                @csrf
                <div>
                    <label for="property_unit_id" class="block text-sm font-medium text-slate-800 dark:text-slate-100">Vacant unit</label>
                    <div class="mt-2">
                        <x-property.quick-create-select
                            name="property_unit_id"
                            :required="true"
                            select-id="property_unit_id"
                            placeholder="Select property / unit…"
                            :options="collect($vacantUnits)->map(function($u) {
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
                                    ['name' => 'property_id', 'label' => 'Property', 'required' => true, 'span' => '2', 'type' => 'select', 'placeholder' => 'Select property', 'options' => collect($vacantUnits)->map(fn($u) => ['value' => $u->property_id, 'label' => $u->property->name])->unique('value')->values()->all()],
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
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Open publish editor
                </button>
            </form>
        @endif
    </div>

    <section id="listing-publish" class="mt-10 space-y-4">
        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Publish editor</h2>

        @if ($selectedUnit)
            <div class="grid gap-6 lg:grid-cols-2 max-w-5xl">
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Upload photos</h3>
                    <form
                        method="post"
                        action="{{ route('property.listings.vacant.public.photos.store', $selectedUnit) }}"
                        enctype="multipart/form-data"
                        class="space-y-3"
                    >
                        @csrf
                        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required class="block w-full text-sm text-slate-600 dark:text-slate-300" />
                        @if ($errors->has('photos') || $errors->has('photos.*'))
                            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                                @foreach ($errors->get('photos') as $msg)
                                    <p>{{ $msg }}</p>
                                @endforeach
                                @foreach ($errors->get('photos.*') as $messages)
                                    @foreach ((array) $messages as $msg)
                                        <p>{{ $msg }}</p>
                                    @endforeach
                                @endforeach
                            </div>
                        @endif
                        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Upload</button>
                    </form>

                    <div class="border-t border-slate-200 dark:border-slate-600 pt-4 space-y-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gallery ({{ $selectedUnit->publicImages->count() }})</h4>
                        <ul class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach ($selectedUnit->publicImages as $img)
                                <li class="relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 aspect-[4/3]">
                                    <img src="{{ $img->publicUrl() }}" alt="" class="w-full h-full object-cover" />
                                    @if ($loop->first)
                                        <span class="absolute top-1 left-1 rounded-md bg-emerald-600 text-white text-[10px] px-2 py-1 font-semibold">Main image</span>
                                    @else
                                        <form
                                            method="post"
                                            action="{{ route('property.listings.vacant.public.photos.main', [$selectedUnit, $img]) }}"
                                            class="absolute top-1 left-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            @csrf
                                            <button type="submit" class="rounded-md bg-indigo-600 text-white text-xs px-2 py-1 font-medium hover:bg-indigo-700">Set main</button>
                                        </form>
                                    @endif
                                    <form
                                        method="post"
                                        action="{{ route('property.listings.vacant.public.photos.destroy', [$selectedUnit, $img]) }}"
                                        class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                        data-swal-confirm="Remove this photo?"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-md bg-red-600 text-white text-xs px-2 py-1 font-medium hover:bg-red-700">Remove</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Description &amp; publish</h3>
                    <form method="post" action="{{ route('property.listings.vacant.public.update', $selectedUnit) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="public_listing_description">Public description</label>
                            <textarea
                                id="public_listing_description"
                                name="public_listing_description"
                                rows="8"
                                class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                            >{{ old('public_listing_description', $selectedUnit->public_listing_description) }}</textarea>
                            @error('public_listing_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                name="public_listing_published"
                                value="1"
                                class="mt-1 rounded border-slate-300 text-blue-600"
                                @checked(old('public_listing_published', $selectedUnit->public_listing_published))
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                <span class="font-medium text-slate-900 dark:text-white">Published on public website</span>
                            </span>
                        </label>
                        @error('public_listing_published')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Save</button>
                    </form>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm text-sm text-slate-700 dark:text-slate-300">
                Select a vacant unit above to open the publish editor.
            </div>
        @endif
    </section>

    <section id="vacant-roster" class="mt-10 space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Vacant roster</h2>
        </div>

        <div class="flex flex-wrap gap-2">
            <input
                type="search"
                data-table-filter="parent"
                autocomplete="off"
                placeholder="Search unit, property, rent…"
                class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
            />
            <select
                data-table-filter="parent"
                class="w-full min-w-0 sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
            >
                <option value="">All statuses</option>
                <option value="featured">Featured</option>
                <option value="standard">Standard (on site)</option>
            </select>
        </div>

        <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Unit</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Property</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Asking rent</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Vacant since</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Photos</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Status</th>
                        <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vacantUnits as $u)
                        @php
                            $statusWord = $u->public_listing_published ? 'featured' : 'standard';
                            $filterText = mb_strtolower(
                                implode(' ', [
                                    (string) $u->label,
                                    (string) $u->property->name,
                                    (string) $u->rent_amount,
                                    \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount),
                                    $u->vacant_since?->format('Y-m-d') ?? '',
                                    (string) $u->publicImages->count(),
                                    $statusWord,
                                ])
                            );
                        @endphp
                        <tr
                            class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                            data-filter-text="{{ e($filterText) }}"
                        >
                            <td class="px-3 sm:px-4 py-3 text-slate-900 dark:text-white font-medium">{{ $u->label }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">{{ $u->property->name }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount) }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400">{{ $u->vacant_since?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ $u->publicImages->count() }}</td>
                            <td class="px-3 sm:px-4 py-3">
                                @if ($u->public_listing_published)
                                    <span class="inline-flex rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-0.5 text-xs font-semibold">Featured</span>
                                @else
                                    <span class="inline-flex rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200 px-2 py-0.5 text-xs font-semibold">On Discover</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-4 py-3">
                                <a href="{{ route('property.listings.create', ['selected_unit' => $u->id], absolute: false) }}#listing-publish" data-turbo-frame="property-main" data-property-nav="property.listings.create" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">Photos &amp; publish</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center text-slate-600 dark:text-slate-400">No vacant units. Create units under Properties first.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-property.workspace>
