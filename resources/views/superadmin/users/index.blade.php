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

    <form method="get" data-sa-auto-filter class="mb-6">
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
                <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                <a href="{{ route('superadmin.users.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </div>
    </form>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('superadmin.users.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
        <a href="{{ route('superadmin.users.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
        <a href="{{ route('superadmin.users.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
    </div>

    <form id="users-bulk-form" method="post" action="{{ route('superadmin.users.bulk') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <div id="users-bulk-ids"></div>
        <p class="text-xs font-semibold text-slate-600 mb-3">Bulk actions — selected rows on this page</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div>
                <label for="users-bulk-kind" class="block text-xs font-semibold text-slate-600">Action</label>
                <select name="bulk_kind" id="users-bulk-kind" class="mt-1 w-full min-w-[14rem] rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="property_role">Set property portal role</option>
                    @if ($hasModuleAccessTable ?? false)
                        <option value="module_property">Set Property module access</option>
                        <option value="module_loan">Set Loan module access</option>
                    @endif
                </select>
            </div>
            <div>
                <label for="users-bulk-value" class="block text-xs font-semibold text-slate-600">Value</label>
                <select name="bulk_value" id="users-bulk-value" class="mt-1 w-full min-w-[12rem] rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></select>
            </div>
            <button type="button" id="users-bulk-apply" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                Apply to selected
            </button>
        </div>
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto overscroll-x-contain">
            <table class="min-w-[640px] w-full text-sm whitespace-nowrap lg:whitespace-normal">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="w-10 px-3 py-3 text-left font-bold" scope="col">
                        <span class="sr-only">Select</span>
                        <input type="checkbox" id="users-select-page" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" title="Select all on this page" aria-label="Select all users on this page">
                    </th>
                    <th class="px-5 py-3 text-left font-bold">User</th>
                    <th class="px-5 py-3 text-left font-bold">Flags</th>
                    <th class="px-5 py-3 text-right font-bold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $u)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-3 py-4 align-middle">
                            <input type="checkbox" value="{{ $u->id }}" class="users-row-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" aria-label="Select {{ $u->name }}">
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ $u->name }}</div>
                            <div class="text-slate-500">{{ $u->email }}</div>
                        </td>
                        <td class="px-5 py-4">
                            @php($loanAccessRow = $u->relationLoaded('moduleAccesses') ? $u->moduleAccesses->firstWhere('module', 'loan') : null)
                            <div class="flex flex-wrap gap-2">
                                @if ($u->is_super_admin)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Super Admin</span>
                                @endif
                                @if ($u->property_portal_role)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-800">Property: {{ $u->property_portal_role }}</span>
                                @endif
                                @if (filled($u->loan_role))
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">Loan: {{ ($loanRoleLabels ?? [])[strtolower((string) $u->loan_role)] ?? str_replace('_', ' ', (string) $u->loan_role) }}</span>
                                @elseif ($loanAccessRow)
                                    <span class="inline-flex items-center rounded-full bg-teal-100 px-3 py-1 text-xs font-bold text-teal-900">Loan module: {{ ucfirst((string) $loanAccessRow->status) }}</span>
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
                        <td colspan="4" class="px-5 py-10 text-center text-slate-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

    <script>
        (function () {
            const kindEl = document.getElementById('users-bulk-kind');
            const valueEl = document.getElementById('users-bulk-value');
            const form = document.getElementById('users-bulk-form');
            const idsWrap = document.getElementById('users-bulk-ids');
            const master = document.getElementById('users-select-page');
            const applyBtn = document.getElementById('users-bulk-apply');
            if (!kindEl || !valueEl || !form || !idsWrap || !master || !applyBtn) return;

            const optionsByKind = {
                property_role: [
                    { v: 'agent', t: 'Agent' },
                    { v: 'landlord', t: 'Landlord' },
                    { v: 'tenant', t: 'Tenant' },
                    { v: 'none', t: 'None (clear)' },
                ],
                module_property: [
                    { v: 'approved', t: 'Approved' },
                    { v: 'pending', t: 'Pending' },
                    { v: 'revoked', t: 'Revoked' },
                ],
                module_loan: [
                    { v: 'approved', t: 'Approved' },
                    { v: 'pending', t: 'Pending' },
                    { v: 'revoked', t: 'Revoked' },
                ],
            };

            function rowCheckboxes() {
                return document.querySelectorAll('.users-row-cb');
            }

            function refillValues() {
                const kind = kindEl.value;
                const list = optionsByKind[kind] || [];
                valueEl.innerHTML = '';
                list.forEach(function (o) {
                    const opt = document.createElement('option');
                    opt.value = o.v;
                    opt.textContent = o.t;
                    valueEl.appendChild(opt);
                });
            }

            kindEl.addEventListener('change', refillValues);
            refillValues();

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
                        window.Swal.fire({ icon: 'info', title: 'No rows selected', text: 'Select at least one user using the checkboxes.' });
                    } else {
                        window.alert('Select at least one user using the checkboxes.');
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
                const msg = 'Apply “' + kindEl.options[kindEl.selectedIndex].text + '” → “' + valueEl.options[valueEl.selectedIndex].text + '” to ' + checked.length + ' user(s)?';
                function doSubmit() {
                    HTMLFormElement.prototype.submit.call(form);
                }
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Confirm bulk update',
                        text: msg,
                        showCancelButton: true,
                        confirmButtonText: 'Yes, apply',
                        cancelButtonText: 'Cancel',
                    }).then(function (res) {
                        if (res.isConfirmed) doSubmit();
                    });
                } else if (window.confirm(msg)) {
                    doSubmit();
                }
            });
        })();
    </script>
@endsection

