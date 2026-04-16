@php($title = 'Roles & Permissions — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Roles & permissions</h1>
        <p class="mt-1 text-sm text-slate-600">Overview of property access-control definitions.</p>
    </div>

    @if (! $tablesReady)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">RBAC tables are not ready (`pm_roles`, `pm_permissions`).</div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden min-w-0">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="text-sm font-black text-slate-900">Roles</h2>
                </div>
                <div class="overflow-x-auto overscroll-x-contain">
                <table class="min-w-[480px] w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-5 py-3 text-left font-bold">Role</th>
                            <th class="px-5 py-3 text-left font-bold">Scope</th>
                            <th class="px-5 py-3 text-right font-bold">Permissions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($roles as $role)
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="font-semibold text-slate-900">{{ $role->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $role->slug }}</div>
                                </td>
                                <td class="px-5 py-3">{{ ucfirst((string) $role->portal_scope) }}</td>
                                <td class="px-5 py-3 text-right">{{ (int) $role->permissions_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-slate-500">No roles found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden min-w-0">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="text-sm font-black text-slate-900">Permissions by group</h2>
                </div>
                <div class="p-5 space-y-4">
                    @forelse ($permissionsByGroup as $group => $permissions)
                        <div class="rounded-xl border border-slate-200 p-3">
                            <div class="text-xs font-bold uppercase text-slate-500">{{ $group }}</div>
                            <div class="mt-2 text-sm text-slate-700">{{ $permissions->count() }} permission(s)</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No permissions found.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
@endsection

