<x-property.workspace
    title="Amenities"
    subtitle="Library of amenity labels and which properties they apply to."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No amenities in library"
    empty-hint="Add types below, then tag properties."
>
    @php
        $amenityCfg = $amenityFields ?? [];
        $amenityRequired = fn (string $k, bool $d = false) => (bool) (($amenityCfg[$k]['required'] ?? $d) && ($amenityCfg[$k]['enabled'] ?? true));
    @endphp
    <x-slot name="above">
        @if ($errors->has('amenity'))
            <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('amenity') }}</p>
        @endif
        @php
            $amenityLibraryFormHasErrors = $errors->hasAny(['name', 'category']);
            $amenityAttachFormHasErrors = $errors->hasAny(['pm_amenity_id', 'property_id']);
        @endphp
        <div x-data="{ showAmenityLibraryForm: @js($amenityLibraryFormHasErrors), showAmenityAttachForm: @js($amenityAttachFormHasErrors) }" class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="showAmenityLibraryForm = !showAmenityLibraryForm"
                >
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span x-text="showAmenityLibraryForm ? 'Hide amenity library form' : 'Add amenity to library'"></span>
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    @click="showAmenityAttachForm = !showAmenityAttachForm"
                >
                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                    <span x-text="showAmenityAttachForm ? 'Hide property tag form' : 'Tag amenity to property'"></span>
                </button>
            </div>
        <div class="grid gap-4 lg:grid-cols-2 max-w-5xl">
            <form method="post" action="{{ route('property.properties.amenities.store') }}" x-show="showAmenityLibraryForm" x-cloak class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                @csrf
                <h3 class="text-sm font-semibold text-slate-900">Add to library</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" @required($amenityRequired('name', true)) class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="e.g. Solar water heating" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="e.g. Utilities" />
                    @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save amenity</button>
            </form>

            <form method="post" action="{{ route('property.properties.amenities.attach') }}" x-show="showAmenityAttachForm" x-cloak class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3"
                x-data="{
                    selectedAmenity: '{{ old('pm_amenity_id', '') }}',
                    selectedProperty: '{{ old('property_id', '') }}',
                    map: @js($amenityPropertyIds ?? []),
                    isTaken(propertyId) {
                        if (!this.selectedAmenity) return false;
                        const used = this.map[this.selectedAmenity] || [];
                        return used.includes(Number(propertyId));
                    }
                }"
            >
                @csrf
                <h3 class="text-sm font-semibold text-slate-900">Tag a property</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Amenity</label>
                    <select name="pm_amenity_id" x-model="selectedAmenity" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @foreach ($amenities as $a)
                            <option value="{{ $a->id }}" @selected(old('pm_amenity_id') == $a->id)>{{ $a->name }}@if ($a->category) ({{ $a->category }})@endif</option>
                        @endforeach
                    </select>
                    @error('pm_amenity_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Property</label>
                    <select name="property_id" x-model="selectedProperty" @required($amenityRequired('property_id', true)) class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @foreach($properties as $p)
                            <option
                                value="{{ $p->id }}"
                                @selected((string) old('property_id', request('property_id')) === (string) $p->id)
                                x-show="!isTaken({{ $p->id }})"
                            >
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Only properties not yet tagged with the selected amenity are shown.</p>
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50">Attach</button>
            </form>
        </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('property.properties.amenities', array_merge(request()->query(), ['preset' => 'tagged', 'tagged' => 'yes']), false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Tagged only</a>
        <a href="{{ route('property.properties.amenities', array_merge(request()->query(), ['preset' => 'unused', 'tagged' => 'no']), false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Unused only</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.properties.amenities') }}" class="w-full grid gap-2 sm:grid-cols-2 lg:grid-cols-7">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search amenity or category..." class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 lg:col-span-2" />
            <input type="text" name="category" value="{{ $filters['category'] ?? '' }}" placeholder="Category..." class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            <select name="property_id" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">All properties</option>
                @foreach(($properties ?? []) as $p)
                    <option value="{{ $p->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="tagged" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">Tag state: All</option>
                <option value="yes" @selected(($filters['tagged'] ?? '') === 'yes')>Tagged</option>
                <option value="no" @selected(($filters['tagged'] ?? '') === 'no')>Untagged</option>
            </select>
            <input type="hidden" name="preset" value="{{ $filters['preset'] ?? '' }}" />
            <div class="flex items-center gap-2 lg:col-span-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.properties.amenities', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
                <a href="{{ route('property.properties.amenities', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">CSV</a>
                <a href="{{ route('property.properties.amenities', array_merge(request()->query(), ['export' => 'pdf']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">PDF</a>
                <a href="{{ route('property.properties.amenities', array_merge(request()->query(), ['export' => 'word']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Word</a>
            </div>
        </form>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Category summary</h3>
            <table class="mt-3 min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-2">Category</th>
                        <th class="py-2">Amenity count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($categorySummary ?? []) as $row)
                        <tr class="border-t border-slate-100">
                            <td class="py-2">{{ $row['category'] }}</td>
                            <td class="py-2 tabular-nums">{{ (int) $row['count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-6 text-center text-slate-500">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Library cleanup</h3>
            <p class="text-xs text-slate-500 mt-1">Delete removes a label only when it is not attached to any property.</p>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($amenities as $a)
                    @if (($a->properties_count ?? 0) === 0)
                        <li>
                            <form method="post" action="{{ route('property.properties.amenities.destroy', $a) }}" onsubmit="return confirm('Delete “{{ $a->name }}” from the library?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-200 px-2 py-1 text-xs text-red-700 hover:bg-red-50">{{ $a->name }} ×</button>
                            </form>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>

    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-slate-900">Tags by property</h3>
        <p class="text-xs text-slate-500">Amenities are managed at property level.</p>
        <ul class="divide-y divide-slate-100 text-sm max-h-80 overflow-y-auto">
            @forelse ($properties as $p)
                <li class="py-3 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                    <div>
                        <span class="font-medium text-slate-900">{{ $p->name }}</span>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @forelse ($p->amenities as $am)
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                    {{ $am->name }}
                                    <form method="post" action="{{ route('property.properties.amenities.detach') }}" class="inline" onsubmit="return confirm('Remove this tag?');">
                                        @csrf
                                        <input type="hidden" name="pm_amenity_id" value="{{ $am->id }}" />
                                        <input type="hidden" name="property_id" value="{{ $p->id }}" />
                                        <button type="submit" class="text-red-600 hover:underline font-semibold leading-none" title="Remove">×</button>
                                    </form>
                                </span>
                            @empty
                                <span class="text-slate-400 text-xs">No amenities</span>
                            @endforelse
                        </div>
                    </div>
                </li>
            @empty
                <li class="py-6 text-center text-slate-500">No properties yet.</li>
            @endforelse
        </ul>
    </div>

    <x-slot name="footer">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing {{ (int) (($amenitiesPage ?? null)?->firstItem() ?? 0) }}-{{ (int) (($amenitiesPage ?? null)?->lastItem() ?? 0) }}
                of {{ (int) (($amenitiesPage ?? null)?->total() ?? 0) }} amenities.
            </p>
            <div>
                {{ ($amenitiesPage ?? null)?->links() }}
            </div>
        </div>
    </x-slot>
</x-property.workspace>
