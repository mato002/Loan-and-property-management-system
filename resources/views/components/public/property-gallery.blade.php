@props([
    'images' => [],
    'title' => 'Property gallery',
    'placeholder' => null,
])

@php
    $placeholder = $placeholder ?: \App\Http\Controllers\PublicController::LISTING_PLACEHOLDER_IMAGE;
    $items = collect($images)
        ->map(function ($item) {
            if (is_string($item) && $item !== '') {
                return ['url' => $item, 'type' => 'image'];
            }
            if (is_array($item) && ! empty($item['url'])) {
                return [
                    'url' => (string) $item['url'],
                    'type' => (($item['type'] ?? 'image') === 'video') ? 'video' : 'image',
                ];
            }

            return null;
        })
        ->filter()
        ->values()
        ->all();

    if ($items === []) {
        $items = [['url' => $placeholder, 'type' => 'image']];
    }
@endphp

<div {{ $attributes->merge(['class' => 'mb-6 sm:mb-8']) }} x-data="propertyGallery(@js($items))">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 sm:gap-3 md:h-[28rem] lg:h-[32rem]">
        <button type="button" @click="openAt(0)" class="md:col-span-2 relative rounded-2xl overflow-hidden group h-56 md:h-full focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-100">
            <template x-if="items[0]?.type === 'video'">
                <video :src="items[0].url" class="absolute inset-0 w-full h-full object-cover" muted playsinline preload="metadata"></video>
            </template>
            <template x-if="items[0]?.type !== 'video'">
                <img :src="items[0]?.url" alt="{{ $title }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="eager">
            </template>
            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <template x-if="items[0]?.type === 'video'">
                <span class="absolute bottom-3 left-3 rounded-md bg-black/70 px-2 py-1 text-xs font-semibold text-white">Video</span>
            </template>
        </button>
        <div class="hidden md:grid grid-rows-2 gap-2 sm:gap-3 md:col-span-1 h-full">
            @for ($i = 1; $i <= 2; $i++)
                <button type="button" @click="openAt({{ $i }})" class="relative rounded-2xl overflow-hidden group h-full focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-100">
                    @if (isset($items[$i]))
                        @if (($items[$i]['type'] ?? 'image') === 'video')
                            <video src="{{ $items[$i]['url'] }}" class="absolute inset-0 w-full h-full object-cover" muted playsinline preload="metadata"></video>
                            <span class="absolute bottom-2 left-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-semibold text-white">Video</span>
                        @else
                            <img src="{{ $items[$i]['url'] }}" alt="{{ $title }} photo {{ $i + 1 }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy">
                        @endif
                    @else
                        <div class="public-image-placeholder !rounded-2xl"><span>Gallery</span></div>
                    @endif
                </button>
            @endfor
        </div>
        <div class="hidden md:grid grid-rows-2 gap-2 sm:gap-3 md:col-span-1 h-full">
            @for ($i = 3; $i <= 4; $i++)
                <button type="button" @click="openAt({{ min($i, count($items) - 1) }})" class="relative rounded-2xl overflow-hidden group h-full focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-slate-100">
                    @if (isset($items[$i]))
                        @if (($items[$i]['type'] ?? 'image') === 'video')
                            <video src="{{ $items[$i]['url'] }}" class="absolute inset-0 w-full h-full object-cover" muted playsinline preload="metadata"></video>
                        @else
                            <img src="{{ $items[$i]['url'] }}" alt="{{ $title }} photo {{ $i + 1 }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy">
                        @endif
                        @if ($i === 4 && count($items) > 5)
                            <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                <span class="text-white font-black text-lg">+{{ count($items) - 5 }} more</span>
                            </div>
                        @endif
                    @else
                        <div class="public-image-placeholder !rounded-2xl"><span>Gallery</span></div>
                    @endif
                </button>
            @endfor
        </div>
    </div>

    <div class="md:hidden flex gap-2 overflow-x-auto snap-x snap-mandatory pb-1 -mx-1 px-1">
        @foreach ($items as $idx => $item)
            <button type="button" @click="openAt({{ $idx }})" class="snap-start shrink-0 w-[85%] h-48 rounded-xl overflow-hidden relative bg-slate-100">
                @if (($item['type'] ?? 'image') === 'video')
                    <video src="{{ $item['url'] }}" class="w-full h-full object-cover" muted playsinline preload="metadata"></video>
                @else
                    <img src="{{ $item['url'] }}" alt="{{ $title }}" class="w-full h-full object-cover" loading="lazy">
                @endif
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
            <template x-if="current?.type === 'video'">
                <video :src="current.url" class="max-h-[85vh] max-w-[92vw] rounded-lg" controls playsinline autoplay @click.stop></video>
            </template>
            <template x-if="current?.type !== 'video'">
                <img :src="current?.url" :alt="@js($title)" @click.stop>
            </template>
            <button type="button" @click="next()" class="absolute right-3 sm:right-6 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Next">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <p class="absolute bottom-4 left-1/2 -translate-x-1/2 text-white text-sm font-semibold" x-text="`${lightboxIndex + 1} / ${items.length}`"></p>
        </div>
    </template>
</div>
