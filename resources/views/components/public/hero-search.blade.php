@props([
    'availableCities' => collect(),
    'availableUnitTypes' => [],
    'heroImage' => null,
    'stats' => [],
])

@php
    $brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name');
    $bg = $heroImage ?: \App\Http\Controllers\PublicController::LISTING_PLACEHOLDER_IMAGE;
    $vacantCount = (int) ($stats['vacant_listings'] ?? 0);
    $propertyCount = (int) ($stats['properties'] ?? 0);
@endphp

<section class="public-hero" x-data="heroSearchToggle()">
    <div class="public-hero-bg">
        <img src="{{ $bg }}" alt="Premium properties in Kenya" fetchpriority="high" decoding="async">
        <div class="public-hero-overlay"></div>
    </div>

    <div class="relative public-container py-16 sm:py-20 lg:py-24 w-full">
        <div class="max-w-4xl public-animate-in is-visible">
            <p class="text-emerald-300 text-xs sm:text-sm font-bold uppercase tracking-[0.2em] mb-3">Kenya's trusted rental marketplace</p>
            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.05] mb-4 sm:mb-5">
                Find your next home with confidence.
            </h1>
            <p class="text-base sm:text-xl text-slate-200 max-w-2xl mb-6 sm:mb-8 leading-relaxed">
                Browse verified rental properties, apartments, and managed homes across Kenya — backed by professional property operations.
            </p>

            <div class="public-trust-grid mb-6 sm:mb-8 max-w-3xl">
                <div class="public-trust-item">
                    <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Verified listings
                </div>
                <div class="public-trust-item">
                    <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Managed properties
                </div>
                <div class="public-trust-item">
                    <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Fast tenant support
                </div>
            </div>
        </div>

        <div class="max-w-5xl public-animate-in">
            <div class="flex items-center gap-2 mb-3">
                <button type="button" @click="listingType = 'rent'" :class="listingType === 'rent' ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-white/10 text-white border-white/20'" class="rounded-full px-4 py-1.5 text-xs sm:text-sm font-bold border transition-colors">For Rent</button>
                <button type="button" disabled class="rounded-full px-4 py-1.5 text-xs sm:text-sm font-bold border border-white/15 bg-white/5 text-white/50 cursor-not-allowed" title="Coming soon">For Sale</button>
            </div>

            <form method="get" action="{{ route('public.properties') }}" class="public-search-panel">
                <div class="grid grid-cols-2 lg:grid-cols-6 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">
                    <div class="public-search-field col-span-2 lg:col-span-1">
                        <label for="hero-city">Location</label>
                        <select id="hero-city" name="city">
                            <option value="">All locations</option>
                            @foreach ($availableCities as $city)
                                <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="public-search-field col-span-2 lg:col-span-1">
                        <label for="hero-unit-type">Property type</label>
                        <select id="hero-unit-type" name="unit_type">
                            <option value="">Any type</option>
                            @foreach ($availableUnitTypes as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" @selected(request('unit_type') === $typeValue)>{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="public-search-field">
                        <label for="hero-min-rent">Min price</label>
                        <input id="hero-min-rent" type="number" name="min_rent" min="0" step="1000" placeholder="KES" value="{{ request('min_rent') }}">
                    </div>
                    <div class="public-search-field">
                        <label for="hero-max-rent">Max price</label>
                        <input id="hero-max-rent" type="number" name="max_rent" min="0" step="1000" placeholder="KES" value="{{ request('max_rent') }}">
                    </div>
                    <div class="public-search-field">
                        <label for="hero-bedrooms">Bedrooms</label>
                        <select id="hero-bedrooms" name="bedrooms">
                            <option value="any">Any</option>
                            @for ($b = 0; $b <= 5; $b++)
                                <option value="{{ $b }}" @selected((string) request('bedrooms') === (string) $b)>{{ $b === 0 ? 'Studio' : $b.' bed' }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-span-2 lg:col-span-1 flex items-stretch p-1.5">
                        <button type="submit" class="public-btn public-btn-primary w-full !rounded-xl !text-base">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Search
                        </button>
                    </div>
                </div>
                <input type="hidden" name="sort" value="featured">
            </form>

            @if ($vacantCount > 0 || $propertyCount > 0)
                <p class="mt-4 text-sm text-slate-300">
                    <span class="font-bold text-white">{{ number_format(max($vacantCount, 1)) }}+</span> homes available now
                    @if ($propertyCount > 0)
                        across <span class="font-bold text-white">{{ number_format($propertyCount) }}</span> managed properties
                    @endif
                </p>
            @endif
        </div>
    </div>
</section>
