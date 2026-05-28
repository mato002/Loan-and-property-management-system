<x-property-layout>
    <x-slot name="header">Listings</x-slot>

    <x-property.page
        title="Listings"
    >

        @if (! empty($hubStats ?? []))
            <div class="grid gap-3 sm:grid-cols-3 w-full min-w-0 mb-6">
                @foreach ($hubStats as $s)
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $s['label'] }}</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white tabular-nums break-words">{{ $s['value'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <x-property.hub-grid :items="$hubItems ?? [
            ['route' => 'property.listings.create', 'title' => 'Setup a listing', 'description' => ''],
            ['route' => 'property.listings.vacant', 'title' => 'Vacant units', 'description' => ''],
            ['route' => 'property.listings.ads', 'title' => 'Live on website', 'description' => ''],
            ['route' => 'property.listings.leads', 'title' => 'Leads', 'description' => ''],
            ['route' => 'property.listings.applications', 'title' => 'Applications', 'description' => ''],
        ]" />
    </x-property.page>
</x-property-layout>
