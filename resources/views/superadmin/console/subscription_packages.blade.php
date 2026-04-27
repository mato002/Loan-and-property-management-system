@php($title = 'Subscription Packages — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Subscription Packages</h1>
        <p class="mt-1 text-sm text-slate-600">Manage subscription packages for agents based on unit capacity.</p>
    </div>

    @if (!$tablesReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-amber-100 p-2 rounded-lg">
                    <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-amber-900">Database Tables Not Ready</h3>
            </div>
            <p class="text-amber-700">The subscription packages database tables have not been created yet. Please run the database migrations first.</p>
        </div>
    @else
        {{-- Add Package Form --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm mb-8">
            <h2 class="text-lg font-bold tracking-tight text-slate-900 mb-4">Add New Package</h2>
            <form action="{{ route('superadmin.console.packages.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Package Name</label>
                        <input type="text" name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Min Units</label>
                        <input type="number" name="min_units" required min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Max Units (optional)</label>
                        <input type="number" name="max_units" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Monthly Price (KSH)</label>
                        <input type="number" name="monthly_price_ksh" required min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Annual Price (KSH)</label>
                        <input type="number" name="annual_price_ksh" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-slate-700">Active</span>
                    </label>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        Add Package
                    </button>
                </div>
            </form>
        </div>

        {{-- Packages List --}}
        <div class="rounded-2xl bg-white shadow-sm">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Current Packages</h2>
            </div>
            @if ($packages->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900">No packages found</h3>
                    <p class="mt-1 text-sm text-slate-500">Get started by creating your first subscription package.</p>
                </div>
            @else
                <form id="packages-bulk-form" method="post" action="{{ route('superadmin.console.packages.bulk') }}" class="border-b border-slate-200 bg-slate-50/60 px-6 py-4">
                    @csrf
                    <div id="packages-bulk-ids"></div>
                    <p class="text-xs font-semibold text-slate-600 mb-3">Bulk actions — selected rows on this page</p>
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div>
                            <label for="packages-bulk-action" class="block text-xs font-medium text-slate-600">Action</label>
                            <select name="bulk_action" id="packages-bulk-action" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="set_active">Set active / inactive</option>
                                <option value="delete">Delete</option>
                            </select>
                        </div>
                        <div id="packages-bulk-active-wrap">
                            <label for="packages-bulk-is-active" class="block text-xs font-medium text-slate-600">Active</label>
                            <select name="is_active" id="packages-bulk-is-active" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="1">Yes (active)</option>
                                <option value="0">No (inactive)</option>
                            </select>
                        </div>
                        <button type="button" id="packages-bulk-apply" class="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-900">
                            Apply to selected
                        </button>
                    </div>
                </form>
                <div class="overflow-x-auto overscroll-x-contain">
                    <table class="min-w-[720px] w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="w-10 px-3 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider" scope="col">
                                    <span class="sr-only">Select</span>
                                    <input type="checkbox" id="packages-select-page" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" title="Select all on this page" aria-label="Select all packages on this page">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Package</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Unit Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Monthly Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Annual Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach ($packages as $package)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-4 align-middle">
                                        <input type="checkbox" value="{{ $package->id }}" class="packages-row-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" aria-label="Select package {{ $package->name }}">
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900">{{ $package->name }}</div>
                                            @if ($package->description)
                                                <div class="text-sm text-slate-500">{{ Str::limit($package->description, 80) }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900">
                                        {{ $package->unit_range }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">
                                        {{ $package->formatted_monthly_price }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900">
                                        {{ $package->formatted_annual_price ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($package->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <form action="{{ route('superadmin.console.packages.delete', $package) }}" method="POST" class="inline" data-swal-confirm="Are you sure you want to delete this package?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <script>
                    (function () {
                        const form = document.getElementById('packages-bulk-form');
                        const idsWrap = document.getElementById('packages-bulk-ids');
                        const actionEl = document.getElementById('packages-bulk-action');
                        const activeWrap = document.getElementById('packages-bulk-active-wrap');
                        const activeSelect = document.getElementById('packages-bulk-is-active');
                        const master = document.getElementById('packages-select-page');
                        const applyBtn = document.getElementById('packages-bulk-apply');
                        if (!form || !idsWrap || !actionEl || !activeWrap || !master || !applyBtn) return;

                        function rowCheckboxes() {
                            return document.querySelectorAll('.packages-row-cb');
                        }

                        function syncActiveVisibility() {
                            const del = actionEl.value === 'delete';
                            activeWrap.classList.toggle('hidden', del);
                            if (activeSelect) {
                                activeSelect.disabled = del;
                                if (del) {
                                    activeSelect.removeAttribute('name');
                                } else {
                                    activeSelect.setAttribute('name', 'is_active');
                                }
                            }
                        }

                        actionEl.addEventListener('change', syncActiveVisibility);
                        syncActiveVisibility();

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
                                    window.Swal.fire({ icon: 'info', title: 'No rows selected', text: 'Select at least one package.' });
                                } else {
                                    window.alert('Select at least one package.');
                                }
                                return;
                            }
                            const isDelete = actionEl.value === 'delete';
                            const msg = isDelete
                                ? 'Delete ' + checked.length + ' package(s)? In-use packages will be skipped.'
                                : 'Update active flag for ' + checked.length + ' package(s)?';
                            idsWrap.innerHTML = '';
                            checked.forEach(function (cb) {
                                const h = document.createElement('input');
                                h.type = 'hidden';
                                h.name = 'ids[]';
                                h.value = cb.value;
                                idsWrap.appendChild(h);
                            });
                            function doSubmit() {
                                HTMLFormElement.prototype.submit.call(form);
                            }
                            if (window.Swal && typeof window.Swal.fire === 'function') {
                                window.Swal.fire({
                                    icon: isDelete ? 'warning' : 'question',
                                    title: isDelete ? 'Confirm delete' : 'Confirm update',
                                    text: msg,
                                    showCancelButton: true,
                                    confirmButtonText: isDelete ? 'Yes, delete' : 'Yes, apply',
                                    cancelButtonText: 'Cancel',
                                }).then(function (res) {
                                    if (res.isConfirmed) doSubmit();
                                });
                            } else {
                                doSubmit();
                            }
                        });
                    })();
                </script>
            @endif
        </div>
    @endif
@endsection
