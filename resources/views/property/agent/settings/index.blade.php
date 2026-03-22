<x-property-layout>
    <x-slot name="header">Settings</x-slot>

    <x-property.page
        title="Settings"
        subtitle="Users, commissions, M-Pesa rails, and automation rules."
    >
        <x-property.module-status label="Settings" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.settings.roles', 'title' => 'Users & roles', 'description' => 'RBAC for your team.'],
            ['route' => 'property.settings.commission', 'title' => 'Commission settings', 'description' => 'Plans and overrides.'],
            ['route' => 'property.settings.payments', 'title' => 'Payment config (M-Pesa)', 'description' => 'Paybill, STK, settlement.'],
            ['route' => 'property.settings.rules', 'title' => 'System rules', 'description' => 'Penalties, reminders, guardrails.'],
        ]" />
    </x-property.page>
</x-property-layout>
