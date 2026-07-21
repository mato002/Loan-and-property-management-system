@extends('layouts.superadmin', ['title' => $agent->name.' — Agent Workspace — Super Admin'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('superadmin.agent_workspaces', request()->only(['q', 'workspace', 'status', 'per_page'])) }}" class="text-sm font-semibold text-[#2f4f4f] hover:underline">&larr; Back to agent workspaces</a>
        <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-black tracking-tight text-slate-900">{{ $agent->name }}</h1>
                    @include('superadmin.console.partials.workspace_status_badge', ['status' => $summary['status'] ?? ['label' => 'Pending', 'tone' => 'orange']])
                </div>
                <p class="mt-1 text-sm text-slate-600">{{ $agent->email }}</p>
                <p class="mt-1 text-xs text-slate-500">Agent user #{{ $agent->id }} · Joined {{ optional($agent->created_at)->format('M j, Y') ?? '—' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="post" action="{{ route('superadmin.agent_workspaces.impersonate', $agent) }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-[#2f4f4f] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#264040]">View dashboard</button>
                </form>
                <a href="{{ route('superadmin.users.edit', $agent) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit user</a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3 mb-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Properties</p>
            <p class="mt-2 text-3xl font-black text-slate-900 tabular-nums">{{ $propertyCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Units</p>
            <p class="mt-2 text-3xl font-black text-slate-900 tabular-nums">{{ $unitCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subscription</p>
            <p class="mt-2 text-lg font-bold text-slate-900">{{ $summary['subscription'] ?? 'No plan' }}</p>
            @if ($subscription)
                <p class="mt-1 text-xs text-slate-500">Status: {{ ucfirst((string) $subscription->status) }}</p>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Quick actions</h2>
            <div class="mt-4 space-y-3">
                @if (($summary['status']['key'] ?? '') === 'suspended')
                    <form method="post" action="{{ route('superadmin.agent_workspaces.toggle_status', $agent) }}">
                        @csrf
                        <input type="hidden" name="intent" value="activate">
                        <button type="submit" class="w-full rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-left text-sm font-bold text-emerald-800 hover:bg-emerald-100">Activate workspace</button>
                    </form>
                @else
                    <form method="post" action="{{ route('superadmin.agent_workspaces.toggle_status', $agent) }}" onsubmit="return confirm('Suspend this workspace?');">
                        @csrf
                        <input type="hidden" name="intent" value="suspend">
                        <button type="submit" class="w-full rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-left text-sm font-bold text-rose-800 hover:bg-rose-100">Suspend workspace</button>
                    </form>
                @endif
                <a href="{{ route('superadmin.console.subscriptions', ['q' => $agent->email]) }}" class="block rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800 hover:bg-slate-100">Open subscriptions module</a>
            </div>
        </div>

        @if ($packages->isNotEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Change subscription package</h2>
                <form method="post" action="{{ route('superadmin.agent_workspaces.subscription', $agent) }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Package</label>
                        <select name="subscription_package_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($packages as $packageId => $packageName)
                                <option value="{{ $packageId }}" @selected((int) ($summary['package_id'] ?? 0) === (int) $packageId)>{{ $packageName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">Status</label>
                        <select name="status" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['active', 'inactive', 'suspended', 'cancelled'] as $statusOption)
                                <option value="{{ $statusOption }}" @selected((string) ($subscription->status ?? 'active') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Save subscription</button>
                </form>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="text-lg font-bold text-slate-900">Transfer ownership</h2>
            <p class="mt-2 text-sm text-slate-600">Bulk reassign properties, tenants, and other agent-scoped records to another workspace.</p>
            <form method="post" action="{{ route('superadmin.agent_workspaces.transfer', $agent) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onsubmit="return confirm('Transfer all scoped records to the selected agent?');">
                @csrf
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-600">Receiving agent</label>
                    <select name="target_agent_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select agent…</option>
                        @foreach ($otherAgents as $peer)
                            <option value="{{ $peer->id }}">{{ $peer->name }} ({{ $peer->email }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-900 hover:bg-amber-100">Transfer all records</button>
            </form>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Properties in workspace</h2>
        </div>
        @if ($properties->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-slate-500">No properties assigned to this agent yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-5 py-3 text-left font-bold">Property</th>
                            <th class="px-5 py-3 text-left font-bold">Code</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($properties as $property)
                            <tr>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $property->name }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $property->code ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($propertyCount > $properties->count())
                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Showing first {{ $properties->count() }} of {{ $propertyCount }} properties.</p>
            @endif
        @endif
    </div>
@endsection
