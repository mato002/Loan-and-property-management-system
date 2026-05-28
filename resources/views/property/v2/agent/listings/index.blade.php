<x-property-layout>
    <x-slot name="header">Listings</x-slot>

    <x-property.page
        title="Listings"
        workspace="listings"
    >

        @if (! empty($hubStats ?? []))
            <x-property.responsive.stat-card-grid :stats="$hubStats" class="mb-6" />
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
