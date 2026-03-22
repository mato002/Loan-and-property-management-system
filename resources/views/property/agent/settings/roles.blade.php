<x-property.workspace
    title="User roles"
    subtitle="RBAC for staff — modules, portfolios, and sensitive actions (refunds, notices, payouts)."
    back-route="property.settings.index"
    :stats="[
        ['label' => 'Users', 'value' => '0', 'hint' => 'Property app'],
        ['label' => 'Roles', 'value' => '0', 'hint' => 'Defined'],
        ['label' => 'Last change', 'value' => '—', 'hint' => 'Audit'],
    ]"
    :columns="['User', 'Email', 'Role', 'Portfolios', 'Last login', 'MFA', 'Actions']"
    empty-title="No role assignments"
    empty-hint="Seed Admin, Manager, Accountant, Leasing, Maintenance coordinator; customize per org."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'settings-invite-user') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >Invite user</a>
    </x-slot>
</x-property.workspace>
