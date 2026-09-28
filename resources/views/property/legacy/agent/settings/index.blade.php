<x-property-layout>
    <x-slot name="header">Settings</x-slot>
    @php
        $isSuperAdmin = (bool) (auth()->user()->is_super_admin ?? false);
        $canSystemSetup = auth()->user()->hasPmPermission('settings.manage');
        $canTeamUsers = auth()->user()->hasPmPermission('team.users.manage');
        $canViewPermissionCatalog = auth()->user()->hasPmPermission('settings.access.manage');
        $hubItems = \App\Support\Property\PropertyWorkspaceTabs::settingsHubItems();
    @endphp

    <x-property.page
        title="Settings"
        subtitle="{{ $isSuperAdmin ? 'Users, commissions, M-Pesa rails, and automation rules.' : (($canSystemSetup || $canTeamUsers || $canViewPermissionCatalog) ? 'Team access, commissions, payment config, branding, rules, and optional system setup.' : 'Commission, payment config, branding, and automation rules for agents.') }}"
    >
        <x-property.module-status label="Settings" class="mb-4" />

        @include('property.agent.settings.partials.subnav')

        <x-property.hub-grid :items="$hubItems" />
    </x-property.page>
</x-property-layout>
