<x-property-layout>
    <x-slot name="header">Communications</x-slot>

    <x-property.page
        title="Communications"
        subtitle="SMS, email, bulk sends, and templates for rent reminders and notices."
    >
        <x-property.module-status label="Communications" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.notifications', 'title' => 'Notifications', 'description' => 'System alerts, logins, and internal events.'],
            ['route' => 'property.communications.messages', 'title' => 'SMS / email', 'description' => 'Send, resend, and review outbound delivery.'],
            ['route' => 'property.communications.bulk', 'title' => 'Bulk messaging', 'description' => 'Segmented campaigns.'],
            ['route' => 'property.communications.templates', 'title' => 'Templates', 'description' => 'Merge fields and compliance text.'],
            ['route' => 'property.communications.rent_templates', 'title' => 'Rent templates', 'description' => 'Stage wording, preview, and SMS cost estimate.'],
            ['route' => 'property.communications.conversations', 'title' => 'Conversations', 'description' => 'Inbound and reply threads.'],
        ]" />
    </x-property.page>
</x-property-layout>
