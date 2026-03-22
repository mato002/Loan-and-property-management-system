<x-property-layout>
    <x-slot name="header">Listings</x-slot>

    <x-property.page
        title="Listings"
        subtitle="Setup ties a vacant unit to photos and publish. Vacant units is the full roster; Live on website lists what is already public."
    >
        <x-property.module-status label="Listings" class="mb-4" />

        @if (! empty($hubStats ?? []))
            <div class="grid gap-3 sm:grid-cols-3 w-full min-w-0 mb-6">
                @foreach ($hubStats as $s)
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $s['label'] }}</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white tabular-nums break-words">{{ $s['value'] }}</p>
                        @if (! empty($s['hint'] ?? null))
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $s['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <x-property.hub-grid :items="$hubItems ?? [
            ['route' => 'property.listings.create', 'title' => 'Setup a listing', 'description' => 'Pick a vacant unit, then photos & publish.'],
            ['route' => 'property.listings.vacant', 'title' => 'Vacant units', 'description' => 'Full roster and status.'],
            ['route' => 'property.listings.ads', 'title' => 'Live on website', 'description' => 'Published units and links.'],
            ['route' => 'property.listings.leads', 'title' => 'Leads', 'description' => 'Optional pipeline (forms).'],
            ['route' => 'property.listings.applications', 'title' => 'Applications', 'description' => 'Roadmap.'],
        ]" />
    </x-property.page>
</x-property-layout>
