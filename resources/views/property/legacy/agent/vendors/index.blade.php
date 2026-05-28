<x-property-layout>
    <x-slot name="header">Vendors</x-slot>

    <x-property.page
        title="Vendors"
        subtitle="Procurement kept separate — directory, bidding, quotes, performance."
    >
        <x-property.module-status label="Vendors" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.vendors.directory', 'title' => 'Vendor directory', 'description' => 'Onboarding and categories.'],
            ['route' => 'property.vendors.bidding', 'title' => 'Job bidding', 'description' => 'Scoped invitations to quote.'],
            ['route' => 'property.vendors.quotes', 'title' => 'Quotes', 'description' => 'Comparison and award.'],
            ['route' => 'property.vendors.performance', 'title' => 'Vendor performance', 'description' => 'SLA, rework, variance.'],
        ]" />
    </x-property.page>
</x-property-layout>
