<x-public-layout
    :page-title="$publicPageTitle ?? null"
    :page-description="$publicPageDescription ?? null"
>
    <x-public.hero-search
        :available-cities="$availableCities"
        :available-unit-types="$availableUnitTypes"
        :hero-image="$heroImage ?? $listingPlaceholderImage"
        :stats="$publicStats ?? []"
    />

    {{-- Featured listings --}}
    <section class="py-10 sm:py-16 bg-white">
        <div class="public-container">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6 sm:mb-10 public-animate-in">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600 mb-2">Handpicked for you</p>
                    <h2 class="public-section-title">Featured properties</h2>
                    <p class="public-section-subtitle">Verified vacant units with professional management — updated live from our operations platform.</p>
                </div>
                <a href="{{ route('public.properties', ['sort' => 'featured']) }}" class="public-btn public-btn-secondary shrink-0">
                    View all listings
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            @if ($featuredUnits->isNotEmpty())
                <div class="public-listing-grid">
                    @foreach ($featuredUnits as $unit)
                        <x-public.property-card :unit="$unit" :placeholder-image="$listingPlaceholderImage" :featured="true" />
                    @endforeach
                </div>
            @else
                <x-public.empty-state
                    title="New listings coming soon"
                    description="We're preparing verified properties for you. Check back shortly or contact us to get notified first."
                    action-label="Contact our team"
                    :action-url="route('public.contact')"
                />
            @endif

            {{-- Quick discovery chips --}}
            @if (! empty($availableUnitTypes))
                <div class="mt-8 sm:mt-10 public-animate-in">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-3">Browse by type</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($availableUnitTypes as $typeValue => $typeLabel)
                            <a href="{{ route('public.properties', ['unit_type' => $typeValue, 'sort' => 'featured']) }}" class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-4 py-2 text-xs sm:text-sm font-bold text-gray-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 transition-colors">
                                {{ $typeLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <x-public.why-choose-us :stats="$publicStats ?? []" />
    <x-public.testimonials />

    {{-- Landlord CTA --}}
    <section class="py-12 sm:py-16 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900">
        <div class="public-container">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center public-animate-in">
                <div>
                    <p class="text-emerald-300 text-xs font-bold uppercase tracking-wider mb-2">For property owners</p>
                    <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight mb-4">List with a team that operates, not just advertises</h2>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed">Full rent collection, tenant screening, maintenance coordination, and transparent monthly reporting — all in one platform.</p>
                </div>
                <div class="flex flex-col sm:flex-row lg:flex-col xl:flex-row gap-3 lg:items-start">
                    <a href="{{ route('public.about') }}" class="public-btn public-btn-primary !rounded-xl">Landlord onboarding</a>
                    <a href="{{ route('public.contact', ['intent' => 'landlord']) }}" class="public-btn public-btn-ghost !rounded-xl">Request callback</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Final tenant CTA --}}
    <section class="py-12 sm:py-16 bg-emerald-600">
        <div class="public-container text-center public-animate-in">
            <h2 class="text-2xl sm:text-4xl font-black text-white mb-3">Ready to find your next home?</h2>
            <p class="text-emerald-100 mb-6 max-w-xl mx-auto">Browse verified listings, save favorites, and apply online in minutes.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('public.properties') }}" class="public-btn bg-white text-emerald-700 hover:bg-emerald-50 !shadow-xl">Browse available homes</a>
                <a href="{{ route('public.apply') }}" class="public-btn public-btn-ghost">Start application</a>
            </div>
        </div>
    </section>
</x-public-layout>
