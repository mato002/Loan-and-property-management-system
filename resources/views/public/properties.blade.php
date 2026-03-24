@php
    $currentSort = request('sort') ?: 'updated';
@endphp
<x-public-layout :page-title="__('Discover Properties')">
    <div class="bg-gray-50 border-b border-gray-200 shadow-inner">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-10">
            <h1 class="text-4xl font-black text-gray-900 tracking-tight">Discover Properties</h1>
            <p class="text-lg text-gray-500 mt-2">{{ __('Vacant units from your property panel. Filter by city — listings update as soon as agents mark units vacant.') }}</p>
        </div>
    </div>

    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-12">
        <div class="flex flex-col lg:flex-row gap-8">
            <div class="w-full lg:w-1/4">
                <form method="get" action="{{ route('public.properties') }}" class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm lg:sticky lg:top-28 space-y-6">
                    <h3 class="text-lg font-black text-gray-900 mb-1">Filter Search</h3>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2" for="city">Location</label>
                        <select
                            id="city"
                            name="city"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none"
                        >
                            <option value="">{{ __('All cities') }}</option>
                            @foreach ($filterCities as $c)
                                <option value="{{ $c }}" @selected(request('city') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2" for="unit_type">House type</label>
                        <select
                            id="unit_type"
                            name="unit_type"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-700 outline-none"
                        >
                            <option value="">{{ __('All house types') }}</option>
                            @foreach ($filterUnitTypes as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" @selected(request('unit_type') === $typeValue)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2" for="bedrooms">Bedrooms</label>
                        <select
                            id="bedrooms"
                            name="bedrooms"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-700 outline-none"
                        >
                            <option value="any" @selected(request('bedrooms', 'any') === 'any' || request('bedrooms') === '' || request('bedrooms') === null)>Any</option>
                            @for ($b = 0; $b <= 6; $b++)
                                <option value="{{ $b }}" @selected((string) request('bedrooms') === (string) $b)>{{ $b === 0 ? 'No separate bedroom' : $b.' '.Str::plural('bedroom', $b) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">{{ __('Price range (KES / mo)') }}</label>
                        <div class="flex items-center gap-2">
                            <input
                                type="number"
                                name="min_rent"
                                min="0"
                                step="1"
                                placeholder="Min"
                                value="{{ request('min_rent') }}"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none"
                            />
                            <span class="text-gray-400 shrink-0">–</span>
                            <input
                                type="number"
                                name="max_rent"
                                min="0"
                                step="1"
                                placeholder="Max"
                                value="{{ request('max_rent') }}"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2" for="sort">Sort</label>
                        <select
                            id="sort"
                            name="sort"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-700 outline-none"
                        >
                            <option value="updated" @selected($currentSort === 'updated')>{{ __('Recently updated') }}</option>
                            <option value="featured" @selected($currentSort === 'featured')>{{ __('Featured first') }}</option>
                            <option value="rent_asc" @selected($currentSort === 'rent_asc')>{{ __('Rent: low to high') }}</option>
                            <option value="rent_desc" @selected($currentSort === 'rent_desc')>{{ __('Rent: high to low') }}</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-colors shadow-md shadow-indigo-600/20">{{ __('Apply filters') }}</button>
                    <p class="text-center">
                        <a href="{{ route('public.properties') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-700">{{ __('Clear filters') }}</a>
                    </p>
                </form>
            </div>

            <div class="w-full lg:w-3/4">
                <div class="flex justify-between items-center mb-6">
                    <p class="text-gray-600 font-medium">
                        Showing <span class="font-bold text-gray-900">{{ $units->total() }}</span> {{ Str::plural('result', $units->total()) }}
                    </p>
                    <span class="text-sm text-gray-500">{{ __('Sorted by') }} <span class="font-semibold text-gray-700">{{ $sortLabel }}</span></span>
                </div>

                @if ($units->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-16 text-center text-gray-500">
                        <p class="font-bold text-gray-800 text-lg mb-2">{{ __('No vacant units to show') }}</p>
                        <p class="text-sm">{{ __('Create properties and units under the agent workspace and set unit status to vacant. Optional: use Listings → Vacant units to upload photos and feature a listing.') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        @foreach ($units as $unit)
                            @include('public.partials.listing-card', ['unit' => $unit, 'placeholderImage' => $listingPlaceholderImage])
                        @endforeach
                    </div>

                    <div class="mt-12">
                        {{ $units->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-public-layout>
