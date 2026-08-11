<x-property-layout>
    <x-slot name="header">Settings</x-slot>
    @php
        $isSuperAdmin = (bool) (auth()->user()->is_super_admin ?? false);
        $canSystemSetup = auth()->user()->hasPmPermission('settings.manage');
        $canTeamUsers = auth()->user()->hasPmPermission('team.users.manage');
        $canViewPermissionCatalog = auth()->user()->hasPmPermission('settings.access.manage');
        $tabLinks = [
            ['route' => 'property.settings.commission', 'label' => 'Commission'],
            ['route' => 'property.settings.payments', 'label' => 'Payment config'],
            ['route' => 'property.settings.forwarder', 'label' => 'My SMS Forwarder'],
            ['route' => 'property.settings.branding', 'label' => 'Branding'],
            ['route' => 'property.settings.rules', 'label' => 'System rules'],
            ['route' => 'property.settings.deposits', 'label' => 'Deposit rules'],
            ['route' => 'property.settings.expenses', 'label' => 'Expense charge rules'],
        ];
        $hubItems = [
            ['route' => 'property.settings.commission', 'title' => 'Commission settings', 'description' => 'Plans and overrides.'],
            ['route' => 'property.settings.payments', 'title' => 'Payment config (M-Pesa)', 'description' => 'Paybill, STK, settlement.'],
            ['route' => 'property.settings.forwarder', 'title' => 'My SMS Forwarder', 'description' => 'Generate the personal token your office phone uses to forward M-Pesa SMS so payments are tagged to you.'],
            ['route' => 'property.settings.branding', 'title' => 'Branding', 'description' => 'Company name and logo used in printable docs.'],
            ['route' => 'property.settings.rules', 'title' => 'System rules', 'description' => 'Penalties, reminders, guardrails.'],
            ['route' => 'property.settings.deposits', 'title' => 'Deposit rules', 'description' => 'Deposit types, required flags, formulas, ledger mapping.'],
            ['route' => 'property.settings.expenses', 'title' => 'Expense charge rules', 'description' => 'Charge lines, required flags, formulas, and ledger mapping.'],
        ];
        $prefixTabs = [];
        $prefixHub = [];
        if ($isSuperAdmin || $canTeamUsers) {
            $prefixTabs[] = ['route' => 'property.settings.roles', 'label' => 'Property users'];
            $prefixHub[] = ['route' => 'property.settings.roles', 'title' => 'Property users', 'description' => 'Add staff logins and review assignments.'];
        }
        if ($isSuperAdmin || $canViewPermissionCatalog) {
            $prefixTabs[] = ['route' => 'property.settings.permissions', 'label' => 'Permissions'];
            $prefixHub[] = ['route' => 'property.settings.permissions', 'title' => 'Permissions', 'description' => 'View all permission keys and usage.'];
        }
        $tabLinks = array_merge($prefixTabs, $tabLinks);
        $hubItems = array_merge($prefixHub, $hubItems);
        if ($canSystemSetup) {
            $tabLinks[] = ['route' => 'property.settings.system_setup', 'label' => 'System setup'];
            $hubItems[] = ['route' => 'property.settings.system_setup', 'title' => 'System setup', 'description' => 'Adjust forms, workflows, and templates.'];
        }
        if ($canSystemSetup || $canViewPermissionCatalog) {
            $tabLinks[] = ['route' => 'property.settings.activity_log', 'label' => 'Activity log'];
            $hubItems[] = ['route' => 'property.settings.activity_log', 'title' => 'Activity log', 'description' => 'Who changed settings, leases, invoices, and other system records.'];
        }
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

