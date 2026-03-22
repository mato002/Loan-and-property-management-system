<x-property.workspace
    title="Amenities"
    subtitle="Library of amenity labels and which units they apply to. Public listing photos stay under Listings → Vacant units."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No amenities in library"
    empty-hint="Add types below, then tag units."
>
    <x-slot name="above">
        @if ($errors->has('amenity'))
            <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('amenity') }}</p>
        @endif
        <div class="grid gap-4 lg:grid-cols-2 max-w-5xl">
            <form method="post" action="{{ route('property.properties.amenities.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add to library</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Solar water heating" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Utilities" />
                    @error('category')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save amenity</button>
            </form>

            <form method="post" action="{{ route('property.properties.amenities.attach') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tag a unit</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amenity</label>
                    <select name="pm_amenity_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @foreach ($amenities as $a)
                            <option value="{{ $a->id }}" @selected(old('pm_amenity_id') == $a->id)>{{ $a->name }}@if ($a->category) ({{ $a->category }})@endif</option>
                        @endforeach
                    </select>
                    @error('pm_amenity_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
                    <select name="property_unit_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @foreach ($units as $u)
                            <option value="{{ $u->id }}" @selected(old('property_unit_id') == $u->id)>{{ $u->property->name }} — {{ $u->label }}</option>
                        @endforeach
                    </select>
                    @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">Attach</button>
            </form>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search amenity…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>

    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Library (unused only)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">Delete removes the label from the library when it is not attached to any unit.</p>
        <ul class="flex flex-wrap gap-2">
            @foreach ($amenities as $a)
                @if ($a->units_count === 0)
                    <li>
                        <form method="post" action="{{ route('property.properties.amenities.destroy', $a) }}" onsubmit="return confirm('Delete “{{ $a->name }}” from the library?');" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-red-200 dark:border-red-900/50 px-2 py-1 text-xs text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/30">{{ $a->name }} ×</button>
                        </form>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>

    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tags by unit</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400">Remove a tag with ×. To retire a label that is still in use, detach it from every unit first, then delete it above.</p>
        <ul class="divide-y divide-slate-100 dark:divide-slate-700 text-sm max-h-80 overflow-y-auto">
            @forelse ($units as $u)
                <li class="py-3 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                    <div>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $u->property->name }} — {{ $u->label }}</span>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @forelse ($u->amenities as $am)
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-xs text-slate-700 dark:text-slate-200">
                                    {{ $am->name }}
                                    <form method="post" action="{{ route('property.properties.amenities.detach') }}" class="inline" onsubmit="return confirm('Remove this tag?');">
                                        @csrf
                                        <input type="hidden" name="pm_amenity_id" value="{{ $am->id }}" />
                                        <input type="hidden" name="property_unit_id" value="{{ $u->id }}" />
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-semibold leading-none" title="Remove">×</button>
                                    </form>
                                </span>
                            @empty
                                <span class="text-slate-400 dark:text-slate-500 text-xs">No amenities</span>
                            @endforelse
                        </div>
                    </div>
                </li>
            @empty
                <li class="py-6 text-center text-slate-500 dark:text-slate-400">No units yet.</li>
            @endforelse
        </ul>
    </div>
</x-property.workspace>
