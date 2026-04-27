@php($title = 'Access Approvals — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Access approvals</h1>
        <p class="mt-1 text-sm text-slate-600">All module access records stay listed here so you can approve, revoke, or set back to pending at any time.</p>
    </div>

    @if (! $tablesReady)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">`user_module_accesses` table is not available yet.</div>
    @else
        <div class="mb-4 flex flex-col gap-3">
            <form method="get" data-sa-auto-filter class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                <input
                    type="text"
                    name="q"
                    value="{{ $q ?? '' }}"
                    placeholder="Search by user name or email..."
                    class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:col-span-2"
                />
                <select name="module" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All modules</option>
                    <option value="property" @selected(($module ?? '') === 'property')>Property</option>
                    <option value="loan" @selected(($module ?? '') === 'loan')>Loan</option>
                </select>
                <select name="status" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="" @selected(($status ?? '') === '')>All statuses</option>
                    <option value="pending" @selected(($status ?? '') === 'pending')>Pending</option>
                    <option value="approved" @selected(($status ?? '') === 'approved')>Approved</option>
                    <option value="revoked" @selected(($status ?? '') === 'revoked')>Revoked</option>
                </select>
                <select name="per_page" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ([10, 25, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }} / page</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2 lg:col-span-1">
                    <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                    <a href="{{ route('superadmin.access_approvals') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('superadmin.access_approvals', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('superadmin.access_approvals', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('superadmin.access_approvals', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="post" action="{{ route('superadmin.access_approvals.bulk') }}">
                        @csrf
                        <input type="hidden" name="bulk_mode" value="filter">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="q" value="{{ $q ?? '' }}">
                        <input type="hidden" name="module" value="{{ $module ?? '' }}">
                        <button
                            type="submit"
                            class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                            data-swal-title="Approve all matching pending requests?"
                            data-swal-confirm="Only rows that are still pending and match your search/module filters will be approved."
                            data-swal-confirm-text="Yes, approve all"
                        >Approve all pending (filtered)</button>
                    </form>
                    <form method="post" action="{{ route('superadmin.access_approvals.bulk') }}">
                        @csrf
                        <input type="hidden" name="bulk_mode" value="filter">
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="q" value="{{ $q ?? '' }}">
                        <input type="hidden" name="module" value="{{ $module ?? '' }}">
                        <button
                            type="submit"
                            class="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50"
                            data-swal-title="Revoke all matching pending requests?"
                            data-swal-confirm="Only rows that are still pending and match your search/module filters will be revoked."
                            data-swal-confirm-text="Yes, revoke all"
                        >Revoke all pending (filtered)</button>
                    </form>
                </div>
            </div>
            <p class="text-xs text-slate-500">“All pending (filtered)” affects every <span class="font-semibold text-slate-700">pending</span> row matching search/module (not status filter). Use checkboxes for actions on <span class="font-semibold text-slate-700">this page only</span>.</p>
        </div>

        <form id="access-approvals-selected-bulk" method="post" action="{{ route('superadmin.access_approvals.bulk') }}" class="hidden" aria-hidden="true">
            @csrf
            <input type="hidden" name="bulk_mode" value="selected">
            <input type="hidden" name="action" id="access-approvals-selected-action" value="">
            <div id="access-approvals-selected-ids"></div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/90 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <p class="text-xs font-semibold text-slate-600">Bulk actions — selected rows</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="access-approvals-bulk-selected rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                        data-bulk-action="approve"
                        data-swal-title="Approve selected?"
                        data-swal-text="Eligible rows (pending or revoked on this page) will be approved."
                    >Approve selected</button>
                    <button
                        type="button"
                        class="access-approvals-bulk-selected rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                        data-bulk-action="revoke"
                        data-swal-title="Revoke selected?"
                        data-swal-text="Eligible rows (pending or approved on this page) will be revoked."
                    >Revoke selected</button>
                    <button
                        type="button"
                        class="access-approvals-bulk-selected rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        data-bulk-action="pending"
                        data-swal-title="Set selected to pending?"
                        data-swal-text="Eligible rows (approved or revoked on this page) will return to pending."
                    >Set pending</button>
                </div>
            </div>
            <div class="overflow-x-auto overscroll-x-contain">
            <table class="min-w-[720px] w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="w-10 px-3 py-3 text-left font-bold" scope="col">
                            <span class="sr-only">Select</span>
                            <input type="checkbox" id="access-approvals-select-page" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" title="Select all on this page" aria-label="Select all rows on this page">
                        </th>
                        <th class="px-5 py-3 text-left font-bold">User</th>
                        <th class="px-5 py-3 text-left font-bold">Module</th>
                        <th class="px-5 py-3 text-left font-bold">Status</th>
                        <th class="px-5 py-3 text-left font-bold">Requested</th>
                        <th class="px-5 py-3 text-right font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr class="align-top">
                            <td class="px-3 py-4 align-middle">
                                <input type="checkbox" value="{{ $item->id }}" class="access-approvals-row-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" aria-label="Select row {{ $item->id }}">
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ $item->user?->name ?? '—' }}</div>
                                <div class="text-slate-500">{{ $item->user?->email ?? '—' }}</div>
                            </td>
                            <td class="px-5 py-4">{{ strtoupper((string) $item->module) }}</td>
                            <td class="px-5 py-4">
                                @if ($item->normalized_status === 'pending')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">Pending</span>
                                @elseif ($item->normalized_status === 'approved')
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">Approved</span>
                                    @if ($item->approved_at)
                                        <div class="mt-1 text-xs text-slate-500">{{ $item->approved_at->format('M j, Y g:i a') }}</div>
                                    @endif
                                @elseif ($item->normalized_status === 'revoked')
                                    <span class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-800">Revoked</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-900">{{ $item->status ?: 'Unknown' }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                                {{ $item->created_at?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if (! in_array($item->normalized_status, ['pending', 'approved', 'revoked'], true))
                                        <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50">Approve</button>
                                        </form>
                                        <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="revoked">
                                            <button type="submit" class="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">Revoke</button>
                                        </form>
                                        <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="pending">
                                            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Set pending</button>
                                        </form>
                                    @else
                                        @if (in_array($item->normalized_status, ['pending', 'revoked'], true))
                                            <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50">Approve</button>
                                            </form>
                                        @endif
                                        @if (in_array($item->normalized_status, ['pending', 'approved'], true))
                                            <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="revoked">
                                                <button type="submit" class="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">Revoke</button>
                                            </form>
                                        @endif
                                        @if (in_array($item->normalized_status, ['approved', 'revoked'], true))
                                            <form method="post" action="{{ route('superadmin.access_approvals.update', $item) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="pending">
                                                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Set pending</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No access records match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="mt-6">
            {{ $items->links() }}
        </div>

        <script>
            (function () {
                const master = document.getElementById('access-approvals-select-page');
                const form = document.getElementById('access-approvals-selected-bulk');
                const actionInput = document.getElementById('access-approvals-selected-action');
                const idsWrap = document.getElementById('access-approvals-selected-ids');
                if (!master || !form || !actionInput || !idsWrap) return;

                function rowCheckboxes() {
                    return document.querySelectorAll('.access-approvals-row-cb');
                }

                master.addEventListener('change', function () {
                    master.indeterminate = false;
                    rowCheckboxes().forEach(function (cb) {
                        cb.checked = master.checked;
                    });
                });

                rowCheckboxes().forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        const all = Array.from(rowCheckboxes());
                        master.checked = all.length > 0 && all.every(function (x) {
                            return x.checked;
                        });
                        master.indeterminate = all.some(function (x) {
                            return x.checked;
                        }) && !master.checked;
                    });
                });

                document.querySelectorAll('.access-approvals-bulk-selected').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const checked = Array.from(rowCheckboxes()).filter(function (cb) {
                            return cb.checked;
                        });
                        if (checked.length === 0) {
                            if (window.Swal && typeof window.Swal.fire === 'function') {
                                window.Swal.fire({ icon: 'info', title: 'No rows selected', text: 'Select at least one row using the checkboxes.' });
                            } else {
                                window.alert('Select at least one row using the checkboxes.');
                            }
                            return;
                        }

                        const bulkAction = btn.getAttribute('data-bulk-action') || '';
                        const title = btn.getAttribute('data-swal-title') || 'Confirm';
                        const text = btn.getAttribute('data-swal-text') || '';

                        idsWrap.innerHTML = '';
                        checked.forEach(function (cb) {
                            const h = document.createElement('input');
                            h.type = 'hidden';
                            h.name = 'ids[]';
                            h.value = cb.value;
                            idsWrap.appendChild(h);
                        });
                        actionInput.value = bulkAction;

                        function doSubmit() {
                            form.submit();
                        }

                        if (window.Swal && typeof window.Swal.fire === 'function') {
                            window.Swal.fire({
                                icon: 'warning',
                                title: title,
                                text: text,
                                showCancelButton: true,
                                confirmButtonText: 'Yes, apply',
                                cancelButtonText: 'Cancel',
                            }).then(function (res) {
                                if (res.isConfirmed) doSubmit();
                            });
                        } else {
                            doSubmit();
                        }
                    });
                });
            })();
        </script>
    @endif
@endsection
