<x-public-layout>
    <div class="bg-gray-50 border-b border-gray-200 shadow-inner">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <h1 class="text-4xl font-black text-gray-900 tracking-tight">Discover Properties</h1>
            <p class="text-lg text-gray-500 mt-2">Browse our extensive list of available rentals and homes for sale.</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col lg:flex-row gap-8">
            <div class="w-full lg:w-1/4">
                <form method="get" action="{{ route('public.properties') }}" class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm lg:sticky lg:top-28 space-y-6">
                    <h3 class="text-lg font-black text-gray-900 mb-1">Filter Search</h3>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2" for="city">Location</label>
                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="{{ request('city') }}"
                            placeholder="City"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Property Type</label>
                        <select class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-700 outline-none" disabled title="Coming soon">
                            <option>Any</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Price Range</label>
                        <div class="flex items-center gap-2">
                            <input type="number" placeholder="Min" class="w-full rounded-xl border-gray-300 shadow-sm outline-none" disabled title="Coming soon" />
                            <span class="text-gray-400">-</span>
                            <input type="number" placeholder="Max" class="w-full rounded-xl border-gray-300 shadow-sm outline-none" disabled title="Coming soon" />
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-colors shadow-md shadow-indigo-600/20">Apply Filters</button>
                </form>
            </div>

            <div class="w-full lg:w-3/4">
                <div class="flex justify-between items-center mb-6">
                    <p class="text-gray-600 font-medium">
                        Showing <span class="font-bold text-gray-900">{{ $units->total() }}</span> {{ Str::plural('result', $units->total()) }}
                    </p>
                    <span class="text-sm text-gray-500">Sorted by recently updated</span>
                </div>

                @if ($units->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-16 text-center text-gray-500">
                        <p class="font-bold text-gray-800 text-lg mb-2">No published listings yet</p>
                        <p class="text-sm">Agents can publish vacant units from <strong>Listings → Vacant unit listings</strong> in the property workspace.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        @foreach ($units as $unit)
                            @include('public.partials.listing-card', ['unit' => $unit, 'placeholderImage' => $listingPlaceholderImage, 'imageHeight' => 'h-60'])
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
