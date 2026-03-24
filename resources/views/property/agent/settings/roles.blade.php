<x-property.workspace
    title="Property users"
    subtitle="RBAC for staff — modules, portfolios, and sensitive actions (refunds, notices, payouts)."
    back-route="property.settings.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No role assignments"
    empty-hint="No users with a property portal role were found."
>
    <x-slot name="toolbar">
        <a href="{{ route('property.settings.roles') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Property users</a>
        <a href="{{ route('property.settings.permissions') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Permissions</a>
        <a href="{{ route('property.settings.commission') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Commission</a>
        <a href="{{ route('property.settings.payments') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payment config</a>
        <a href="{{ route('property.settings.branding') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Branding</a>
        <a href="{{ route('property.settings.rules') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System rules</a>
        <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup</a>
    </x-slot>

    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'settings-invite-user') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >Invite user</a>
    </x-slot>

    <x-slot name="footer">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            Role changes are managed from user profile/administration workflows in this build. This page gives a live assignment overview for agent, landlord, and tenant access.
        </p>
    </x-slot>
</x-property.workspace>

