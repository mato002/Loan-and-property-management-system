@props([
    'images' => [],
    'title' => 'Property gallery',
    'placeholder' => null,
])

@php
    $placeholder = $placeholder ?: \App\Http\Controllers\PublicController::LISTING_PLACEHOLDER_IMAGE;
    $allImages = collect($images)->filter()->values()->all();
    if (empty($allImages)) {
        $allImages = [$placeholder];
    }
@endphp

<div {{ $attributes->merge(['class' => 'mb-6 sm:mb-8']) }} x-data="propertyGallery(@js($allImages))">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 sm:gap-3 md:h-[28rem] lg:h-[32rem]">
        <button type="button" @click="openAt(0)" class="md:col-span-2 relative rounded-2xl overflow-hidden group h-56 md:h-full focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <img src="{{ $allImages[0] }}" alt="{{ $title }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="eager">
            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
        </button>
        <div class="hidden md:grid grid-rows-2 gap-2 sm:gap-3 md:col-span-1 h-full">
            @for ($i = 1; $i <= 2; $i++)
                <button type="button" @click="openAt({{ $i }})" class="relative rounded-2xl overflow-hidden group h-full focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @if (isset($allImages[$i]))
                        <img src="{{ $allImages[$i] }}" alt="{{ $title }} photo {{ $i + 1 }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy">
                    @else
                        <div class="public-image-placeholder !rounded-2xl"><span>Gallery</span></div>
                    @endif
                </button>
            @endfor
        </div>
        <div class="hidden md:grid grid-rows-2 gap-2 sm:gap-3 md:col-span-1 h-full">
            @for ($i = 3; $i <= 4; $i++)
                <button type="button" @click="openAt({{ min($i, count($allImages) - 1) }})" class="relative rounded-2xl overflow-hidden group h-full focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @if (isset($allImages[$i]))
                        <img src="{{ $allImages[$i] }}" alt="{{ $title }} photo {{ $i + 1 }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy">
                        @if ($i === 4 && count($allImages) > 5)
                            <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                <span class="text-white font-black text-lg">+{{ count($allImages) - 5 }} photos</span>
                            </div>
                        @endif
                    @else
                        <div class="public-image-placeholder !rounded-2xl"><span>Gallery</span></div>
                    @endif
                </button>
            @endfor
        </div>
    </div>

    {{-- Mobile swipe strip --}}
    <div class="md:hidden flex gap-2 overflow-x-auto snap-x snap-mandatory pb-1 -mx-1 px-1">
        @foreach ($allImages as $idx => $img)
            <button type="button" @click="openAt({{ $idx }})" class="snap-start shrink-0 w-[85%] h-48 rounded-xl overflow-hidden relative">
                <img src="{{ $img }}" alt="{{ $title }}" class="w-full h-full object-cover" loading="lazy">
            </button>
        @endforeach
    </div>

    <template x-teleport="body">
        <div x-show="lightboxOpen" x-cloak class="public-lightbox" @keydown.escape.window="close()" @keydown.arrow-right.window="next()" @keydown.arrow-left.window="prev()">
            <button type="button" @click="close()" class="absolute top-4 right-4 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Close gallery">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <button type="button" @click="prev()" class="absolute left-3 sm:left-6 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Previous">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <img :src="images[lightboxIndex]" :alt="@js($title)" @click.stop>
            <button type="button" @click="next()" class="absolute right-3 sm:right-6 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Next">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <p class="absolute bottom-4 left-1/2 -translate-x-1/2 text-white text-sm font-semibold" x-text="`${lightboxIndex + 1} / ${images.length}`"></p>
        </div>
    </template>
</div>
