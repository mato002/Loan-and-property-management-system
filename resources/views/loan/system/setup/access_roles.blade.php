<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">System setup</a>
        </x-slot>

        @include('loan.accounting.partials.flash')

        @if (!($rbacReady ?? false))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Roles & permissions tables are not ready yet. Run <span class="font-semibold">php artisan migrate</span>, then refresh this page.
            </div>
        @endif

        <div class="mb-4 flex items-center justify-end">
            <form method="post" action="{{ route('loan.system.setup.access_roles.sync') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100" @disabled(!($rbacReady ?? false))>
                    Sync existing user roles
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-1 bg-white border border-slate-200 rounded-xl shadow-sm p-5"
                x-data="{
                    defaults: @js($defaultPermissionsByBaseRole ?? []),
                    createBaseRole: 'admin',
                    createPerms: [],
                    applyCreateDefaults() { this.createPerms = [...(this.defaults[this.createBaseRole] || [])]; }
                }"
                x-init="applyCreateDefaults()"
            >
                <h2 class="text-sm font-semibold text-slate-700">Create role</h2>
                <form method="post" action="{{ route('loan.system.setup.access_roles.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Role name</label>
                        <input name="name" class="w-full rounded-lg border-slate-200 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Base role</label>
                        <select name="base_role" x-model="createBaseRole" @change="applyCreateDefaults()" class="w-full rounded-lg border-slate-200 text-sm" required>
                            @foreach (['admin', 'manager', 'accountant', 'officer', 'applicant', 'user'] as $role)
                                <option value="{{ $role }}">{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block text-xs font-semibold text-slate-600">Permissions</label>
                            <button type="button" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700" @click="applyCreateDefaults()">Use role defaults</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($permissionCatalog as $key => $label)
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700 rounded border border-slate-200 px-2 py-1.5">
                                    <input type="checkbox" name="permissions[]" :value="'{{ $key }}'" x-model="createPerms" class="rounded border-slate-300">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full rounded-lg border-slate-200 text-sm"></textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        Active
                    </label>
                    <div>
                        <button class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040] disabled:opacity-60" @disabled(!($rbacReady ?? false))>Save role</button>
                    </div>
                </form>
            </div>

            <div class="xl:col-span-2 space-y-6">
                @foreach ($roles as $role)
                    @php
                        $assignedUserIds = $role->users()->pluck('users.id')->all();
                        $rolePerms = is_array($role->permissions) && count($role->permissions) > 0
                            ? $role->permissions
                            : (($defaultPermissionsByBaseRole[$role->base_role] ?? []));
                    @endphp
                    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5"
                        x-data="{
                            defaults: @js($defaultPermissionsByBaseRole ?? []),
                            rolePerms: @js($rolePerms),
                            applyDefaults(baseRole) { this.rolePerms = [...(this.defaults[baseRole] || [])]; }
                        }"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-900">{{ $role->name }}</h3>
                                <p class="text-xs text-slate-500 mt-1">Base role: <span class="font-semibold">{{ $role->base_role }}</span> · {{ $role->is_active ? 'Active' : 'Inactive' }}</p>
                            </div>
                            <form method="post" action="{{ route('loan.system.setup.access_roles.destroy', $role) }}" data-swal-confirm="Delete this role?">
                                @csrf
                                @method('delete')
                                <button class="text-xs font-semibold text-red-600 hover:text-red-700">Delete</button>
                            </form>
                        </div>

                        <form method="post" action="{{ route('loan.system.setup.access_roles.update', $role) }}" class="mt-4 space-y-3">
                            @csrf
                            @method('patch')
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Base role</label>
                                    <select name="base_role" class="w-full rounded-lg border-slate-200 text-sm" required @change="applyDefaults($event.target.value)">
                                        @foreach (['admin', 'manager', 'accountant', 'officer', 'applicant', 'user'] as $base)
                                            <option value="{{ $base }}" @selected($role->base_role === $base)>{{ ucfirst($base) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked($role->is_active)>
                                        Active
                                    </label>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <p class="text-xs font-semibold text-slate-600">Permissions</p>
                                    <button type="button" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700" @click="applyDefaults($el.closest('form').querySelector('select[name=base_role]').value)">Use role defaults</button>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                    @foreach ($permissionCatalog as $key => $label)
                                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 rounded border border-slate-200 px-2 py-1.5">
                                            <input type="checkbox" name="permissions[]" :value="'{{ $key }}'" x-model="rolePerms" class="rounded border-slate-300">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Update role permissions</button>
                        </form>

                        <form method="post" action="{{ route('loan.system.setup.access_roles.assign', $role) }}" class="mt-4">
                            @csrf
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Assign users</label>
                            <select name="user_ids[]" multiple class="w-full min-h-[130px] rounded-lg border-slate-200 text-sm">
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(in_array($user->id, $assignedUserIds, true))>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Tip: Hold Ctrl to select multiple users.</p>
                            <button class="mt-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Save assignments</button>
                        </form>
                    </div>
                @endforeach
                @if ($roles->isEmpty())
                    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 text-sm text-slate-600">
                        No roles yet. Use <span class="font-semibold">Sync existing user roles</span> to import from current users, or create one manually.
                    </div>
                @endif
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
