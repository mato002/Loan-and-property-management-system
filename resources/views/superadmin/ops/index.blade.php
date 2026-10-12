@php($title = (($tab ?? '') === 'maintenance' ? 'Maintenance center' : 'Operations console').' — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">{{ $tab === 'maintenance' ? 'System maintenance' : 'Operations console' }}</h1>
        <p class="mt-1 text-sm text-slate-600 max-w-3xl">
            {{ $tab === 'maintenance' ? 'Central reference for the artisan commands used to repair, reconcile, deploy, and monitor the system.' : 'Super-admin only tools for server operations: landlord/agent visibility, command reference, and quick repairs.' }}
        </p>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        <a
            href="{{ route('superadmin.ops.index', ['tab' => 'landlord-scope']) }}"
            class="inline-flex rounded-xl px-4 py-2 text-sm font-bold transition {{ $tab === 'landlord-scope' ? 'bg-emerald-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}"
        >
            Landlord scope
        </a>
        <a
            href="{{ route('superadmin.ops.index', ['tab' => 'commands']) }}"
            class="inline-flex rounded-xl px-4 py-2 text-sm font-bold transition {{ $tab === 'commands' ? 'bg-emerald-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}"
        >
            Command reference
        </a>
        <a
            href="{{ route('superadmin.ops.index', ['tab' => 'maintenance']) }}"
            class="inline-flex rounded-xl px-4 py-2 text-sm font-bold transition {{ $tab === 'maintenance' ? 'bg-emerald-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}"
        >
            Maintenance
        </a>
    </div>

    @if (in_array($tab, ['commands', 'maintenance'], true))
        @if ($tab === 'maintenance')
            <div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <section class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-emerald-800">Common repair · payment allocations</p>
                    <h2 class="mt-1 text-lg font-black text-slate-900">Rebuild tenant receipt allocations</h2>
                    <p class="mt-2 text-sm text-slate-700">Reassign completed tenant payments to open invoices oldest-due-first, then sync invoice paid totals. This can move existing allocations, so confirm the database backup and run it from the production app directory.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <code class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm font-bold text-slate-900">php artisan payments:repair-allocations --limit=10000</code>
                        <button type="button" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800" data-copy-command="php artisan payments:repair-allocations --limit=10000">Copy command</button>
                    </div>
                    <p class="mt-2 text-xs text-slate-600">The limit is the maximum number of tenants processed. Raise it if your portfolio has more.</p>
                </section>
                <section class="rounded-2xl border border-sky-200 bg-sky-50/70 p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-sky-800">Invoice totals · allocation drift</p>
                    <h2 class="mt-1 text-lg font-black text-slate-900">Recalculate paid/status fields</h2>
                    <p class="mt-2 text-sm text-slate-700">Use this when payment allocation rows are already correct but stored invoice totals or statuses do not match them. It does not rebuild or move receipts between invoices.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <code class="rounded-lg border border-sky-200 bg-white px-3 py-2 text-sm font-bold text-slate-900">php artisan finance:detect-allocation-drift --fix</code>
                        <button type="button" class="rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white hover:bg-sky-800" data-copy-command="php artisan finance:detect-allocation-drift --fix">Copy command</button>
                    </div>
                </section>
            </div>
        @endif
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm prose prose-slate max-w-none">
            {!! $commandReferenceHtml !!}
        </div>
        @if ($tab === 'maintenance')
            <script>
                document.querySelectorAll('[data-copy-command]').forEach((button) => {
                    button.addEventListener('click', async () => {
                        await navigator.clipboard.writeText(button.dataset.copyCommand || '');
                        const original = button.textContent;
                        button.textContent = 'Copied';
                        window.setTimeout(() => { button.textContent = original; }, 1400);
                    });
                });
            </script>
        @endif
    @else
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-1 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Agents</h2>
                    <form method="get" action="{{ route('superadmin.ops.index') }}" class="mt-3 space-y-3">
                        <input type="hidden" name="tab" value="landlord-scope">
                        <label class="block text-xs font-semibold text-slate-600">View landlords for agent</label>
                        <select name="agent_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" onchange="this.form.submit()">
                            <option value="">Select agent…</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($selectedAgentId === (int) $agent->id)>
                                    #{{ $agent->id }} — {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
                    <h2 class="text-sm font-black uppercase tracking-wide text-amber-800">Admin-only landlords</h2>
                    <p class="mt-1 text-xs text-amber-900/80">Not visible to any agent until assigned or linked to a property.</p>
                    <p class="mt-2 text-2xl font-black text-amber-950">{{ $orphans->count() }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Inspect landlord</h2>
                    <form method="get" action="{{ route('superadmin.ops.index') }}" class="mt-3 flex gap-2">
                        <input type="hidden" name="tab" value="landlord-scope">
                        @if ($selectedAgentId > 0)
                            <input type="hidden" name="agent_id" value="{{ $selectedAgentId }}">
                        @endif
                        <input
                            type="number"
                            name="inspect_landlord_id"
                            value="{{ $inspectLandlord?->id }}"
                            placeholder="Landlord user ID"
                            class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            min="1"
                        >
                        <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-bold text-white hover:bg-slate-900">Inspect</button>
                    </form>
                </div>
            </div>

            <div class="xl:col-span-2 space-y-6">
                @if ($inspectLandlord)
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-black text-slate-900">#{{ $inspectLandlord->id }} — {{ $inspectLandlord->name }}</h2>
                                <p class="text-sm text-slate-600">{{ $inspectLandlord->email ?: $inspectLandlord->phone ?: 'No contact' }}</p>
                            </div>
                        </div>

                        @if ($inspectReasons === [])
                            <p class="mt-4 text-sm font-semibold text-emerald-800">Super-admin only — no agent visibility paths.</p>
                        @else
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                            <th class="py-2 pr-4">Reason</th>
                                            <th class="py-2">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-indigo-100">
                                        @foreach ($inspectReasons as $row)
                                            <tr>
                                                <td class="py-2 pr-4 font-semibold text-slate-800">{{ $row['reason'] }}</td>
                                                <td class="py-2 text-slate-700">{{ $row['detail'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <form method="post" action="{{ route('superadmin.ops.landlord_scope.assign') }}" class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                                @csrf
                                <input type="hidden" name="landlord_id" value="{{ $inspectLandlord->id }}">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Assign to agent</p>
                                <select name="agent_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">Select agent…</option>
                                    @foreach ($agents as $agent)
                                        <option value="{{ $agent->id }}">#{{ $agent->id }} — {{ $agent->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-700">Assign</button>
                            </form>

                            <form method="post" action="{{ route('superadmin.ops.landlord_scope.release') }}" class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                                @csrf
                                <input type="hidden" name="landlord_id" value="{{ $inspectLandlord->id }}">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Remove from agent</p>
                                <select name="from_agent_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">All agents (admin-only)</option>
                                    @foreach ($agents as $agent)
                                        <option value="{{ $agent->id }}">Only agent #{{ $agent->id }} — {{ $agent->name }}</option>
                                    @endforeach
                                </select>
                                <label class="flex items-center gap-2 text-xs text-slate-600">
                                    <input type="checkbox" name="keep_property_links" value="1" class="rounded border-slate-300">
                                    Keep property links
                                </label>
                                <button type="submit" class="w-full rounded-lg bg-rose-600 px-3 py-2 text-sm font-bold text-white hover:bg-rose-700">Release</button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($selectedAgentId > 0)
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-black text-slate-900">
                            Landlords for agent #{{ $selectedAgentId }}
                            @if ($selectedAgent)
                                <span class="font-semibold text-slate-500">({{ $selectedAgent->name }})</span>
                            @endif
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">{{ $agentLandlords->count() }} visible in agent workspace</p>

                        @if ($agentLandlords->isEmpty())
                            <p class="mt-4 text-sm text-slate-600">No landlords scoped to this agent.</p>
                        @else
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-100">
                                            <th class="py-2 pr-3">ID</th>
                                            <th class="py-2 pr-3">Name</th>
                                            <th class="py-2 pr-3">Contact</th>
                                            <th class="py-2 pr-3">Visible because</th>
                                            <th class="py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($agentLandlords as $landlord)
                                            <tr>
                                                <td class="py-3 pr-3 font-mono text-xs">{{ $landlord->id }}</td>
                                                <td class="py-3 pr-3 font-semibold text-slate-900">{{ $landlord->name }}</td>
                                                <td class="py-3 pr-3 text-slate-600">{{ $landlord->email ?: $landlord->phone ?: '—' }}</td>
                                                <td class="py-3 pr-3 text-xs text-slate-600">{{ $landlordScope->summarizeLandlordAgentLinks((int) $landlord->id, $selectedAgentId) }}</td>
                                                <td class="py-3">
                                                    <a
                                                        href="{{ route('superadmin.ops.index', ['tab' => 'landlord-scope', 'agent_id' => $selectedAgentId, 'inspect_landlord_id' => $landlord->id]) }}"
                                                        class="text-xs font-bold text-emerald-700 hover:underline"
                                                    >
                                                        Inspect
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Unscoped landlords</h2>
                    <p class="text-sm text-slate-500 mt-1">Visible to super admin only until assigned.</p>

                    @if ($orphans->isEmpty())
                        <p class="mt-4 text-sm text-slate-600">None — every landlord is scoped to an agent or property.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-100">
                                        <th class="py-2 pr-3">ID</th>
                                        <th class="py-2 pr-3">Name</th>
                                        <th class="py-2 pr-3">Contact</th>
                                        <th class="py-2 pr-3">Created</th>
                                        <th class="py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($orphans as $landlord)
                                        <tr>
                                            <td class="py-3 pr-3 font-mono text-xs">{{ $landlord->id }}</td>
                                            <td class="py-3 pr-3 font-semibold text-slate-900">{{ $landlord->name }}</td>
                                            <td class="py-3 pr-3 text-slate-600">{{ $landlord->email ?: $landlord->phone ?: '—' }}</td>
                                            <td class="py-3 pr-3 text-xs text-slate-500">{{ $landlord->created_at }}</td>
                                            <td class="py-3">
                                                <a
                                                    href="{{ route('superadmin.ops.index', ['tab' => 'landlord-scope', 'inspect_landlord_id' => $landlord->id]) }}"
                                                    class="text-xs font-bold text-emerald-700 hover:underline"
                                                >
                                                    Inspect / assign
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection
