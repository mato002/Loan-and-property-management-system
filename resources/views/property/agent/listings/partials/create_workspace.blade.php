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
    <div
        id="listing-publish-slot"
        class="mb-8 scroll-mt-24 min-h-0"
        data-listings-create-url="{{ route('property.listings.create', absolute: false) }}"
    >
        @if ($selectedUnit)
            @include('property.agent.listings.partials.publish_editor', ['selectedUnit' => $selectedUnit])
        @endif
    </div>

    <section
        id="vacant-roster"
        class="space-y-3"
        x-data="listingVacantRoster(@js($vacantUnits->map(function ($u) {
            $property = $u->property;
            $city = trim((string) ($property?->city ?? ''));
            if ($city === '') {
                $city = 'Other';
            }

            return [
                'id' => (int) $u->id,
                'city' => $city,
                'city_label' => $property ? \App\Support\Property\PublicApplyCatalog::cityLabel($city) : $city,
                'area' => $property ? \App\Support\Property\PublicApplyCatalog::areaLabel($property) : '',
                'property_id' => (int) $u->property_id,
                'property' => (string) ($property?->name ?? ''),
                'property_label' => $property ? \App\Support\Property\PublicApplyCatalog::publicBuildingName($property) : (string) $u->label,
                'unit_type' => (string) ($u->unit_type ?: ''),
                'unit_type_label' => $u->unitTypeLabel(),
                'rent' => $u->listedRentAmount(),
                'has_photos' => $u->publicImages->isNotEmpty(),
                'featured' => (bool) $u->public_listing_published,
                'search' => mb_strtolower(implode(' ', array_filter([
                    (string) $u->label,
                    (string) ($property?->name ?? ''),
                    (string) ($property?->city ?? ''),
                    $property ? \App\Support\Property\PublicApplyCatalog::areaLabel($property) : '',
                    (string) $u->listedRentAmount(),
                ]))),
            ];
        })->values()))"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Vacant units</h2>
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400" x-text="visibleCount + ' of {{ $vacantUnits->count() }} shown'"></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 print-hide">
            <input
                type="search"
                x-model="q"
                autocomplete="off"
                placeholder="Search unit, building, rent…"
                class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 sm:col-span-2"
            />
            <select x-model="city" @change="onCity()" class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All towns</option>
                <template x-for="opt in cities" :key="opt.value">
                    <option :value="opt.value" x-text="opt.label"></option>
                </template>
            </select>
            <select x-model="area" @change="onArea()" class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All estates</option>
                <template x-for="opt in areas" :key="opt.value">
                    <option :value="opt.value" x-text="opt.label"></option>
                </template>
            </select>
            <select x-model="property_id" @change="onBuilding()" class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All buildings</option>
                <template x-for="opt in buildings" :key="opt.value">
                    <option :value="opt.value" x-text="opt.label"></option>
                </template>
            </select>
            <select x-model="unit_type" class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All home types</option>
                <template x-for="opt in unitTypes" :key="opt.value">
                    <option :value="opt.value" x-text="opt.label"></option>
                </template>
            </select>
            <select x-model="max_rent" class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Any asking rent</option>
                <template x-for="opt in rentBands" :key="opt.value">
                    <option :value="opt.value" x-text="opt.label"></option>
                </template>
            </select>
            <select x-model="photos" class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All photos</option>
                <option value="with">With photos</option>
                <option value="none">No photos</option>
            </select>
            <select x-model="listing" class="w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All listing status</option>
                <option value="featured">Featured</option>
                <option value="standard">On website</option>
            </select>
            <button type="button" @click="clear()" class="min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 px-3 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                Clear filters
            </button>
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
                    @foreach ($vacantUnits as $u)
                        @php
                            $isSelected = $selectedUnit && (int) $selectedUnit->id === (int) $u->id;
                        @endphp
                        <tr
                            @class([
                                'border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40',
                                'bg-blue-50/80 dark:bg-blue-950/30 ring-1 ring-inset ring-blue-200/80 dark:ring-blue-800/60' => $isSelected,
                            ])
                            data-listing-unit-id="{{ $u->id }}"
                            x-show="isVisible({{ (int) $u->id }})"
                            x-cloak
                        >
                            <td class="px-3 sm:px-4 py-3 text-slate-900 dark:text-white font-medium">{{ $u->label }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">{{ $u->property->name }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($u->listedRentAmount()) }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400">{{ $u->vacant_since?->format('d M Y') ?? '—' }}</td>
                            <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ $u->publicImages->count() }}</td>
                            <td class="px-3 sm:px-4 py-3">
                                @if ($u->public_listing_published)
                                    <span class="inline-flex rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-0.5 text-xs font-semibold">Featured</span>
                                @else
                                    <span class="inline-flex rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200 px-2 py-0.5 text-xs font-semibold">On website</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-4 py-3">
                                <a
                                    href="{{ route('property.listings.publish-panel', $u, absolute: false) }}"
                                    data-listing-publish
                                    data-listing-unit-id="{{ $u->id }}"
                                    data-property-form-modal="off"
                                    class="text-blue-600 dark:text-blue-400 font-medium hover:underline"
                                >Add photos</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p x-show="visibleCount === 0" x-cloak class="text-sm text-slate-500 dark:text-slate-400 px-1">No vacant units match these filters. Clear filters to see the full list.</p>
    </section>
@endif
