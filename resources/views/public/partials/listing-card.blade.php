@php
    $imageHeight = $imageHeight ?? 'h-60';
    $thumb = $unit->primaryPublicImageUrl() ?? $placeholderImage;
    $title = $unit->property->name.' — Unit '.$unit->label;
    $addr = trim(collect([$unit->property->address_line, $unit->property->city])->filter()->implode(', ')) ?: '—';
    $rentLabel = 'KES '.number_format((float) $unit->rent_amount, 0).' / mo';
    $unitTypeLabel = $unit->unitTypeLabel();
    $bedroomsLabel = $unit->bedroomsLabel();
@endphp
<div class="group bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-300 hover:-translate-y-1.5 flex flex-col">
    <div class="relative {{ $imageHeight }} overflow-hidden">
        <img src="{{ $thumb }}" alt="{{ $title }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
        <div class="absolute top-4 left-4 bg-white/95 backdrop-blur px-3 py-1 rounded-full text-xs font-black tracking-wider text-gray-900 shadow-sm uppercase">For Rent</div>
        @if ($unit->public_listing_published)
            <div class="absolute top-4 right-4 bg-indigo-600/95 backdrop-blur px-3 py-1 rounded-full text-[10px] font-black tracking-wider text-white shadow-sm uppercase">Featured</div>
        @endif
        <div class="absolute bottom-4 left-4 bg-gray-900/85 backdrop-blur px-4 py-1.5 rounded-lg text-white font-black shadow-lg">{{ $rentLabel }}</div>
    </div>
    <div class="p-6 flex-1 flex flex-col">
        <h3 class="text-2xl font-black text-gray-900 mb-2 truncate">{{ $title }}</h3>
        <p class="inline-flex items-center w-fit mb-3 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold uppercase tracking-wide">
            {{ $unitTypeLabel }}
        </p>
        <p class="text-gray-500 text-sm mb-4 flex items-center gap-1.5">
            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="truncate">{{ $addr }}</span>
        </p>
        <div class="flex items-center justify-between border-t border-gray-100 pt-5 mt-auto mb-6">
            <div class="flex items-center gap-2 text-gray-700 text-sm font-bold">
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                {{ $bedroomsLabel }}
            </div>
            <div class="flex items-center gap-2 text-gray-700 text-sm font-bold">
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                Unit {{ $unit->label }}
            </div>
        </div>
        <a href="{{ route('public.property_details', $unit->id) }}" class="block w-full text-center bg-gray-50 hover:bg-indigo-600 text-indigo-600 hover:text-white border border-gray-100 font-bold py-3 rounded-xl transition-all shadow-sm">View Details</a>
    </div>
</div>
