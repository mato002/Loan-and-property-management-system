<x-property-layout>
    <x-slot name="header">Settings</x-slot>

    <x-property.page
        title="Settings"
        subtitle="Users, commissions, M-Pesa rails, and automation rules."
    >
        <x-property.module-status label="Settings" class="mb-4" />

        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.roles') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Users & roles</a>
            <a href="{{ route('property.settings.commission') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Commission</a>
            <a href="{{ route('property.settings.payments') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payment config</a>
            <a href="{{ route('property.settings.branding') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Branding</a>
            <a href="{{ route('property.settings.rules') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System rules</a>
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup</a>
        </div>

        <x-property.hub-grid :items="[
            ['route' => 'property.settings.roles', 'title' => 'Users & roles', 'description' => 'RBAC for your team.'],
            ['route' => 'property.settings.commission', 'title' => 'Commission settings', 'description' => 'Plans and overrides.'],
            ['route' => 'property.settings.payments', 'title' => 'Payment config (M-Pesa)', 'description' => 'Paybill, STK, settlement.'],
            ['route' => 'property.settings.branding', 'title' => 'Branding', 'description' => 'Company name and logo used in printable docs.'],
            ['route' => 'property.settings.rules', 'title' => 'System rules', 'description' => 'Penalties, reminders, guardrails.'],
            ['route' => 'property.settings.system_setup', 'title' => 'System setup', 'description' => 'Adjust forms, workflows, and templates.'],
        ]" />
    </x-property.page>
</x-property-layout>
