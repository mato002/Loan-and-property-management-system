<x-property-layout>
    <x-slot name="header">Communications</x-slot>

    <x-property.page
        title="Communications"
        subtitle="SMS, email, bulk sends, and templates for rent reminders and notices."
    >
        <x-property.module-status label="Communications" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.communications.messages', 'title' => 'SMS / email', 'description' => 'Transactional log.'],
            ['route' => 'property.communications.bulk', 'title' => 'Bulk messaging', 'description' => 'Segmented campaigns.'],
            ['route' => 'property.communications.templates', 'title' => 'Templates', 'description' => 'Merge fields and compliance text.'],
        ]" />
    </x-property.page>
</x-property-layout>
