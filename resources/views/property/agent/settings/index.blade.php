<x-property-layout>
    <x-slot name="header">Settings</x-slot>
    @php
        $isSuperAdmin = (bool) (auth()->user()->is_super_admin ?? false);
        $tabLinks = [
            ['route' => 'property.settings.commission', 'label' => 'Commission'],
            ['route' => 'property.settings.payments', 'label' => 'Payment config'],
            ['route' => 'property.settings.branding', 'label' => 'Branding'],
            ['route' => 'property.settings.rules', 'label' => 'System rules'],
            ['route' => 'property.settings.deposits', 'label' => 'Deposit rules'],
            ['route' => 'property.settings.expenses', 'label' => 'Expense charge rules'],
        ];
        $hubItems = [
            ['route' => 'property.settings.commission', 'title' => 'Commission settings', 'description' => 'Plans and overrides.'],
            ['route' => 'property.settings.payments', 'title' => 'Payment config (M-Pesa)', 'description' => 'Paybill, STK, settlement.'],
            ['route' => 'property.settings.branding', 'title' => 'Branding', 'description' => 'Company name and logo used in printable docs.'],
            ['route' => 'property.settings.rules', 'title' => 'System rules', 'description' => 'Penalties, reminders, guardrails.'],
            ['route' => 'property.settings.deposits', 'title' => 'Deposit rules', 'description' => 'Deposit types, required flags, formulas, ledger mapping.'],
            ['route' => 'property.settings.expenses', 'title' => 'Expense charge rules', 'description' => 'Charge lines, required flags, formulas, and ledger mapping.'],
        ];
        if ($isSuperAdmin) {
            $tabLinks = array_merge([
                ['route' => 'property.settings.roles', 'label' => 'Property users'],
                ['route' => 'property.settings.permissions', 'label' => 'Permissions'],
            ], $tabLinks, [
                ['route' => 'property.settings.system_setup', 'label' => 'System setup'],
            ]);
            $hubItems = array_merge([
                ['route' => 'property.settings.roles', 'title' => 'Property users', 'description' => 'RBAC for your team.'],
                ['route' => 'property.settings.permissions', 'title' => 'Permissions', 'description' => 'View all permission keys and usage.'],
            ], $hubItems, [
                ['route' => 'property.settings.system_setup', 'title' => 'System setup', 'description' => 'Adjust forms, workflows, and templates.'],
            ]);
        }
    @endphp

    <x-property.page
        title="Settings"
        subtitle="{{ $isSuperAdmin ? 'Users, commissions, M-Pesa rails, and automation rules.' : 'Commission, payment config, branding, and automation rules for agents.' }}"
    >
        <x-property.module-status label="Settings" class="mb-4" />

        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($tabLinks as $tab)
                <a href="{{ route($tab['route']) }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">{{ $tab['label'] }}</a>
            @endforeach
        </div>

        <x-property.hub-grid :items="$hubItems" />
    </x-property.page>
</x-property-layout>

