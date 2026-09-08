<x-property-layout>
    <x-slot name="header">System setup  -  Access control</x-slot>

    <x-property.page
        title="Access control"
        subtitle="Use the matrix to grant permissions per role in one view. User badges show counts from assigned roles, optional direct overrides, and the effective total (what the app enforces)."
    >
        @include('property.agent.settings.system_setup.partials.hub_nav', ['active' => 'property.settings.system_setup.access'])
        @if (! $tablesReady)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Access-control tables are not ready yet. Run <code>php artisan migrate</code> then reload this page.
            </div>
        @else
            @php
                $cloneFrom = (int) request()->query('clone_from', 0);
                $cloneScope = request()->query('clone_scope', old('portal_scope', 'agent'));
                $cloneName = request()->query('clone_name', old('name'));
            @endphp
            <div x-data="{ modal: @js($cloneFrom > 0 ? 'clone-role' : '') }" class="space-y-4">
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="modal = 'add-role'" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Add role</button>
                        <button type="button" @click="modal = 'clone-role'" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Clone role</button>
                        <button type="button" @click="modal = 'add-permission'" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Add permission</button>
                    </div>
                </div>

                <div x-show="modal !== ''" x-cloak class="fixed inset-0 z-[7000] flex items-center justify-center bg-slate-900/50 p-4">
                    <div class="w-full max-w-xl rounded-2xl bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700" @click.stop>
                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-5 py-3">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white" x-text="modal === 'add-role' ? 'Add role' : (modal === 'clone-role' ? 'Clone role' : 'Add permission')"></h3>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close" @click="modal = ''">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>

                        <form x-show="modal === 'add-role'" method="post" action="{{ route('property.settings.system_setup.access.roles.store') }}" class="p-5 space-y-3" data-turbo="false">
                            @csrf
                            <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Role name (e.g. Accountant)" />
                            <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="role_slug" />
                            <select name="portal_scope" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                @foreach (['agent' => 'Agent', 'landlord' => 'Landlord', 'tenant' => 'Tenant', 'any' => 'Any'] as $scopeKey => $scopeLabel)
                                    <option value="{{ $scopeKey }}" @selected(old('portal_scope', 'agent') === $scopeKey)>{{ $scopeLabel }}</option>
                                @endforeach
                            </select>
                            <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional description">{{ old('description') }}</textarea>
                            <div class="flex justify-end"><button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create role</button></div>
                        </form>

                        <form id="clone-role-form" x-show="modal === 'clone-role'" method="post" action="{{ route('property.settings.system_setup.access.roles.clone') }}" class="p-5 space-y-3" data-turbo="false">
                            @csrf
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
                            <div class="flex justify-end"><button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Clone role</button></div>
                        </form>

                        <form x-show="modal === 'add-permission'" method="post" action="{{ route('property.settings.system_setup.access.permissions.store') }}" class="p-5 space-y-3" data-turbo="false">
                            @csrf
                            <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Permission name (e.g. Approve payouts)" />
                            <input type="text" name="key" value="{{ old('key') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="permission.key (e.g. payouts.approve)" />
                            <input type="text" name="group" value="{{ old('group', 'general') }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Group (e.g. payments)" />
                            <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional description">{{ old('description') }}</textarea>
                            <div class="flex justify-end"><button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create permission</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Role ├ù permission matrix</h3>
                        <p class="mt-1 max-w-3xl text-xs text-slate-500 dark:text-slate-400">
                            Columns are roles; rows are permissions. Tick intersections to include that permission on the role, then save once.
                            Empty landlord/tenant template roles are normal until you assign permissions.
                        </p>
                    </div>
                </div>
                <form method="post" action="{{ route('property.settings.system_setup.access.matrix.store') }}" class="space-y-3" data-turbo="false">
                    @csrf
                    @foreach ($roles as $role)
                        <input type="hidden" name="matrix_role_ids[]" value="{{ $role->id }}" />
                    @endforeach
                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-600">
                        <table class="min-w-max w-full border-collapse text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-900/80">
                                    <th class="sticky left-0 z-20 min-w-[14rem] border-r border-slate-200 bg-slate-50 px-2 py-2 font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900/95 dark:text-slate-200">Permission</th>
                                    @foreach ($roles as $role)
                                        <th class="border-l border-slate-200 px-1.5 py-2 text-center align-bottom font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">
                                            <div class="flex max-w-[7.5rem] flex-col items-center gap-1">
                                                <span class="leading-tight break-words hyphens-auto" title="{{ $role->slug }}">{{ $role->name }}</span>
                                                <span class="text-[10px] font-normal uppercase tracking-wide text-slate-400">{{ $role->portal_scope }}</span>
                                                <a href="{{ route('property.settings.system_setup.access', ['clone_from' => $role->id, 'clone_scope' => $role->portal_scope, 'clone_name' => $role->name.' Copy']) }}#clone-role-form" class="text-[10px] font-medium text-indigo-600 hover:underline">Clone</a>
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach ($permissionsByGroup as $group => $permissions)
                                    <tr class="bg-slate-100/90 dark:bg-slate-800/90">
                                        <td class="sticky left-0 z-10 border-r border-slate-200 bg-slate-100/95 px-2 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:border-slate-600 dark:bg-slate-800/95 dark:text-slate-300" colspan="{{ $roles->count() + 1 }}">{{ $group }}</td>
                                    </tr>
                                    @foreach ($permissions as $permission)
                                        <tr class="bg-white dark:bg-gray-900/40">
                                            <td class="sticky left-0 z-10 border-r border-slate-200 bg-white px-2 py-1.5 text-slate-800 dark:border-slate-600 dark:bg-gray-900/95 dark:text-slate-100">
                                                <span class="font-medium">{{ $permission->name }}</span>
                                                <span class="block font-mono text-[10px] text-slate-400">{{ $permission->key }}</span>
                                            </td>
                                            @foreach ($roles as $role)
                                                <td class="border-l border-slate-100 px-1 py-1 text-center align-middle dark:border-slate-700">
                                                    <input
                                                        type="checkbox"
                                                        name="matrix[{{ $role->id }}][]"
                                                        value="{{ $permission->id }}"
                                                        @checked($role->permissions->pluck('id')->contains($permission->id))
                                                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-500"
                                                    />
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Tip: scroll horizontally if you have many roles.</p>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save matrix</button>
                    </div>
                </form>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Assign roles to users</h3>
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    <strong>From roles</strong> counts distinct permissions granted by the user’s roles.
                    <strong>Direct overrides</strong> are extra grants (or revokes are not modeled here) attached only to that user — usually leave at 0.
                    <strong>Effective</strong> is the union (what access checks use).
                </p>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-600">
                    <div class="hidden bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-900/60 dark:text-slate-300 md:grid md:grid-cols-12 md:gap-2">
                        <div class="md:col-span-3">User</div>
                        <div class="md:col-span-3">Email</div>
                        <div class="md:col-span-1 text-center">Roles</div>
                        <div class="md:col-span-1 text-center">From roles</div>
                        <div class="md:col-span-1 text-center">Direct</div>
                        <div class="md:col-span-1 text-center">Effective</div>
                        <div class="md:col-span-2 text-right">Actions</div>
                    </div>
                    <div class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-gray-900/30">
                        @foreach ($portalUsers as $u)
                            @php $s = $portalUserRbacSummaries[$u->id] ?? ['from_roles_count' => 0, 'direct_count' => 0, 'effective_count' => 0]; @endphp
                            <div x-data="{ userModal: '' }" class="relative px-3 py-3 hover:bg-slate-50/80 dark:hover:bg-slate-800/50 md:grid md:grid-cols-12 md:items-center md:gap-2 md:py-2">
                                <div class="md:col-span-3">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $u->name }}</p>
                                    <p class="text-xs text-slate-500">({{ $u->property_portal_role }})</p>
                                </div>
                                <div class="mt-1 break-all text-xs text-slate-600 dark:text-slate-300 md:col-span-3 md:mt-0">{{ $u->email }}</div>
                                <div class="mt-2 text-xs text-slate-700 dark:text-slate-200 md:col-span-1 md:mt-0 md:text-center"><span class="text-slate-400 md:hidden">Roles: </span>{{ $u->pmRoles->count() }}</div>
                                <div class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-400 md:col-span-1 md:mt-0 md:text-center"><span class="text-slate-400 md:hidden">From roles: </span>{{ $s['from_roles_count'] }}</div>
                                <div class="mt-1 text-xs font-medium text-indigo-700 dark:text-indigo-300 md:col-span-1 md:mt-0 md:text-center"><span class="text-slate-400 md:hidden">Direct: </span>{{ $s['direct_count'] }}</div>
                                <div class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100 md:col-span-1 md:mt-0 md:text-center"><span class="text-slate-400 md:hidden">Effective: </span>{{ $s['effective_count'] }}</div>
                                <div class="mt-2 flex justify-end gap-2 md:col-span-2 md:mt-0">
                                    <button type="button" @click="userModal = 'roles'" class="rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit roles</button>
                                    <button type="button" @click="userModal = 'permissions'" class="rounded border border-indigo-300 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-950/40">Direct overrides</button>
                                </div>

                                <div x-show="userModal !== ''" x-cloak class="fixed inset-0 z-[7100] flex items-center justify-center bg-slate-900/50 p-4">
                                    <div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700" @click.stop>
                                        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-5 py-3">
                                            <h4 class="text-base font-semibold text-slate-900 dark:text-white" x-text="userModal === 'roles' ? 'Edit roles for {{ $u->name }}' : 'Direct permission overrides for {{ $u->name }}'"></h4>
                                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close" @click="userModal = ''">
                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                            </button>
                                        </div>

                                        <form x-show="userModal === 'roles'" method="post" action="{{ route('property.settings.system_setup.access.users.roles.store', $u) }}" class="p-5 space-y-3" data-turbo="false">
                                            @csrf
                                            <div class="flex max-h-[320px] flex-wrap gap-x-4 gap-y-2 overflow-y-auto pr-1">
                                                @foreach ($roles as $role)
                                                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($u->pmRoles->pluck('id')->contains($role->id)) />
                                                        {{ $role->name }}
                                                    </label>
                                                @endforeach
                                            </div>
                                            <div class="flex justify-end">
                                                <button type="submit" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save user roles</button>
                                            </div>
                                        </form>

                                        <form x-show="userModal === 'permissions'" method="post" action="{{ route('property.settings.system_setup.access.users.permissions.store', $u) }}" class="p-5 space-y-3" data-turbo="false">
                                            @csrf
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Rare: grant extra permissions without changing roles. Most users should stay at zero here.</p>
                                            <div class="max-h-[360px] overflow-y-auto pr-1 flex flex-wrap gap-x-4 gap-y-1">
                                                @foreach ($permissionsByGroup as $group => $permissions)
                                                    @foreach ($permissions as $permission)
                                                        <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($u->pmPermissions->pluck('id')->contains($permission->id)) />
                                                            {{ $permission->key }}
                                                        </label>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                            <div class="flex justify-end">
                                                <button type="submit" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Save direct overrides</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </x-property.page>
</x-property-layout>

