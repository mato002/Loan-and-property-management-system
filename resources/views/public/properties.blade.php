@php
    $currentSort = request('sort') ?: 'updated';
    $activeFilterCount = count($activeFilters ?? []);
@endphp
<x-public-layout
    :page-title="$publicPageTitle ?? 'Discover Properties'"
    :page-description="$publicPageDescription ?? null"
>
    <section class="bg-gradient-to-b from-slate-50 to-white border-b border-gray-100">
        <div class="public-container py-8 sm:py-12">
            <div class="max-w-3xl public-animate-in">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600 mb-2">Property discovery</p>
                <h1 class="public-section-title text-3xl sm:text-4xl">Find your perfect rental</h1>
                <p class="public-section-subtitle">Search verified vacant units across Kenya. Filter by location, price, and property type — results update instantly.</p>
            </div>

            <form method="get" action="{{ route('public.properties') }}" class="mt-6 flex flex-col sm:flex-row gap-2 public-animate-in">
                <div class="flex-1 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by area, building, or keyword..." class="w-full min-h-[2.75rem] pl-10 pr-4 rounded-xl border border-gray-200 bg-white shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500 outline-none">
                </div>
                <button type="submit" class="public-btn public-btn-primary sm:!min-w-[8rem]">Search</button>
            </form>
        </div>
    </section>

    <div class="public-container py-6 sm:py-10">
        <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">
            <aside class="hidden lg:block lg:w-72 xl:w-80 shrink-0">
                @include('public.partials.properties-filters-form', ['currentSort' => $currentSort])
            </aside>

            <div class="flex-1 min-w-0">
                <div class="lg:hidden mb-4">
                    <x-public.filter-drawer :label="__('Filters & sort')" :active-count="$activeFilterCount">
                        @include('public.partials.properties-filters-form', ['currentSort' => $currentSort])
                    </x-public.filter-drawer>
                </div>

                <x-public.filter-chips :filters="$activeFilters ?? []" />

                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 mb-4 sm:mb-6">
                    <p class="text-sm text-gray-600 font-medium">
                        <span class="font-black text-gray-900">{{ number_format($units->total()) }}</span> {{ Str::plural('property', $units->total()) }} available
                    </p>
                    <span class="text-xs text-gray-500">Sorted by <span class="font-bold text-gray-700">{{ $sortLabel }}</span></span>
                </div>

                @if ($units->isEmpty())
                    <x-public.empty-state
                        title="No properties match your search"
                        description="Try adjusting your filters, expanding your price range, or browsing all available listings."
                        action-label="View all properties"
                        :action-url="route('public.properties')"
                    />
                @else
                    <div class="public-listing-grid-two-max">
                        @foreach ($units as $unit)
                            <x-public.property-card :unit="$unit" :placeholder-image="$listingPlaceholderImage" />
                        @endforeach
                    </div>

                    <div class="mt-8 sm:mt-10">
                        {{ $units->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-public-layout>
