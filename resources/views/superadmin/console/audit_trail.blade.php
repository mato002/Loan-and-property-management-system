@php($title = 'Audit Trail — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Audit trail</h1>
        <p class="mt-1 text-sm text-slate-600">Recent portal actions across users.</p>
    </div>

    @if (! $tablesReady)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">`pm_portal_actions` table is not available yet.</div>
    @else
        <form method="get" class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <input
                type="text"
                name="q"
                value="{{ $q }}"
                placeholder="Search action key, notes, role..."
                class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:col-span-2"
            />
            <select name="role" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All roles</option>
                <option value="super_admin" @selected(($role ?? '') === 'super_admin')>Super Admin</option>
                <option value="agent" @selected(($role ?? '') === 'agent')>Agent</option>
                <option value="landlord" @selected(($role ?? '') === 'landlord')>Landlord</option>
                <option value="tenant" @selected(($role ?? '') === 'tenant')>Tenant</option>
            </select>
            <select name="per_page" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($perPage ?? 30) === $size)>{{ $size }} / page</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2">
                <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                <a href="{{ route('superadmin.audit_trail') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
        <div class="mb-4 flex items-center gap-2">
            <a href="{{ route('superadmin.audit_trail', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
            <a href="{{ route('superadmin.audit_trail', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
            <a href="{{ route('superadmin.audit_trail', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">When</th>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Role</th>
                        <th class="px-5 py-3 text-left font-bold">Action key</th>
                        <th class="px-5 py-3 text-left font-bold">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-5 py-3">{{ optional($item->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-3">
                                <div class="font-semibold text-slate-900">{{ $item->user?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $item->user?->email ?? '' }}</div>
                            </td>
                            <td class="px-5 py-3">{{ $item->portal_role }}</td>
                            <td class="px-5 py-3">{{ $item->action_key }}</td>
                            <td class="px-5 py-3">{{ \Illuminate\Support\Str::limit((string) ($item->notes ?? '—'), 90) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No actions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $items->links() }}
        </div>
    @endif
@endsection

