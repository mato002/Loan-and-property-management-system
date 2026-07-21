<x-loan-layout>
    <x-slot name="header">Communications</x-slot>

    <x-loan.page
        title="Communications"
        subtitle="SMS, email, bulk sends, and templates for payment reminders and notices."
    >
        <x-loan.hub-grid :items="[
            ['route' => 'loan.communications.notifications', 'title' => 'Notifications', 'description' => 'System alerts, logins, and internal events.'],
            ['route' => 'loan.communications.messages', 'title' => 'SMS / email', 'description' => 'Send, resend, and review outbound delivery.'],
            ['route' => 'loan.communications.bulk', 'title' => 'Bulk messaging', 'description' => 'Segmented campaigns.'],
            ['route' => 'loan.communications.templates', 'title' => 'Templates', 'description' => 'Merge fields and compliance text.'],
            ['route' => 'loan.communications.payment_templates', 'title' => 'Payment templates', 'description' => 'Stage wording, preview, and SMS cost estimate.'],
            ['route' => 'loan.communications.conversations', 'title' => 'Conversations', 'description' => 'Inbound and reply threads.'],
        ]" />
    </x-loan.page>
</x-loan-layout>
