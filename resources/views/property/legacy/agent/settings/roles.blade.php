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
    <x-slot name="above">
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.roles'])
        @include('property.agent.settings.partials.custom_roles')
    </x-slot>

    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'settings-invite-user') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >Invite user</a>
    </x-slot>

    <x-slot name="footer">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            Create a custom role in the form above, then open Set permissions to choose what that role can do. The table below is who currently has a portal login.
        </p>
    </x-slot>
</x-property.workspace>

