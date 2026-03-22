<x-property-layout>
    <x-slot name="header">Analytics</x-slot>

    <x-property.page
        title="Analytics"
        subtitle="Weekly and monthly intelligence — not for daily clutter."
    >
        <x-property.module-status label="Analytics" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.performance.collection_rate', 'title' => 'Rent collection rate', 'description' => 'Budget vs actual.'],
            ['route' => 'property.performance.vacancy', 'title' => 'Vacancy trends', 'description' => 'Duration and loss-to-lease.'],
            ['route' => 'property.performance.arrears_trends', 'title' => 'Arrears trends', 'description' => 'Cure rates and roll-forward.'],
            ['route' => 'property.performance.maintenance_trends', 'title' => 'Maintenance cost trends', 'description' => 'Cost per door over time.'],
        ]" />
    </x-property.page>
</x-property-layout>
