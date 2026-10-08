@php
    $userRows = $tableRows ?? [];
    $userColumns = $columns ?? [];
    $roleCount = ($customRoles ?? collect())->count();
    $userCount = count($userRows);
    $openUsers = ! $errors->any() && (
        request('panel') === 'users'
        || session()->has('team_user_created')
    );
@endphp

<div
    x-data="{ tab: @js($openUsers ? 'users' : 'roles') }"
    class="space-y-4"
>
    <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Roles and users">
        <button
            type="button"
            role="tab"
            :aria-selected="(tab === 'roles').toString()"
            @click="tab = 'roles'"
            :class="tab === 'roles' ? 'bg-blue-600 border-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200'"
            class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold"
        >
            Roles
            <span class="rounded-full px-1.5 text-xs tabular-nums" :class="tab === 'roles' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200'">{{ $roleCount }}</span>
        </button>
        <button
            type="button"
            role="tab"
            :aria-selected="(tab === 'users').toString()"
            @click="tab = 'users'"
            :class="tab === 'users' ? 'bg-blue-600 border-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200'"
            class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold"
        >
            Users
            <span class="rounded-full px-1.5 text-xs tabular-nums" :class="tab === 'users' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200'">{{ $userCount }}</span>
        </button>
    </div>

    <div x-show="tab === 'roles'" x-cloak role="tabpanel">
        @include('property.agent.settings.partials.custom_roles')
    </div>

    <div x-show="tab === 'users'" x-cloak role="tabpanel" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Users</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">People who currently have a portal login.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if (\Illuminate\Support\Facades\Route::has('property.settings.team_users.create'))
                    <a
                        href="{{ route('property.settings.team_users.create') }}"
                        data-turbo-frame="property-main"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >Add team member</a>
                @endif
                <a
                    href="{{ route('property.workspace.form.show', 'settings-invite-user') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50"
                >Invite user</a>
            </div>
        </div>

        @if ($userCount === 0)
            <p class="text-sm text-slate-500">No users with a property portal role were found.</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-600">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                        <tr>
                            @foreach ($userColumns as $column)
                                <th class="px-3 py-2 font-semibold whitespace-nowrap">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($userRows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-200 align-top">
                                        @if ($cell instanceof \Illuminate\Support\HtmlString)
                                            {!! $cell !!}
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
