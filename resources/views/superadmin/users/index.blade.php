@php($title = 'Users — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Users</h1>
            <p class="text-sm text-slate-600 mt-1">Approve module access, manage roles and permissions, and create staff accounts.</p>
        </div>
        <a href="{{ route('superadmin.users.create') }}" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white hover:bg-indigo-700">
            Add user
        </a>
    </div>

    <form method="get" class="mb-6">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <input
                type="text"
                name="q"
                value="{{ $q }}"
                placeholder="Search name or email…"
                class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:col-span-2"
            />
            <select name="role" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All roles</option>
                <option value="super_admin" @selected(($role ?? '') === 'super_admin')>Super Admin</option>
                <option value="agent" @selected(($role ?? '') === 'agent')>Agent</option>
                <option value="landlord" @selected(($role ?? '') === 'landlord')>Landlord</option>
                <option value="tenant" @selected(($role ?? '') === 'tenant')>Tenant</option>
                <option value="none" @selected(($role ?? '') === 'none')>No property role</option>
            </select>
            <select name="per_page" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ([10, 20, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }} / page</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2">
                <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                <a href="{{ route('superadmin.users.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </div>
    </form>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('superadmin.users.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
        <a href="{{ route('superadmin.users.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
        <a href="{{ route('superadmin.users.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-5 py-3 text-left font-bold">User</th>
                    <th class="px-5 py-3 text-left font-bold">Flags</th>
                    <th class="px-5 py-3 text-right font-bold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $u)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ $u->name }}</div>
                            <div class="text-slate-500">{{ $u->email }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                @if ($u->is_super_admin)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Super Admin</span>
                                @endif
                                @if ($u->property_portal_role)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-800">Property: {{ $u->property_portal_role }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('superadmin.users.edit', $u) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                Manage
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-10 text-center text-slate-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
@endsection

