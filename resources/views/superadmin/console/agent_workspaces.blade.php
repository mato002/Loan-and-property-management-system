@php($title = 'Agent Workspaces — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Agent workspaces</h1>
        <p class="mt-1 text-sm text-slate-600">Ownership footprint per agent account.</p>
    </div>

    {{-- SEARCH FORM --}}
    <form method="get" data-sa-auto-filter class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
        <input
            type="text"
            name="q"
            value="{{ $q ?? '' }}"
            placeholder="Search agent by name/email..."
            class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 lg:col-span-2"
        />
        <select name="workspace" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all" @selected(($workspace ?? 'all') === 'all')>All workspaces</option>
            <option value="active" @selected(($workspace ?? 'all') === 'active')>Active workspaces</option>
            <option value="empty" @selected(($workspace ?? 'all') === 'empty')>Empty workspaces</option>
        </select>
        <select name="per_page" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ([10, 25, 50, 100, 200] as $size)
                <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }} / page</option>
            @endforeach
        </select> 
        <div class="flex items-center gap-2">
            <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
            <a href="{{ route('superadmin.agent_workspaces') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </form> {{-- FIXED: Changed from </div> to </form> --}}

    {{-- EXPORT BUTTONS --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
    </div>

    <form id="workspaces-export-form" method="post" action="{{ route('superadmin.agent_workspaces.export_selected') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <div id="workspaces-export-ids"></div>
        <p class="text-xs font-semibold text-slate-600 mb-3">Bulk export — selected agents on this page</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div>
                <label for="workspaces-export-format" class="block text-xs font-semibold text-slate-600">Format</label>
                <select name="format" id="workspaces-export-format" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="csv">CSV</option>
                    <option value="xls">Excel</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
            <button type="button" id="workspaces-export-apply" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-900">
                Export selected
            </button>
        </div>
    </form>

    {{-- DATA TABLE --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto overscroll-x-contain">
        <table class="min-w-[520px] w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="w-10 px-3 py-3 text-left font-bold" scope="col">
                        <span class="sr-only">Select</span>
                        <input type="checkbox" id="workspaces-select-page" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" title="Select all on this page" aria-label="Select all agents on this page">
                    </th>
                    <th class="px-5 py-3 text-left font-bold">Agent</th>
                    <th class="px-5 py-3 text-right font-bold">Properties</th>
                    <th class="px-5 py-3 text-right font-bold">Units</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($agents as $agent)
                    <tr>
                        <td class="px-3 py-4 align-middle">
                            <input type="checkbox" value="{{ $agent->id }}" class="workspaces-row-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" aria-label="Select {{ $agent->name }}">
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ $agent->name }}</div>
                            <div class="text-slate-500">{{ $agent->email }}</div>
                        </td>
                        <td class="px-5 py-4 text-right">{{ (int) ($propertyCounts[$agent->id] ?? 0) }}</td>
                        <td class="px-5 py-4 text-right">{{ (int) ($unitCounts[$agent->id] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No agents found.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $agents->links() }}
    </div>

    <script>
        (function () {
            const form = document.getElementById('workspaces-export-form');
            const idsWrap = document.getElementById('workspaces-export-ids');
            const master = document.getElementById('workspaces-select-page');
            const applyBtn = document.getElementById('workspaces-export-apply');
            if (!form || !idsWrap || !master || !applyBtn) return;

            function rowCheckboxes() {
                return document.querySelectorAll('.workspaces-row-cb');
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
                        window.Swal.fire({ icon: 'info', title: 'No rows selected', text: 'Select at least one agent.' });
                    } else {
                        window.alert('Select at least one agent.');
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
@endsection