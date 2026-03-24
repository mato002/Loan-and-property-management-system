<x-public-layout>
    @php($brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name'))
    <!-- Hero Section -->
    <div class="relative bg-gray-900 border-b border-gray-800">
        <div class="absolute inset-0 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2850&q=80" alt="Beautiful modern house" class="w-full h-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/60 to-transparent"></div>
        </div>
        
        <div class="relative w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-32 lg:py-48 flex flex-col items-center text-center">
            <h1 class="text-4xl md:text-6xl font-black text-white tracking-tight mb-6 max-w-4xl">
                Find Your Perfect Space With <span class="text-indigo-400">{{ $brandName }}</span>
            </h1>
            <p class="text-xl text-gray-300 max-w-2xl mb-12">
                Discover the best residential and commercial properties curated just for you. Seamlessly search, apply, and manage your properties entirely online.
            </p>
            
            <!-- Search Bar -->
            <form method="get" action="{{ route('public.properties') }}" class="w-full max-w-4xl bg-white p-2 sm:p-3 rounded-2xl shadow-2xl flex flex-col sm:flex-row gap-2 sm:gap-0">
                <label class="sr-only" for="hero-city">{{ __('City') }}</label>
                <select
                    id="hero-city"
                    name="city"
                    class="flex-1 border-0 focus:ring-0 px-5 font-medium text-gray-700 bg-transparent py-4 text-lg w-full sm:w-auto outline-none"
                >
                    <option value="">{{ __('Select city') }}</option>
                    @foreach (($availableCities ?? collect()) as $city)
                        <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                    @endforeach
                </select>
                <div class="hidden sm:block w-px h-10 bg-gray-200 self-center mx-2"></div>
                <label class="sr-only" for="hero-unit-type">{{ __('House type') }}</label>
                <select
                    id="hero-unit-type"
                    name="unit_type"
                    class="flex-1 border-0 focus:ring-0 px-5 font-medium text-gray-700 bg-transparent py-4 text-lg w-full sm:w-auto outline-none"
                >
                    <option value="">{{ __('All house types') }}</option>
                    @foreach (($availableUnitTypes ?? []) as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(request('unit_type') === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
                <div class="hidden sm:block w-px h-10 bg-gray-200 self-center mx-2"></div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-4 rounded-xl transition-colors w-full sm:w-auto text-lg flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    {{ __('Search') }}
                </button>
            </form>

            @if (! empty($availableUnitTypes))
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-300 mr-1">House types:</span>
                    @foreach ($availableUnitTypes as $typeValue => $typeLabel)
                        <a
                            href="{{ route('public.properties', ['unit_type' => $typeValue, 'sort' => 'featured']) }}"
                            class="inline-flex items-center rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white hover:text-indigo-700 transition-colors"
                        >
                            {{ $typeLabel }}
                        </a>
                    @endforeach
                </div>
            @endif
            
            <div class="mt-8 flex flex-wrap gap-x-4 gap-y-2 text-sm font-medium text-gray-300">
                <span>Popular:</span>
                <a href="{{ route('public.properties', ['bedrooms' => 2, 'sort' => 'featured']) }}" class="hover:text-white underline underline-offset-4">Downtown Apartments</a>
                <a href="{{ route('public.properties', ['sort' => 'rent_desc']) }}" class="hover:text-white underline underline-offset-4">Commercial Spaces</a>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="bg-white border-b border-gray-100">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-16">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-x divide-gray-100">
                <div>
                    <p class="text-4xl font-black text-indigo-600 mb-2">1,200+</p>
                    <p class="text-gray-500 font-medium">Properties Managed</p>
                </div>
                <div>
                    <p class="text-4xl font-black text-indigo-600 mb-2">350+</p>
                    <p class="text-gray-500 font-medium">Happy Landlords</p>
                </div>
                <div>
                    <p class="text-4xl font-black text-indigo-600 mb-2">5,000+</p>
                    <p class="text-gray-500 font-medium">Active Tenants</p>
                </div>
                <div>
                    <p class="text-4xl font-black text-indigo-600 mb-2">15</p>
                    <p class="text-gray-500 font-medium">Years Experience</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Properties -->
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-24">
        <div class="flex justify-between items-end mb-12">
            <div>
                <h2 class="text-3xl font-black text-gray-900 tracking-tight sm:text-4xl mb-3">Featured Properties</h2>
                <p class="text-gray-500 text-lg">{{ __('Vacant units from your portfolio — featured listings with photos appear first.') }}</p>
            </div>
            <a href="{{ route('public.properties') }}" class="hidden md:inline-flex items-center gap-2 text-indigo-600 font-bold hover:text-indigo-700">
                View All Listings
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse ($featuredUnits as $unit)
                @include('public.partials.listing-card', ['unit' => $unit, 'placeholderImage' => $listingPlaceholderImage])
            @empty
                <div class="col-span-full text-center py-12 text-gray-500 text-lg">{{ __('No vacant units yet. Add properties and units in the agent workspace — they will show here automatically when status is vacant.') }}</div>
            @endforelse
        </div>
    </div>

    <!-- Tenant CTA Area -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 py-20">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-black text-white sm:text-4xl mb-6 tracking-tight">Ready to Find Your Next Home?</h2>
            <p class="text-xl text-slate-200 mb-10">Browse verified listings, compare options, and book a viewing in minutes.</p>
            <a href="{{ route('public.properties') }}" class="inline-block bg-indigo-500 hover:bg-indigo-400 text-white font-bold px-10 py-4 rounded-xl shadow-xl shadow-indigo-900/30 transition-transform hover:-translate-y-1">Browse Available Homes</a>
        </div>
    </div>
</x-public-layout>
