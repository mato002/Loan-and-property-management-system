@php($title = 'Manage user — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Manage user</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $user->name }} · {{ $user->email }}</p>
        </div>
        <a href="{{ route('superadmin.users.index') }}" class="w-full lg:w-auto text-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
            Back to users
        </a>
    </div>

    <form method="post" action="{{ route('superadmin.users.update', $user) }}" class="space-y-8">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 min-w-0">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
                    <h2 class="text-lg font-black text-slate-900">Account</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Name</label>
                            <input name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                            @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Email</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                            @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">New password (optional)</label>
                            <input type="password" name="password" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('password')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Property portal role</label>
                            <select name="property_portal_role" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">None</option>
                                @foreach (['agent' => 'Agent', 'landlord' => 'Landlord', 'tenant' => 'Tenant'] as $k => $lbl)
                                    <option value="{{ $k }}" @selected(old('property_portal_role', $user->property_portal_role) === $k)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            @error('property_portal_role')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-2">Loan role</label>
                            <select name="loan_role" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">None</option>
                                @foreach ([
                                    'admin' => 'Administrator',
                                    'manager' => 'Manager',
                                    'officer' => 'Loan officer',
                                    'accountant' => 'Accountant',
                                    'applicant' => 'Applicant',
                                    'user' => 'General user',
                                ] as $k => $lbl)
                                    <option value="{{ $k }}" @selected(old('loan_role', $user->loan_role) === $k)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            @error('loan_role')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-700">
                        <input type="checkbox" name="is_super_admin" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_super_admin', $user->is_super_admin) ? true : false) />
                        Super admin (full access)
                    </label>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
                    <h2 class="text-lg font-black text-slate-900">Module approvals</h2>
                    <p class="text-sm text-slate-600">Set which modules this user can access. <span class="font-semibold text-slate-700">Revoked</span> is the only status that always blocks. If a user already has a Property portal role (agent/landlord/tenant) or a Loan role, they are treated as allowed for that module until you revoke or clear those roles—even when the database row is still “pending”.</p>

                    @php($statuses = ['approved' => 'Approved', 'pending' => 'Pending', 'revoked' => 'Revoked'])
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Property module</label>
                            <select name="module_property" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($statuses as $k => $lbl)
                                    <option value="{{ $k }}" @selected(old('module_property', $moduleAccess['property']) === $k)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            @error('module_property')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Loan module</label>
                            <select name="module_loan" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($statuses as $k => $lbl)
                                    <option value="{{ $k }}" @selected(old('module_loan', $moduleAccess['loan']) === $k)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            @error('module_loan')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
                    <h2 class="text-lg font-black text-slate-900">Property RBAC</h2>
                    <p class="text-sm text-slate-600">Assign Property module roles and/or direct permissions. When <span class="font-semibold text-slate-800">Property module</span> is Approved and this user has no roles saved yet, common roles for their <span class="font-semibold text-slate-800">Property portal role</span> are pre-selected (agents: Property Manager, Leasing Officer, Finance Clerk; landlords/tenants: their portal role).</p>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-black text-slate-800 mb-3">Roles</h3>
                            @if ($pmRoles->isEmpty())
                                <p class="text-sm text-slate-500">No roles found (run migrations/seed for property RBAC).</p>
                            @else
                                <div class="space-y-2 max-h-72 overflow-auto rounded-xl border border-slate-200 p-4">
                                    @foreach ($pmRoles as $role)
                                        <label class="flex items-start gap-3 text-sm">
                                            <input
                                                type="checkbox"
                                                name="pm_role_ids[]"
                                                value="{{ $role->id }}"
                                                class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                @checked(in_array($role->id, old('pm_role_ids', $propertyPmRoleCheckboxDefaults ?? $selectedRoleIds)))
                                            />
                                            <span>
                                                <span class="font-bold text-slate-900">{{ $role->name }}</span>
                                                @if($role->description)
                                                    <span class="block text-xs text-slate-500">{{ $role->description }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div>
                            <h3 class="text-sm font-black text-slate-800 mb-3">Direct permissions</h3>
                            @if ($pmPermissions->isEmpty())
                                <p class="text-sm text-slate-500">No permissions found.</p>
                            @else
                                <div class="space-y-2 max-h-72 overflow-auto rounded-xl border border-slate-200 p-4">
                                    @foreach ($pmPermissions as $perm)
                                        <label class="flex items-start gap-3 text-sm">
                                            <input
                                                type="checkbox"
                                                name="pm_permission_ids[]"
                                                value="{{ $perm->id }}"
                                                class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                @checked(in_array($perm->id, old('pm_permission_ids', $selectedPermissionIds)))
                                            />
                                            <span>
                                                <span class="font-bold text-slate-900">{{ $perm->name }}</span>
                                                <span class="block text-xs text-slate-500">{{ $perm->group }} · {{ $perm->key }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Save</h2>
                    <p class="text-sm text-slate-600 mt-1">Apply approvals, roles and permissions.</p>
                    <button class="mt-4 w-full rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700">Save changes</button>
                </div>
            </div>
        </div>
    </form>
@endsection

