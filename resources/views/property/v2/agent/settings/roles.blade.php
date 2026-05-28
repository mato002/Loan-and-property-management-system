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

        @if (session('team_user_created'))
            @php($tc = session('team_user_created'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                <p class="font-semibold">{{ __('Save these details — the password is shown only once.') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-1 font-mono text-xs sm:text-sm">
                    <li>{{ __('Email') }}: {{ $tc['email'] ?? '—' }}</li>
                    <li>{{ __('Temporary password') }}: {{ $tc['temporary_password'] ?? '—' }}</li>
                </ul>
            </div>
        @endif
    </x-slot>

    <x-slot name="actions">
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <a
                href="{{ route('property.settings.team_users.create') }}"
                class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
            >{{ __('Add team member') }}</a>
            <a
                href="{{ route('property.workspace.form.show', 'settings-invite-user') }}"
                class="inline-flex justify-center items-center rounded-xl border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
            >{{ __('Record invite request') }}</a>
        </div>
    </x-slot>

    <x-slot name="footer">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            {{ __('Use “Add team member” to create logins for office staff. This table shows everyone with a property portal role; refine RBAC under System setup → Access control.') }}
        </p>
    </x-slot>
</x-property.workspace>

