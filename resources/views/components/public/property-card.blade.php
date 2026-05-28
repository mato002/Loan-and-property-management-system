@props([
    'unit',
    'placeholderImage' => null,
    'featured' => false,
])

@php
    $placeholder = $placeholderImage ?: \App\Http\Controllers\PublicController::LISTING_PLACEHOLDER_IMAGE;
    $imageUrls = $unit->relationLoaded('publicImages')
        ? $unit->publicImages->map(fn ($img) => $img->publicUrl())->filter()->values()->all()
        : [];
    if (empty($imageUrls)) {
        $imageUrls = [$placeholder];
    }
    $propertyName = (string) ($unit->property?->name ?? 'Property');
    $title = $propertyName.' — Unit '.$unit->label;
    $addr = trim(collect([$unit->property?->address_line, $unit->property?->city])->filter()->implode(', '));
    $rentLabel = 'KES '.number_format((float) $unit->rent_amount, 0);
    $unitTypeLabel = $unit->unitTypeLabel();
    $bedroomsLabel = $unit->bedroomsLabel();
    $isVerified = (bool) $unit->public_listing_published;
    $detailUrl = route('public.property_details', $unit->id);
    $hasRealPhoto = $unit->relationLoaded('publicImages') && $unit->publicImages->isNotEmpty();
@endphp

<article
    {{ $attributes->merge(['class' => 'public-property-card group public-animate-in']) }}
    x-data="propertyCardCarousel(@js($imageUrls))"
>
    <a href="{{ $detailUrl }}" class="public-property-card-media block relative" aria-label="{{ $title }}">
        <img :src="images[index]" alt="{{ $title }}" loading="lazy" decoding="async" @class(['opacity-90' => ! $hasRealPhoto])>

        <div class="absolute inset-x-0 top-0 flex items-start justify-between p-2.5 sm:p-3 pointer-events-none">
            <span class="public-badge public-badge-available shadow-sm">Available</span>
            <div class="flex items-center gap-1.5 pointer-events-auto">
                @if ($isVerified || $featured)
                    <span class="public-badge public-badge-verified shadow-sm">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zM9 12l2 2 4-4" clip-rule="evenodd"/></svg>
                        Verified
                    </span>
                @endif
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/95 shadow hover:text-rose-500 transition-colors"
                    :class="$store.publicFavorites.isSaved('{{ $unit->id }}') ? 'text-rose-500' : 'text-gray-500'"
                    @click.prevent.stop="$store.publicFavorites.toggle('{{ $unit->id }}')"
                    aria-label="Save listing"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </button>
            </div>
        </div>

        <template x-if="hasMultiple">
            <div class="absolute inset-x-0 bottom-12 flex items-center justify-between px-2 pointer-events-none">
                <button type="button" @click="prev($event)" class="pointer-events-auto inline-flex h-7 w-7 items-center justify-center rounded-full bg-black/45 text-white hover:bg-black/65" aria-label="Previous photo">&lsaquo;</button>
                <button type="button" @click="next($event)" class="pointer-events-auto inline-flex h-7 w-7 items-center justify-center rounded-full bg-black/45 text-white hover:bg-black/65" aria-label="Next photo">&rsaquo;</button>
            </div>
        </template>

        <div class="absolute bottom-2.5 left-2.5 right-2.5 flex items-end justify-between gap-2 pointer-events-none">
            <div class="rounded-lg bg-gray-900/90 px-2.5 py-1 text-white shadow-lg backdrop-blur-sm">
                <span class="text-sm sm:text-base font-black">{{ $rentLabel }}</span>
                <span class="text-[10px] sm:text-xs font-semibold text-gray-300"> / mo</span>
            </div>
            <template x-if="hasMultiple">
                <span class="rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur-sm" x-text="`${index + 1}/${images.length}`"></span>
            </template>
        </div>

        @unless ($hasRealPhoto)
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/50 via-transparent to-transparent pointer-events-none"></div>
        @endunless
    </a>

    <div class="flex flex-1 flex-col p-3 sm:p-4 min-w-0">
        <h3 class="text-sm sm:text-base font-black text-gray-900 line-clamp-2 leading-snug mb-1">
            <a href="{{ $detailUrl }}" class="hover:text-emerald-700 transition-colors">{{ $propertyName }}</a>
        </h3>
        <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wide text-amber-700 mb-1.5">{{ $unitTypeLabel }} · Unit {{ $unit->label }}</p>
        @if ($addr !== '')
            <p class="text-xs text-gray-500 flex items-center gap-1 mb-3 min-w-0">
                <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="truncate">{{ $addr }}</span>
            </p>
        @endif
        <div class="mt-auto flex items-center justify-between gap-2 pt-3 border-t border-gray-100">
            <span class="inline-flex items-center gap-1 text-xs font-bold text-gray-700">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                {{ $bedroomsLabel }}
            </span>
            <a href="{{ $detailUrl }}" class="public-btn public-btn-primary !min-h-[2.25rem] !px-3 !py-1.5 !text-xs !shadow-md">View</a>
        </div>
    </div>
</article>
