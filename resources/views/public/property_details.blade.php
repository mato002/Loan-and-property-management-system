@php
    $title = $pageTitle ?? $unit->property->name.' — Unit '.$unit->label;
    $addr = trim(collect([$unit->property->address_line, $unit->property->city])->filter()->implode(', ')) ?: '—';
    $rentDisplay = 'KES '.number_format((float) $unit->rent_amount, 0);
    $desc = $unit->public_listing_description;
    $unitTypeLabel = $unit->unitTypeLabel();
    $bedroomsLabel = $unit->bedroomsLabel();
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($addr !== '—' ? $addr : $unit->property->name);
@endphp
<x-public-layout :page-title="$title">
    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="bg-indigo-100 text-indigo-800 text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider">For Rent</span>
                    <span class="text-gray-500 font-medium text-sm">
                        <svg class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Updated {{ $unit->updated_at->diffForHumans() }}
                    </span>
                </div>
                <h1 class="text-4xl sm:text-5xl font-black text-gray-900 tracking-tight">{{ $title }}</h1>
                <p class="text-lg text-gray-500 mt-2 flex items-center gap-1.5 font-medium">
                    <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $addr }}
                </p>
            </div>
            <div class="text-left md:text-right">
                <p class="text-4xl font-black text-indigo-600">{{ $rentDisplay }} <span class="text-xl text-gray-500 font-medium tracking-normal">/ mo</span></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 h-[400px] md:h-[550px]">
            <div class="col-span-1 md:col-span-2 h-full rounded-3xl overflow-hidden cursor-pointer relative group">
                @if (! empty($gallerySlots[0]))
                    <img src="{{ $gallerySlots[0] }}" alt="Main" class="absolute inset-0 block w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700">
                    <div class="absolute inset-0 bg-gray-900/10 group-hover:bg-gray-900/0 transition-colors"></div>
                @else
                    <div class="w-full h-full bg-slate-100 text-slate-500 flex items-center justify-center text-sm font-semibold">
                        No photo uploaded yet
                    </div>
                @endif
            </div>
            <div class="hidden md:grid grid-rows-2 gap-4 col-span-1 h-full">
                <div class="rounded-3xl overflow-hidden cursor-pointer relative group h-full">
                    @if (! empty($gallerySlots[1]))
                        <img src="{{ $gallerySlots[1] }}" alt="Photo" class="absolute inset-0 block w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700">
                    @else
                        <div class="w-full h-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-semibold">
                            No photo
                        </div>
                    @endif
                </div>
                <div class="rounded-3xl overflow-hidden cursor-pointer relative group h-full">
                    @if (! empty($gallerySlots[2]))
                        <img src="{{ $gallerySlots[2] }}" alt="Photo" class="absolute inset-0 block w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700">
                    @else
                        <div class="w-full h-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-semibold">
                            No photo
                        </div>
                    @endif
                </div>
            </div>
            <div class="hidden md:grid grid-rows-2 gap-4 col-span-1 h-full">
                <div class="rounded-3xl overflow-hidden cursor-pointer relative group h-full">
                    @if (! empty($gallerySlots[3]))
                        <img src="{{ $gallerySlots[3] }}" alt="Photo" class="absolute inset-0 block w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700">
                    @else
                        <div class="w-full h-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-semibold">
                            No photo
                        </div>
                    @endif
                </div>
                <div class="rounded-3xl overflow-hidden cursor-pointer relative group h-full">
                    @if ($extraPhotoCount > 0)
                        <div class="absolute inset-0 bg-gray-900/60 z-10 flex items-center justify-center">
                            <span class="text-white font-black text-xl tracking-wider">+{{ $extraPhotoCount }} {{ Str::plural('photo', $extraPhotoCount) }}</span>
                        </div>
                    @endif
                    @if (! empty($gallerySlots[4]))
                        <img src="{{ $gallerySlots[4] }}" alt="Photo" class="absolute inset-0 block w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700">
                    @else
                        <div class="w-full h-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-semibold">
                            No photo
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-12">
        <div class="flex flex-col lg:flex-row gap-12">
            <div class="w-full lg:w-2/3">
                <div class="bg-gray-50 border border-gray-100 rounded-3xl p-6 md:p-8 flex flex-wrap justify-between items-center mb-10 shadow-sm gap-y-6">
                    <div class="text-center px-2 md:px-4">
                        <p class="text-gray-500 font-bold mb-1">Type</p>
                        <p class="text-2xl font-black text-gray-900">{{ $unitTypeLabel }}</p>
                    </div>
                    <div class="hidden sm:block w-px h-12 bg-gray-200"></div>
                    <div class="text-center px-2 md:px-4">
                        <p class="text-gray-500 font-bold mb-1">Room setup</p>
                        <p class="text-2xl font-black text-gray-900">{{ $bedroomsLabel }}</p>
                    </div>
                    <div class="hidden sm:block w-px h-12 bg-gray-200"></div>
                    <div class="text-center px-2 md:px-4">
                        <p class="text-gray-500 font-bold mb-1">Unit</p>
                        <p class="text-2xl font-black text-gray-900">{{ $unit->label }}</p>
                    </div>
                    <div class="hidden sm:block w-px h-12 bg-gray-200"></div>
                    <div class="text-center px-2 md:px-4">
                        <p class="text-gray-500 font-bold mb-1">Monthly rent</p>
                        <p class="text-2xl font-black text-gray-900">{{ $rentDisplay }}</p>
                    </div>
                    <div class="hidden sm:block w-px h-12 bg-gray-200"></div>
                    <div class="text-center px-2 md:px-4">
                        <p class="text-gray-500 font-bold mb-1">Property</p>
                        <p class="text-xl font-black text-gray-900 mt-2 truncate max-w-[140px]">{{ $unit->property->name }}</p>
                    </div>
                </div>

                <div class="mb-12">
                    <h2 class="text-2xl font-black text-gray-900 mb-6">Description</h2>
                    <div class="prose prose-lg text-gray-600 max-w-none">
                        @if ($desc)
                            <div class="leading-relaxed whitespace-pre-line">{{ $desc }}</div>
                        @else
                            <p class="mb-4 leading-relaxed">Contact us for a full walkthrough and availability. This unit is professionally managed and move-in ready.</p>
                        @endif
                    </div>
                </div>

                <div class="mb-12">
                    <h2 class="text-2xl font-black text-gray-900 mb-8">Features &amp; Amenities</h2>
                    @if ($unit->amenities->isNotEmpty())
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-y-6 gap-x-8 text-gray-700 font-bold text-sm">
                            @foreach ($unit->amenities as $am)
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>{{ $am->name }}@if ($am->category)<span class="text-gray-400 font-medium"> · {{ $am->category }}</span>@endif</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-y-6 gap-x-8 text-gray-700 font-bold text-sm">
                            <div class="flex items-center gap-3"><svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Professionally managed</div>
                            <div class="flex items-center gap-3"><svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Vacant &amp; available</div>
                            <div class="flex items-center gap-3"><svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Online applications</div>
                        </div>
                    @endif
                </div>

                <div class="mb-12">
                    <h2 class="text-2xl font-black text-gray-900 mb-6">Location Map</h2>
                    <div class="bg-gray-50 w-full min-h-[16rem] rounded-3xl border border-gray-200 shadow-inner flex flex-col items-center justify-center px-8 py-10 text-center">
                        <svg class="w-10 h-10 text-indigo-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <p class="text-gray-700 font-bold mb-1">{{ $addr }}</p>
                        <p class="text-sm text-gray-500 mb-6 max-w-md">Open in Google Maps for directions and street view.</p>
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black px-8 py-3 rounded-2xl shadow-lg shadow-indigo-600/25 transition-colors">
                            Open in Maps
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                </div>

                @if (($similarUnits ?? collect())->isNotEmpty())
                    <div class="mb-12">
                        <h2 class="text-2xl font-black text-gray-900 mb-8">More in this building</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            @foreach ($similarUnits as $su)
                                @include('public.partials.listing-card', ['unit' => $su, 'placeholderImage' => $listingPlaceholderImage])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="w-full lg:w-1/3">
                <div class="bg-white border border-gray-100 p-8 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] sticky top-28">
                    <div class="flex items-center gap-5 mb-8 pb-8 border-b border-gray-100">
                        <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-black text-xl border border-indigo-100">PM</div>
                        <div>
                            <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Property Management</p>
                            <p class="text-xl font-black text-gray-900">{{ config('app.name') }}</p>
                            <p class="text-sm font-bold text-indigo-600 mt-1">Ask about this unit</p>
                        </div>
                    </div>

                    <div class="space-y-4 mb-8">
                        <a href="{{ route('public.contact') }}" class="flex items-center justify-center gap-3 w-full bg-emerald-500 hover:bg-emerald-600 text-white font-black py-4 rounded-2xl transition-colors shadow-lg shadow-emerald-500/25">
                            Book Site Visit
                        </a>
                        <a href="{{ route('public.contact') }}" class="flex items-center justify-center gap-3 w-full bg-gray-50 hover:bg-gray-100 text-gray-900 font-black py-4 rounded-2xl transition-colors border border-gray-200">
                            <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Talk to Agent
                        </a>
                        <a href="https://wa.me/18005550199" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-3 w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-black py-4 rounded-2xl transition-colors border border-emerald-200">
                            WhatsApp Agent
                        </a>
                    </div>

                    <a href="{{ route('public.apply', ['property_unit' => $unit->id]) }}" class="flex items-center justify-center gap-2 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black text-lg py-5 rounded-2xl transition-all hover:-translate-y-1 shadow-xl shadow-indigo-600/30">
                        Apply Online Now
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
