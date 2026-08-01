@extends('layouts.superadmin', ['title' => 'Agent Workspaces — Super Admin'])

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Agent workspaces</h1>
            <p class="mt-1 text-sm text-slate-600">Manage agent footprints, subscriptions, and workspace access from one table.</p>
        </div>
        <a href="{{ route('superadmin.users.create') }}" class="inline-flex items-center justify-center rounded-xl bg-[#2f4f4f] px-5 py-3 text-sm font-bold text-white hover:bg-[#264040]">
            Invite agent
        </a>
    </div>

    <form method="get" data-sa-auto-filter class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
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
        <select name="status" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="" @selected(($statusFilter ?? '') === '')>All statuses</option>
            <option value="active" @selected(($statusFilter ?? '') === 'active')>Active</option>
            <option value="suspended" @selected(($statusFilter ?? '') === 'suspended')>Suspended</option>
            <option value="pending" @selected(($statusFilter ?? '') === 'pending')>Pending</option>
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
    </form>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Export all CSV</a>
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Export all Excel</a>
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Export all PDF</a>
    </div>

    <form id="workspaces-bulk-form" method="post" action="{{ route('superadmin.agent_workspaces.bulk') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        @csrf
        <div id="workspaces-bulk-ids"></div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">Bulk workspace actions</p>
        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
            <div>
                <label for="workspaces-bulk-action" class="block text-xs font-semibold text-slate-600">Action</label>
                <select name="bulk_action" id="workspaces-bulk-action" class="mt-1 w-full min-w-[14rem] rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="export">Export selected (CSV/Excel/PDF)</option>
                    @if ($hasSubscriptions && $packages->isNotEmpty())
                        <option value="change_package">Change subscription package (bulk)</option>
                    @endif
                    <option value="suspend">Suspend selected workspaces</option>
                    <option value="activate">Activate selected workspaces</option>
                </select>
            </div>
            <div id="workspaces-bulk-format-wrap">
                <label for="workspaces-bulk-format" class="block text-xs font-semibold text-slate-600">Format</label>
                <select name="format" id="workspaces-bulk-format" class="mt-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="csv">CSV</option>
                    <option value="xls">Excel</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
            @if ($hasSubscriptions && $packages->isNotEmpty())
                <div id="workspaces-bulk-package-wrap" class="hidden">
                    <label for="workspaces-bulk-package" class="block text-xs font-semibold text-slate-600">Package</label>
                    <select name="subscription_package_id" id="workspaces-bulk-package" class="mt-1 min-w-[12rem] rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($packages as $packageId => $packageName)
                            <option value="{{ $packageId }}">{{ $packageName }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="button" id="workspaces-bulk-apply" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-900">
                Run action
            </button>
        </div>
    </form>

    <div
        x-data="{
            transferOpen: false,
            transferAgentId: null,
            transferAgentName: '',
            openTransfer(id, name) {
                this.transferAgentId = id;
                this.transferAgentName = name;
                this.transferOpen = true;
            },
        }"
        @keydown.escape.window="transferOpen = false"
    >
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="min-w-[960px] w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="w-10 px-3 py-3 text-left font-bold" scope="col">
                                <span class="sr-only">Select</span>
                                <input type="checkbox" id="workspaces-select-page" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" title="Select all on this page" aria-label="Select all agents on this page">
                            </th>
                            <th class="px-5 py-3 text-left font-bold">Agent</th>
                            <th class="px-5 py-3 text-left font-bold">Status</th>
                            <th class="px-5 py-3 text-left font-bold">Subscription</th>
                            <th class="px-5 py-3 text-right font-bold">Properties</th>
                            <th class="px-5 py-3 text-right font-bold">Units</th>
                            <th class="px-5 py-3 text-right font-bold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($agents as $agent)
                            @php
                                $meta = $summaries[(int) $agent->id] ?? [];
                                $status = $meta['status'] ?? ['label' => 'Pending', 'tone' => 'orange', 'key' => 'pending'];
                                $detailUrl = route('superadmin.agent_workspaces.show', $agent);
                            @endphp
                            <tr
                                class="group cursor-pointer transition hover:bg-gray-50/50"
                                data-workspace-row
                                data-href="{{ $detailUrl }}"
                            >
                                <td class="px-3 py-4 align-middle" data-row-ignore-click>
                                    <input type="checkbox" value="{{ $agent->id }}" class="workspaces-row-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" aria-label="Select {{ $agent->name }}">
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900 group-hover:text-[#2f4f4f]">{{ $agent->name }}</div>
                                    <div class="text-slate-500">{{ $agent->email }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    @include('superadmin.console.partials.workspace_status_badge', ['status' => $status])
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-medium text-slate-800">{{ $meta['subscription'] ?? 'No plan' }}</span>
                                </td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ (int) ($propertyCounts[$agent->id] ?? 0) }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">{{ (int) ($unitCounts[$agent->id] ?? 0) }}</td>
                                <td class="px-5 py-4 text-right" data-row-ignore-click>
                                    <div class="relative inline-block text-left" x-data="{ open: false }">
                                        <button
                                            type="button"
                                            @click.stop="open = !open"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                                        >
                                            Actions
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div
                                            x-show="open"
                                            @click.outside="open = false"
                                            x-cloak
                                            class="absolute right-0 z-20 mt-1 w-56 origin-top-right rounded-xl border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-black/5"
                                        >
                                            <form method="post" action="{{ route('superadmin.agent_workspaces.impersonate', $agent) }}">
                                                @csrf
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">View / impersonate dashboard</button>
                                            </form>
                                            <a href="{{ $detailUrl }}" class="block px-4 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">Workspace details</a>
                                            <button type="button" @click="openTransfer({{ $agent->id }}, {{ json_encode($agent->name) }}); open = false" class="block w-full px-4 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">Transfer ownership</button>
                                            <a href="{{ route('superadmin.console.subscriptions', ['q' => $agent->email]) }}" class="block px-4 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">Manage subscription</a>
                                            @if (($status['key'] ?? '') === 'suspended')
                                                <form method="post" action="{{ route('superadmin.agent_workspaces.toggle_status', $agent) }}">
                                                    @csrf
                                                    <input type="hidden" name="intent" value="activate">
                                                    <button type="submit" class="block w-full px-4 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Activate workspace</button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('superadmin.agent_workspaces.toggle_status', $agent) }}" data-swal-confirm="Suspend this agent workspace? They will lose property module access.">
                                                    @csrf
                                                    <input type="hidden" name="intent" value="suspend">
                                                    <button type="submit" class="block w-full px-4 py-2 text-left text-xs font-semibold text-rose-700 hover:bg-rose-50">Suspend workspace</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-16">
                                    <div class="mx-auto max-w-md text-center">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#eef5f3] text-[#2f4f4f]">
                                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1"/></svg>
                                        </div>
                                        <h3 class="mt-4 text-base font-bold text-slate-900">No agent workspaces found</h3>
                                        <p class="mt-2 text-sm text-slate-500">
                                            @if (($q ?? '') !== '' || ($workspace ?? 'all') !== 'all' || ($statusFilter ?? '') !== '')
                                                Try clearing your filters or invite a new agent account.
                                            @else
                                                Get started by inviting your first property agent.
                                            @endif
                                        </p>
                                        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                                            @if (($q ?? '') !== '' || ($workspace ?? 'all') !== 'all' || ($statusFilter ?? '') !== '')
                                                <a href="{{ route('superadmin.agent_workspaces') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear filters</a>
                                            @endif
                                            <a href="{{ route('superadmin.users.create') }}" class="rounded-xl bg-[#2f4f4f] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#264040]">Invite agent</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="transferOpen" x-cloak class="fixed inset-0 z-[7000] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60" @click="transferOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-slate-900">Transfer ownership</h3>
                <p class="mt-2 text-sm text-slate-600">Move all properties, units, and scoped records from <span class="font-semibold text-slate-900" x-text="transferAgentName"></span> to another agent.</p>
                <form method="post" :action="`{{ url('/superadmin/agent-workspaces') }}/${transferAgentId}/transfer`" class="mt-5 space-y-4" data-swal-confirm="Transfer all scoped records to the selected agent?">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Receiving agent</label>
                        <select name="target_agent_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select agent…</option>
                            @foreach ($otherAgents as $peer)
                                <option value="{{ $peer->id }}">{{ $peer->name }} ({{ $peer->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="transferOpen = false" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-xl bg-[#2f4f4f] px-4 py-2 text-sm font-bold text-white hover:bg-[#264040]">Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-6">
        {{ $agents->links() }}
    </div>

    <script>
        (function () {
            const form = document.getElementById('workspaces-bulk-form');
            const idsWrap = document.getElementById('workspaces-bulk-ids');
            const master = document.getElementById('workspaces-select-page');
            const applyBtn = document.getElementById('workspaces-bulk-apply');
            const actionSelect = document.getElementById('workspaces-bulk-action');
            const formatWrap = document.getElementById('workspaces-bulk-format-wrap');
            const packageWrap = document.getElementById('workspaces-bulk-package-wrap');
            if (!form || !idsWrap || !master || !applyBtn || !actionSelect) return;

            function rowCheckboxes() {
                return document.querySelectorAll('.workspaces-row-cb');
            }

            function syncBulkFields() {
                const action = actionSelect.value;
                if (formatWrap) formatWrap.classList.toggle('hidden', action !== 'export');
                if (packageWrap) packageWrap.classList.toggle('hidden', action !== 'change_package');
            }

            actionSelect.addEventListener('change', syncBulkFields);
            syncBulkFields();

            master.addEventListener('change', function () {
                master.indeterminate = false;
                rowCheckboxes().forEach(function (cb) { cb.checked = master.checked; });
            });

            rowCheckboxes().forEach(function (cb) {
                cb.addEventListener('change', function () {
                    const all = Array.from(rowCheckboxes());
                    master.checked = all.length > 0 && all.every(function (x) { return x.checked; });
                    master.indeterminate = all.some(function (x) { return x.checked; }) && !master.checked;
                });
            });

            document.querySelectorAll('[data-workspace-row]').forEach(function (row) {
                row.addEventListener('click', function (event) {
                    if (event.target.closest('[data-row-ignore-click]')) return;
                    const href = row.getAttribute('data-href');
                    if (href) window.location.href = href;
                });
            });

            applyBtn.addEventListener('click', async function () {
                const checked = Array.from(rowCheckboxes()).filter(function (cb) { return cb.checked; });
                if (checked.length === 0) {
                    await window.swalAlert('Select at least one agent workspace.', {
                        icon: 'info',
                        title: 'No rows selected',
                    });
                    return;
                }

                const action = actionSelect.value;
                if (action === 'suspend' || action === 'activate') {
                    const confirmed = await window.swalConfirm('Apply this action to ' + checked.length + ' selected workspace(s)?');
                    if (!confirmed) {
                        return;
                    }
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
