<x-property.workspace
    title="Property users"
    subtitle="Switch between roles and the people who can sign in."
    back-route="property.settings.index"
    :stats="$stats"
    :columns="[]"
    :legacy-toolbar="false"
    :show-search="false"
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

        @include('property.agent.settings.partials.roles_users_tabs')
    </x-slot>
</x-property.workspace>
