@php
    $currentSort = $currentSort ?? (request('sort') ?: 'updated');
@endphp
<form method="get" action="{{ route('public.properties') }}" class="bg-white border border-gray-100 p-4 sm:p-5 rounded-2xl shadow-sm lg:sticky lg:top-20 space-y-4">
    <h3 class="text-sm font-black text-gray-900 uppercase tracking-wide">Filters</h3>

    @if (request('q'))
        <input type="hidden" name="q" value="{{ request('q') }}">
    @endif

    <div class="space-y-3">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1" for="filter-city">Location</label>
            <select id="filter-city" name="city" class="w-full min-h-[2.5rem] rounded-lg border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">All locations</option>
                @foreach ($filterCities as $c)
                    <option value="{{ $c }}" @selected(request('city') === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1" for="filter-type">Property type</label>
            <select id="filter-type" name="unit_type" class="w-full min-h-[2.5rem] rounded-lg border-gray-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">All types</option>
                @foreach ($filterUnitTypes as $typeValue => $typeLabel)
                    <option value="{{ $typeValue }}" @selected(request('unit_type') === $typeValue)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1" for="filter-bedrooms">Bedrooms</label>
                <select id="filter-bedrooms" name="bedrooms" class="w-full min-h-[2.5rem] rounded-lg border-gray-200 text-sm">
                    <option value="any" @selected(request('bedrooms', 'any') === 'any' || request('bedrooms') === '' || request('bedrooms') === null)>Any</option>
                    @for ($b = 0; $b <= 6; $b++)
                        <option value="{{ $b }}" @selected((string) request('bedrooms') === (string) $b)>{{ $b === 0 ? 'Studio' : $b.' bed' }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1" for="filter-sort">Sort</label>
                <select id="filter-sort" name="sort" class="w-full min-h-[2.5rem] rounded-lg border-gray-200 text-sm">
                    <option value="updated" @selected($currentSort === 'updated')>Recently updated</option>
                    <option value="featured" @selected($currentSort === 'featured')>Featured first</option>
                    <option value="rent_asc" @selected($currentSort === 'rent_asc')>Price: low to high</option>
                    <option value="rent_desc" @selected($currentSort === 'rent_desc')>Price: high to low</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Rent range (KES/mo)</label>
            <div class="flex items-center gap-2">
                <input type="number" name="min_rent" min="0" step="500" placeholder="Min" value="{{ request('min_rent') }}" class="w-full min-h-[2.5rem] rounded-lg border-gray-200 text-sm">
                <span class="text-gray-400">–</span>
                <input type="number" name="max_rent" min="0" step="500" placeholder="Max" value="{{ request('max_rent') }}" class="w-full min-h-[2.5rem] rounded-lg border-gray-200 text-sm">
            </div>
        </div>
    </div>

    <button type="submit" class="public-btn public-btn-primary w-full !min-h-[2.5rem] !text-sm">Apply filters</button>
    <a href="{{ route('public.properties') }}" class="block text-center text-xs font-bold text-emerald-600 hover:text-emerald-700">Clear all filters</a>
</form>
