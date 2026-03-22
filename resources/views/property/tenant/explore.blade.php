<x-property-layout>
    <x-slot name="header">Explore</x-slot>

    <x-property.page
        title="Explore"
        subtitle="Featured vacant listings from your agency. Units in buildings where you already lease are highlighted."
    >
        @if ($units->isEmpty())
            <div class="rounded-2xl border border-teal-200/70 dark:border-teal-900/50 bg-teal-50/40 dark:bg-teal-950/15 p-6 text-sm text-slate-700 dark:text-slate-300">
                <p>No published listings right now.</p>
                <a href="{{ route('public.properties') }}" class="inline-block mt-3 text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">Browse all public properties →</a>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 w-full min-w-0">
                @foreach ($units as $unit)
                    @php
                        $img = $unit->primaryPublicImageUrl();
                        $sameBuilding = $leasePropertyIds->contains($unit->property_id);
                    @endphp
                    <article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 overflow-hidden shadow-sm flex flex-col min-w-0">
                        <div class="aspect-[16/10] bg-slate-100 dark:bg-slate-900 relative">
                            @if ($img)
                                <img src="{{ $img }}" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy" />
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-xs text-slate-400">No photo</div>
                            @endif
                            @if ($sameBuilding)
                                <span class="absolute top-2 left-2 rounded-lg bg-teal-600/90 text-white text-[10px] font-semibold px-2 py-0.5">Your building</span>
                            @endif
                        </div>
                        <div class="p-4 flex-1 flex flex-col min-w-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $unit->property->name }} · {{ $unit->label }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $unit->property->city ?? $unit->property->address_line ?? '' }}</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-white tabular-nums">
                                {{ \App\Services\Property\PropertyMoney::kes((float) $unit->rent_amount) }}
                                <span class="text-xs font-normal text-slate-500">/ mo</span>
                            </p>
                            @if ($unit->public_listing_description)
                                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300 line-clamp-3">{{ $unit->public_listing_description }}</p>
                            @endif
                            <a href="{{ route('public.property_details', ['id' => $unit->id]) }}" class="mt-auto pt-4 text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">View listing →</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <a href="{{ route('public.properties') }}" class="inline-block mt-6 text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">Browse full directory →</a>
        @endif
    </x-property.page>
</x-property-layout>
