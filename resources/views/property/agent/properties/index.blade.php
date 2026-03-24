<x-property-layout>
    <x-slot name="header">Properties</x-slot>

    <x-property.page
        title="Properties"
        subtitle="Structural inventory only — no financials or utilities here."
    >
        <x-property.module-status label="Properties" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.properties.list', 'title' => 'All properties', 'description' => 'Portfolio shell — see landlord(s) on each row; link users below the grid.'],
            ['route' => 'property.landlords.index', 'title' => 'Landlords', 'description' => 'Every landlord account and which buildings they are tied to.'],
            ['route' => 'property.settings.roles', 'title' => 'Property users', 'description' => 'Internal workspace users (staff/agents), separate from tenants and landlords.'],
            ['route' => 'property.properties.units', 'title' => 'Units', 'description' => 'Doors, rent, status.'],
            ['route' => 'property.properties.occupancy', 'title' => 'Occupancy view', 'description' => 'Vacant vs occupied vs notice.'],
            ['route' => 'property.properties.performance', 'title' => 'Unit performance', 'description' => 'Asking rent vs active lease rent.'],
            ['route' => 'property.properties.amenities', 'title' => 'Amenities', 'description' => 'Library + tags per unit.'],
        ]" />
    </x-property.page>
</x-property-layout>
