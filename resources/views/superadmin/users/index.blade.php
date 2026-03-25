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
        <div class="flex flex-col sm:flex-row gap-3">
            <input
                type="text"
                name="q"
                value="{{ $q }}"
                placeholder="Search name or email…"
                class="w-full sm:max-w-md rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Search
            </button>
        </div>
    </form>

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

