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
        <form method="get" data-sa-auto-filter class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
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
                <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                <a href="{{ route('superadmin.audit_trail') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
            <a href="{{ route('superadmin.audit_trail', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
            <a href="{{ route('superadmin.audit_trail', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
            <a href="{{ route('superadmin.audit_trail', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
        </div>

        <form id="audit-export-form" method="post" action="{{ route('superadmin.audit_trail.export_selected') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            @csrf
            <div id="audit-export-ids"></div>
            <p class="text-xs font-semibold text-slate-600 mb-3">Bulk export — selected rows on this page</p>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div>
                    <label for="audit-export-format" class="block text-xs font-semibold text-slate-600">Format</label>
                    <select name="format" id="audit-export-format" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="csv">CSV</option>
                        <option value="xls">Excel</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
                <button type="button" id="audit-export-apply" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-900">
                    Export selected
                </button>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto overscroll-x-contain">
            <table class="min-w-[720px] w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="w-10 px-3 py-3 text-left font-bold" scope="col">
                            <span class="sr-only">Select</span>
                            <input type="checkbox" id="audit-select-page" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" title="Select all on this page" aria-label="Select all rows on this page">
                        </th>
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
                            <td class="px-3 py-3 align-middle">
                                <input type="checkbox" value="{{ $item->id }}" class="audit-row-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" aria-label="Select audit row {{ $item->id }}">
                            </td>
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
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No actions found.</td></tr>
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
                const form = document.getElementById('audit-export-form');
                const idsWrap = document.getElementById('audit-export-ids');
                const master = document.getElementById('audit-select-page');
                const applyBtn = document.getElementById('audit-export-apply');
                if (!form || !idsWrap || !master || !applyBtn) return;

                function rowCheckboxes() {
                    return document.querySelectorAll('.audit-row-cb');
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
                        master.checked = all.length > 0 && all.every(function (x) { return x.checked; });
                        master.indeterminate = all.some(function (x) { return x.checked; }) && !master.checked;
                    });
                });

                applyBtn.addEventListener('click', function () {
                    const checked = Array.from(rowCheckboxes()).filter(function (cb) { return cb.checked; });
                    if (checked.length === 0) {
                        if (window.Swal && typeof window.Swal.fire === 'function') {
                            window.Swal.fire({ icon: 'info', title: 'No rows selected', text: 'Select at least one audit row.' });
                        } else {
                            window.alert('Select at least one audit row.');
                        }
                        return;
                    }
                    idsWrap.innerHTML = '';
                    checked.forEach(function (cb) {
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = 'ids[]';
                        h.value = cb.value;
                        idsWrap.appendChild(h);
                    });
                    HTMLFormElement.prototype.submit.call(form);
                });
            })();
        </script>
    @endif
@endsection

