<x-property-layout>
    <x-slot name="header">System setup · Access control</x-slot>

    <x-property.page
        title="Access control"
        subtitle="Add more roles and permissions, then map them to users."
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.system_setup') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">System setup hub</a>
            <a href="{{ route('property.settings.system_setup.forms') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Form adjustments</a>
            <a href="{{ route('property.settings.system_setup.workflows') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Workflow adjustments</a>
            <a href="{{ route('property.settings.system_setup.templates') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Template adjustments</a>
            <a href="{{ route('property.settings.system_setup.access') }}" aria-current="page" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">Access control</a>
        </div>

        @if (session('success'))
            <p class="mb-4 text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif

        @if (! $tablesReady)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Access-control tables are not ready yet. Run <code>php artisan migrate</code> then reload this page.
            </div>
        @else
            <div class="grid gap-6 xl:grid-cols-2">
                <form method="post" action="{{ route('property.settings.system_setup.access.roles.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                    @csrf
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add role</h3>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Role name (e.g. Accountant)" />
                    <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="role_slug" />
                    <select name="portal_scope" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['agent' => 'Agent', 'landlord' => 'Landlord', 'tenant' => 'Tenant', 'any' => 'Any'] as $scopeKey => $scopeLabel)
                            <option value="{{ $scopeKey }}" @selected(old('portal_scope', 'agent') === $scopeKey)>{{ $scopeLabel }}</option>
                        @endforeach
                    </select>
                    <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional description">{{ old('description') }}</textarea>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create role</button>
                </form>

                @php
                    $cloneFrom = (int) request()->query('clone_from', 0);
                    $cloneScope = request()->query('clone_scope', old('portal_scope', 'agent'));
                    $cloneName = request()->query('clone_name', old('name'));
                @endphp
                <form id="clone-role-form" method="post" action="{{ route('property.settings.system_setup.access.roles.clone') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                    @csrf
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Clone role</h3>
                    <select name="source_role_id" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" required>
                        <option value="">Select source role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($cloneFrom === (int) $role->id)>{{ $role->name }} ({{ $role->slug }})</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" value="{{ $cloneName }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="New role name" />
                    <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="new_role_slug" />
                    <select name="portal_scope" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['agent' => 'Agent', 'landlord' => 'Landlord', 'tenant' => 'Tenant', 'any' => 'Any'] as $scopeKey => $scopeLabel)
                            <option value="{{ $scopeKey }}" @selected($cloneScope === $scopeKey)>{{ $scopeLabel }}</option>
                        @endforeach
                    </select>
                    <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional description">{{ old('description') }}</textarea>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Clone role</button>
                </form>

                <form method="post" action="{{ route('property.settings.system_setup.access.permissions.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                    @csrf
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add permission</h3>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Permission name (e.g. Approve payouts)" />
                    <input type="text" name="key" value="{{ old('key') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="permission.key (e.g. payouts.approve)" />
                    <input type="text" name="group" value="{{ old('group', 'general') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Group (e.g. payments)" />
                    <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional description">{{ old('description') }}</textarea>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create permission</button>
                </form>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                @foreach ($roles as $role)
                    <form method="post" action="{{ route('property.settings.system_setup.access.roles.permissions.store', $role) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                        @csrf
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $role->name }} <span class="text-xs text-slate-500">({{ $role->slug }})</span></h4>
                                <p class="text-xs text-slate-500">Scope: {{ ucfirst($role->portal_scope) }}</p>
                            </div>
                            <a href="{{ route('property.settings.system_setup.access', ['clone_from' => $role->id, 'clone_scope' => $role->portal_scope, 'clone_name' => $role->name . ' Copy']) }}#clone-role-form" class="rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-[11px] font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Clone this role</a>
                        </div>
                        <div class="max-h-52 overflow-auto space-y-3 pr-1">
                            @forelse ($permissionsByGroup as $group => $permissions)
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">{{ $group }}</p>
                                    <div class="space-y-1">
                                        @foreach ($permissions as $permission)
                                            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                                <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($role->permissions->pluck('id')->contains($permission->id)) />
                                                <span>{{ $permission->name }} <span class="text-slate-400">({{ $permission->key }})</span></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">No permissions yet.</p>
                            @endforelse
                        </div>
                        <button type="submit" class="mt-4 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save role permissions</button>
                    </form>
                @endforeach
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Assign roles to users</h3>
                <div class="space-y-4 max-h-[520px] overflow-auto pr-1">
                    @foreach ($portalUsers as $u)
                        <form method="post" action="{{ route('property.settings.system_setup.access.users.roles.store', $u) }}" class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                            @csrf
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $u->name }} <span class="text-xs text-slate-500">({{ $u->property_portal_role }})</span></p>
                                <button type="submit" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save user roles</button>
                            </div>
                            <p class="text-xs text-slate-500 mb-2">{{ $u->email }}</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($roles as $role)
                                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($u->pmRoles->pluck('id')->contains($role->id)) />
                                        {{ $role->name }}
                                    </label>
                                @endforeach
                            </div>
                        </form>

                        <form method="post" action="{{ route('property.settings.system_setup.access.users.permissions.store', $u) }}" class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                            @csrf
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Direct permissions for {{ $u->name }}</p>
                                <button type="submit" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save direct permissions</button>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($permissionsByGroup as $group => $permissions)
                                    @foreach ($permissions as $permission)
                                        <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($u->pmPermissions->pluck('id')->contains($permission->id)) />
                                            {{ $permission->key }}
                                        </label>
                                    @endforeach
                                @endforeach
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </x-property.page>
</x-property-layout>

