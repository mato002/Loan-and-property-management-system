<x-public-layout>
    <!-- Hero Section -->
    <div class="relative bg-gray-900 border-b border-gray-800">
        <div class="absolute inset-0 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2850&q=80" alt="Beautiful modern house" class="w-full h-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/60 to-transparent"></div>
        </div>
        
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32 lg:py-48 flex flex-col items-center text-center">
            <h1 class="text-4xl md:text-6xl font-black text-white tracking-tight mb-6 max-w-4xl">
                Find Your Perfect Space With <span class="text-indigo-400">PrimeEstate</span>
            </h1>
            <p class="text-xl text-gray-300 max-w-2xl mb-12">
                Discover the best residential and commercial properties curated just for you. Seamlessly search, apply, and manage your properties entirely online.
            </p>
            
            <!-- Search Bar -->
            <form method="get" action="{{ route('public.properties') }}" class="w-full max-w-4xl bg-white p-2 sm:p-3 rounded-2xl shadow-2xl flex flex-col sm:flex-row gap-2 sm:gap-0">
                <label class="sr-only" for="hero-city">{{ __('City') }}</label>
                <input
                    type="text"
                    id="hero-city"
                    name="city"
                    value="{{ request('city') }}"
                    placeholder="{{ __('City or area') }}"
                    class="flex-1 border-0 focus:ring-0 px-5 font-medium text-gray-700 bg-transparent py-4 text-lg w-full sm:w-auto outline-none"
                    autocomplete="address-level2"
                />
                <div class="hidden sm:block w-px h-10 bg-gray-200 self-center mx-2"></div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-4 rounded-xl transition-colors w-full sm:w-auto text-lg flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    {{ __('Search') }}
                </button>
            </form>
            
            <div class="mt-8 flex gap-4 text-sm font-medium text-gray-300">
                <span>Popular:</span>
                <a href="{{ route('public.properties') }}" class="hover:text-white underline underline-offset-4">Downtown Apartments</a>
                <a href="{{ route('public.properties') }}" class="hover:text-white underline underline-offset-4">Commercial Spaces</a>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
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
                @include('public.partials.listing-card', ['unit' => $unit, 'placeholderImage' => $listingPlaceholderImage, 'imageHeight' => 'h-64'])
            @empty
                <div class="col-span-full text-center py-12 text-gray-500 text-lg">{{ __('No vacant units yet. Add properties and units in the agent workspace — they will show here automatically when status is vacant.') }}</div>
            @endforelse
        </div>
    </div>

    <!-- CTA Area -->
    <div class="bg-indigo-600 py-20">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-black text-white sm:text-4xl mb-6 tracking-tight">Are you a Landlord?</h2>
            <p class="text-xl text-indigo-100 mb-10">We provide state-of-the-art property management capabilities. Hand your properties over to the best firm in the industry.</p>
            <a href="{{ route('public.signup') }}" class="inline-block bg-white hover:bg-gray-50 text-indigo-600 font-bold px-10 py-4 rounded-xl shadow-xl transition-transform hover:-translate-y-1">Start Managing Today</a>
        </div>
    </div>
</x-public-layout>
