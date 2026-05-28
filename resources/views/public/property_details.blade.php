@php
    $title = $pageTitle ?? $unit->property->name.' — Unit '.$unit->label;
    $addr = trim(collect([$unit->property->address_line, $unit->property->city])->filter()->implode(', '));
    $rentDisplay = 'KES '.number_format((float) $unit->rent_amount, 0);
    $desc = $unit->public_listing_description;
    $unitTypeLabel = $unit->unitTypeLabel();
    $bedroomsLabel = $unit->bedroomsLabel();
    $mapsQuery = $addr !== '' ? $addr : $unit->property->name;
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($mapsQuery);
    $mapEmbedUrl = 'https://maps.google.com/maps?q='.rawurlencode($mapsQuery).'&t=&z=15&ie=UTF8&iwloc=&output=embed';
    $currentPage = url()->current();
    $listingImage = $publicPageImage ?? ($imageUrls[0] ?? $listingPlaceholderImage);
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Properties', 'item' => route('public.properties')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $currentPage],
        ],
    ];
    $residenceSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Apartment',
        'name' => $title,
        'description' => $publicPageDescription ?? null,
        'url' => $currentPage,
        'image' => $listingImage,
        'numberOfRooms' => max(1, (int) $unit->bedrooms),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => (string) ($unit->property->address_line ?? ''),
            'addressLocality' => (string) ($unit->property->city ?? ''),
            'addressCountry' => 'KE',
        ],
        'offers' => $offerSchema ?? null,
    ];
@endphp
<x-public-layout
    :page-title="$title"
    :page-description="$publicPageDescription ?? null"
    :page-image="$listingImage"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($residenceSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <div class="public-container py-4 sm:py-6">
        {{-- Breadcrumb --}}
        <nav class="text-xs text-gray-500 mb-4 flex flex-wrap items-center gap-1.5" aria-label="Breadcrumb">
            <a href="{{ route('public.home') }}" class="hover:text-emerald-600">Home</a>
            <span>/</span>
            <a href="{{ route('public.properties') }}" class="hover:text-emerald-600">Properties</a>
            <span>/</span>
            <span class="text-gray-800 font-semibold truncate">{{ $unit->property->name }}</span>
        </nav>

        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 mb-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="public-badge public-badge-available">Available now</span>
                    @if ($unit->public_listing_published)
                        <span class="public-badge public-badge-verified">Verified listing</span>
                    @endif
                    <span class="text-xs text-gray-500">Updated {{ $unit->updated_at->diffForHumans() }}</span>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 tracking-tight">{{ $unit->property->name }}</h1>
                <p class="text-base text-gray-600 mt-1">Unit {{ $unit->label }} · {{ $unitTypeLabel }}</p>
                @if ($addr !== '')
                    <p class="text-sm text-gray-500 mt-2 flex items-start gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $addr }}
                    </p>
                @endif
            </div>
            <p class="text-2xl sm:text-3xl font-black text-emerald-700 shrink-0">{{ $rentDisplay }}<span class="text-sm font-semibold text-gray-500"> /mo</span></p>
        </div>

        <x-public.property-gallery :images="$imageUrls ?? []" :title="$title" :placeholder="$listingPlaceholderImage" />
    </div>

    <x-public.mobile-action-bar :unit="$unit" :whats-app-digits="$whatsAppDigits ?? ''" :phone-href="$phoneHref ?? ''" />

    <div class="public-container pb-12 sm:pb-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
            <div class="lg:col-span-2 space-y-8 sm:space-y-10 order-2 lg:order-1">
                {{-- Quick stats --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ([
                        ['label' => 'Type', 'value' => $unitTypeLabel],
                        ['label' => 'Bedrooms', 'value' => $bedroomsLabel],
                        ['label' => 'Unit', 'value' => $unit->label],
                        ['label' => 'Status', 'value' => 'Vacant'],
                    ] as $stat)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 sm:p-4 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">{{ $stat['label'] }}</p>
                            <p class="text-sm sm:text-base font-black text-gray-900 mt-1">{{ $stat['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Description --}}
                <section>
                    <h2 class="text-xl font-black text-gray-900 mb-4">About this property</h2>
                    <div class="prose prose-sm sm:prose-base text-gray-600 max-w-none">
                        @if ($desc)
                            <div class="leading-relaxed whitespace-pre-line">{{ $desc }}</div>
                        @else
                            <p>This professionally managed unit is available for immediate viewing. Contact us to schedule a walkthrough and confirm move-in availability. All listings are backed by our property operations team for a smooth rental experience.</p>
                        @endif
                    </div>
                </section>

                {{-- Amenities --}}
                <section>
                    <h2 class="text-xl font-black text-gray-900 mb-4">Features & amenities</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @forelse ($unit->amenities as $am)
                            <div class="flex items-start gap-2 text-sm font-semibold text-gray-700">
                                <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>{{ $am->name }}@if($am->category)<span class="text-gray-400 font-medium"> · {{ $am->category }}</span>@endif</span>
                            </div>
                        @empty
                            @foreach (['Professionally managed', 'Vacant & move-in ready', 'Online applications', 'WhatsApp support', 'Verified agency', 'Fast maintenance response'] as $fallback)
                                <div class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    {{ $fallback }}
                                </div>
                            @endforeach
                        @endforelse
                    </div>
                </section>

                {{-- Map --}}
                <section>
                    <h2 class="text-xl font-black text-gray-900 mb-4">Location</h2>
                    <div class="rounded-2xl overflow-hidden border border-gray-200 mb-3 h-48 sm:h-64">
                        <iframe title="Property location map" src="{{ $mapEmbedUrl }}" class="w-full h-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="public-btn public-btn-secondary !text-sm">
                        Open in Google Maps
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </section>

                {{-- Nearby / building --}}
                @if (($similarUnits ?? collect())->isNotEmpty())
                    <section>
                        <h2 class="text-xl font-black text-gray-900 mb-4">More units in this building</h2>
                        <div class="public-listing-grid">
                            @foreach ($similarUnits as $su)
                                <x-public.property-card :unit="$su" :placeholder-image="$listingPlaceholderImage" />
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <div class="order-1 lg:order-2">
                <x-public.inquiry-panel
                    :unit="$unit"
                    :whats-app-digits="$whatsAppDigits ?? ''"
                    :phone-href="$phoneHref ?? ''"
                    :company-name="$companyName ?? ''"
                />
            </div>
        </div>
    </div>
</x-public-layout>
