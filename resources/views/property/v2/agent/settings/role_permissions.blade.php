<x-property.workspace
    :title="'Permissions: '.$role->name"
    subtitle="Choose what this role can do. You stay in Users & roles."
    back-route="property.settings.roles"
    :stats="[
        ['label' => 'Role', 'value' => $role->name, 'hint' => $role->slug],
        ['label' => 'Scope', 'value' => ucfirst((string) $role->portal_scope), 'hint' => ''],
        ['label' => 'Granted', 'value' => (string) count($selectedIds), 'hint' => 'Selected permissions'],
    ]"
    :columns="[]"
    :legacy-toolbar="false"
    :show-search="false"
>
    <x-slot name="above">
        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.roles'])
        @include('property.agent.settings.partials.roles_users_links')
    </x-slot>

    <form method="post" action="{{ route('property.settings.system_setup.access.roles.permissions.store', $role) }}" class="space-y-4" data-turbo="false">
        @csrf
        @forelse ($permissionsByGroup as $group => $permissions)
            <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $group }}</h2>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($permissions as $permission)
                        <label class="flex items-start gap-2 rounded-lg border border-slate-100 dark:border-slate-700 px-3 py-2 text-sm text-slate-800 dark:text-slate-100">
                            <input
                                type="checkbox"
                                name="permission_ids[]"
                                value="{{ $permission->id }}"
                                class="mt-0.5 rounded border-slate-300 text-blue-600"
                                @checked(in_array((int) $permission->id, $selectedIds, true))
                            />
                            <span>
                                <span class="font-medium">{{ $permission->name }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $permission->key }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-sm text-slate-500">No permissions are defined yet.</p>
        @endforelse

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex min-h-[40px] items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save permissions</button>
            <a href="{{ route('property.settings.roles') }}" data-turbo-frame="property-main" class="inline-flex min-h-[40px] items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50">Back to roles</a>
        </div>
    </form>
</x-property.workspace>
