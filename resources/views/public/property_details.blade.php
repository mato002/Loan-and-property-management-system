@php
    $listing = $listing ?? [];
    $title = $pageTitle ?? $unit->property->name.' — Unit '.$unit->label;
    $addr = $listing['neighborhood']['address'] ?? trim(collect([$unit->property->address_line, $unit->property->city])->filter()->implode(', '));
    $rentDisplay = 'KES '.number_format($listing['rent'] ?? $unit->listedRentAmount(), 0);
    $desc = $listing['description'] ?? $unit->public_listing_description;
    $facts = $listing['facts'] ?? [];
    $mapsUrl = $listing['neighborhood']['maps_url'] ?? ('https://www.google.com/maps/search/?api=1&query='.rawurlencode($addr !== '' ? $addr : $unit->property->name));
    $mapEmbedUrl = $listing['neighborhood']['embed_url'] ?? ('https://maps.google.com/maps?q='.rawurlencode($addr !== '' ? $addr : $unit->property->name).'&t=&z=15&ie=UTF8&iwloc=&output=embed');
    $currentPage = $listing['listingUrl'] ?? url()->current();
    $listingImage = $publicPageImage
        ?? (is_array($imageUrls[0] ?? null) ? ($imageUrls[0]['url'] ?? null) : ($imageUrls[0] ?? null))
        ?? $listingPlaceholderImage;
    $whatsAppDigits = $listing['whatsappDigits'] ?? ($whatsAppDigits ?? '');
    $whatsappUrl = $listing['whatsappUrl'] ?? '';
    $shareText = $listing['shareText'] ?? $title;
    $availableLabel = $listing['availableLabel'] ?? 'Available now';
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

    <div class="public-container py-4 sm:py-6" x-data="listingShareSave(@js($currentPage), @js($shareText))">
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
                    <span class="public-badge public-badge-available">{{ $availableLabel }}</span>
                    @if ($unit->public_listing_published)
                        <span class="public-badge public-badge-verified">Verified listing</span>
                    @endif
                    <span class="text-xs text-gray-500">Updated {{ $unit->updated_at->diffForHumans() }}</span>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 tracking-tight">{{ $unit->property->name }}</h1>
                <p class="text-base text-gray-600 mt-1">Unit {{ $unit->label }} · {{ $unit->unitTypeLabel() }} · {{ $unit->bedroomsLabel() }}</p>
                @if ($addr !== '')
                    <p class="text-sm text-gray-500 mt-2 flex items-start gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $addr }}
                    </p>
                @endif
            </div>
            <div class="flex flex-col items-start lg:items-end gap-2 shrink-0">
                <p class="text-2xl sm:text-3xl font-black text-emerald-700">{{ $rentDisplay }}<span class="text-sm font-semibold text-gray-500"> /mo</span></p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="$store.publicFavorites.toggle('{{ $unit->id }}')" class="public-btn public-btn-secondary !min-h-[2.25rem] !text-xs !rounded-lg">
                        <span x-text="$store.publicFavorites.isSaved('{{ $unit->id }}') ? 'Saved' : 'Save'"></span>
                    </button>
                    <button type="button" @click="shareListing()" class="public-btn public-btn-secondary !min-h-[2.25rem] !text-xs !rounded-lg">Share</button>
                    @if ($whatsappUrl)
                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="public-btn !min-h-[2.25rem] !text-xs !rounded-lg bg-[#25D366] text-white">WhatsApp</a>
                    @endif
                </div>
                <p x-show="copied" x-cloak class="text-xs font-semibold text-emerald-700">Link copied</p>
            </div>
        </div>

        <x-public.property-gallery
            :images="$imageUrls ?? []"
            :title="$title"
            :placeholder="$listingPlaceholderImage"
            :has-uploaded-media="$listing['hasUploadedMedia'] ?? false"
        />
    </div>

    <x-public.mobile-action-bar
        :unit="$unit"
        :whats-app-digits="$whatsAppDigits"
        :phone-href="$phoneHref ?? ''"
        :whatsapp-url="$whatsappUrl"
    />

    <div class="public-container pb-12 sm:pb-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
            <div class="lg:col-span-2 space-y-8 sm:space-y-10 order-2 lg:order-1">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($facts as $stat)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 sm:p-4 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">{{ $stat['label'] }}</p>
                            <p class="text-sm sm:text-base font-black text-gray-900 mt-1">{{ $stat['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if (! empty($listing['moveInLines']))
                    <section>
                        <h2 class="text-xl font-black text-gray-900 mb-4">Move-in cost</h2>
                        <div class="rounded-2xl border border-gray-100 overflow-hidden">
                            <table class="w-full text-sm">
                                <tbody>
                                    @foreach ($listing['moveInLines'] as $line)
                                        <tr class="border-b border-gray-50">
                                            <td class="px-4 py-3 text-gray-700">
                                                {{ $line['label'] }}
                                                @if (! empty($line['hint']))
                                                    <span class="block text-xs text-gray-400">{{ $line['hint'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right font-bold text-gray-900">KES {{ number_format($line['amount'], 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-emerald-50">
                                        <td class="px-4 py-3 font-black text-gray-900">Estimated to move in</td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-800">KES {{ number_format($listing['moveInTotal'] ?? 0, 0) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @if (! empty($listing['monthlyExtras']))
                            <p class="mt-3 text-xs font-bold uppercase tracking-wide text-gray-500">Ongoing monthly charges</p>
                            <ul class="mt-2 space-y-1 text-sm text-gray-700">
                                @foreach ($listing['monthlyExtras'] as $extra)
                                    <li class="flex justify-between gap-3">
                                        <span>{{ $extra['label'] }}</span>
                                        <span class="font-semibold">KES {{ number_format($extra['amount'], 0) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                <section>
                    <h2 class="text-xl font-black text-gray-900 mb-4">About this unit</h2>
                    <div class="prose prose-sm sm:prose-base text-gray-600 max-w-none">
                        <div class="leading-relaxed whitespace-pre-line">{{ $desc }}</div>
                    </div>
                </section>

                @if (! empty($listing['videos']))
                    <section>
                        <h2 class="text-xl font-black text-gray-900 mb-4">Walkthrough</h2>
                        <div class="space-y-4">
                            @foreach ($listing['videos'] as $video)
                                <video src="{{ $video['url'] }}" class="w-full rounded-2xl bg-black max-h-[28rem]" controls playsinline preload="metadata"></video>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if (! empty($listing['floorPlans']))
                    <section>
                        <h2 class="text-xl font-black text-gray-900 mb-4">Floor plan</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($listing['floorPlans'] as $plan)
                                <img src="{{ $plan['url'] }}" alt="Floor plan" class="w-full rounded-2xl border border-gray-100 object-contain bg-slate-50">
                            @endforeach
                        </div>
                    </section>
                @endif

                <section>
                    <h2 class="text-xl font-black text-gray-900 mb-4">Features</h2>
                    @if (! empty($listing['amenities']))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($listing['amenities'] as $amenity)
                                <div class="flex items-start gap-2 text-sm font-semibold text-gray-700">
                                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>{{ $amenity }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Ask us on WhatsApp for water, parking, and what is included with rent.</p>
                    @endif
                </section>

                <section>
                    <h2 class="text-xl font-black text-gray-900 mb-4">Neighborhood</h2>
                    @if (! empty($listing['neighborhood']['city']))
                        <p class="text-sm text-gray-600 mb-3">This unit is in {{ $listing['neighborhood']['city'] }}@if ($addr) — {{ $addr }}@endif. Ask us about commute, schools, and nearby shops when you book a viewing.</p>
                    @endif
                    <div class="rounded-2xl overflow-hidden border border-gray-200 mb-3 h-48 sm:h-64">
                        <iframe title="Property location map" src="{{ $mapEmbedUrl }}" class="w-full h-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="public-btn public-btn-secondary !text-sm">
                        Open pin in Google Maps
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </section>

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
                    :whats-app-digits="$whatsAppDigits"
                    :phone-href="$phoneHref ?? ''"
                    :company-name="$companyName ?? ''"
                    :whatsapp-url="$whatsappUrl"
                    :move-in-total="$listing['moveInTotal'] ?? 0"
                    :available-label="$availableLabel"
                    :trust-lines="$listing['trust'] ?? []"
                />
            </div>
        </div>
    </div>
</x-public-layout>
