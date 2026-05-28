@props([
    'stats' => [],
])

@php
    $brandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name');
@endphp

<section {{ $attributes->merge(['class' => 'py-12 sm:py-16 bg-white border-y border-gray-100']) }}>
    <div class="public-container">
        <div class="text-center mb-8 sm:mb-12 public-animate-in">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600 mb-2">Why choose us</p>
            <h2 class="public-section-title">More than listings — full property operations</h2>
            <p class="public-section-subtitle mx-auto">We combine marketplace discovery with professional management so renters get transparency and landlords get results.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            @foreach ([
                ['title' => 'Verified property badge', 'desc' => 'Listings are reviewed and managed by our team — reducing fake inventory and building renter confidence.', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['title' => 'Managed by professionals', 'desc' => 'Rent collection, tenant communication, and maintenance — handled through one connected platform.', 'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                ['title' => 'Fast maintenance support', 'desc' => 'Tenants report issues online. Our team tracks resolution so properties stay in great condition.', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['title' => 'Live occupancy metrics', 'desc' => 'Real vacancy status synced from operations — what you see online reflects what is actually available.', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ['title' => 'Digital applications', 'desc' => 'Apply online, schedule viewings, and get follow-up via phone or WhatsApp — no endless back-and-forth.', 'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ['title' => 'Landlord onboarding', 'desc' => 'Property owners get transparent reporting, monthly statements, and a dedicated landlord portal.', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ] as $item)
                <div class="rounded-2xl border border-gray-100 bg-gray-50/80 p-5 sm:p-6 hover:shadow-lg hover:border-emerald-100 transition-all public-animate-in">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/></svg>
                    </div>
                    <h3 class="text-base sm:text-lg font-black text-gray-900 mb-2">{{ $item['title'] }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $item['desc'] }}</p>
                </div>
            @endforeach
        </div>

        @if (! empty($stats))
            <div class="public-stats-bar mt-10 sm:mt-14 public-animate-in">
                <div class="text-center">
                    <p class="public-stat-value">{{ number_format((int) ($stats['properties'] ?? 0)) }}</p>
                    <p class="public-stat-label">Properties managed</p>
                </div>
                <div class="text-center">
                    <p class="public-stat-value">{{ number_format((int) ($stats['vacant_listings'] ?? 0)) }}</p>
                    <p class="public-stat-label">Available now</p>
                </div>
                <div class="text-center">
                    <p class="public-stat-value">{{ number_format((int) ($stats['landlords'] ?? 0)) }}</p>
                    <p class="public-stat-label">Landlord partners</p>
                </div>
                <div class="text-center">
                    <p class="public-stat-value">{{ number_format((int) ($stats['tenants'] ?? 0)) }}</p>
                    <p class="public-stat-label">Active tenants</p>
                </div>
            </div>
        @endif
    </div>
</section>
