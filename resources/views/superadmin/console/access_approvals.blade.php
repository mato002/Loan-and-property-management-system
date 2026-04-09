@php($title = 'Access Approvals — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Access approvals</h1>
        <p class="mt-1 text-sm text-slate-600">Users waiting for module approval.</p>
    </div>

    @if (! $tablesReady)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">`user_module_accesses` table is not available yet.</div>
    @else
        <div class="mb-4 flex flex-col gap-3">
            <form method="get" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                <input
                    type="text"
                    name="q"
                    value="{{ $q ?? '' }}"
                    placeholder="Search pending by user name/email..."
                    class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:col-span-2"
                />
                <select name="module" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All modules</option>
                    <option value="property" @selected(($module ?? '') === 'property')>Property</option>
                    <option value="loan" @selected(($module ?? '') === 'loan')>Loan</option>
                </select>
                <select name="per_page" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ([10, 25, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2">
                    <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                    <a href="{{ route('superadmin.access_approvals') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
            <div class="flex items-center gap-2">
                <a href="{{ route('superadmin.access_approvals', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                <a href="{{ route('superadmin.access_approvals', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('superadmin.access_approvals', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                <form method="post" action="{{ route('superadmin.access_approvals.bulk') }}">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="q" value="{{ $q ?? '' }}">
                    <input type="hidden" name="module" value="{{ $module ?? '' }}">
                    <button
                        class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                        data-swal-title="Approve all visible pending access?"
                        data-swal-confirm="This will approve every currently filtered pending access request."
                        data-swal-confirm-text="Yes, approve all"
                    >Approve all visible</button>
                </form>
                <form method="post" action="{{ route('superadmin.access_approvals.bulk') }}">
                    @csrf
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="q" value="{{ $q ?? '' }}">
                    <input type="hidden" name="module" value="{{ $module ?? '' }}">
                    <button
                        class="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50"
                        data-swal-title="Revoke all visible pending access?"
                        data-swal-confirm="This will revoke every currently filtered pending access request."
                        data-swal-confirm-text="Yes, revoke all"
                    >Revoke all visible</button>
                </form>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Module</th>
                        <th class="px-5 py-3 text-left font-bold">Status</th>
                        <th class="px-5 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ $item->user?->name ?? '—' }}</div>
                                <div class="text-slate-500">{{ $item->user?->email ?? '—' }}</div>
                            </td>
                            <td class="px-5 py-4">{{ strtoupper((string) $item->module) }}</td>
                            <td class="px-5 py-4"><span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">{{ ucfirst((string) $item->status) }}</span></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="approved">
                                        <button class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50">Approve</button>
                                    </form>
                                    <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="revoked">
                                        <button class="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">Revoke</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No pending approvals.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $items->links() }}
        </div>
    @endif
@endsection

