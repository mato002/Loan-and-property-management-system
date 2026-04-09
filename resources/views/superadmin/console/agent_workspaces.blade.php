@php($title = 'Agent Workspaces — Super Admin')
@extends('layouts.superadmin', ['title' => $title])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Agent workspaces</h1>
        <p class="mt-1 text-sm text-slate-600">Ownership footprint per agent account.</p>
    </div>

    <form method="get" class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
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
            <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
            <a href="{{ route('superadmin.agent_workspaces') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </div>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
        <a href="{{ route('superadmin.agent_workspaces', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-5 py-3 text-left font-bold">Agent</th>
                    <th class="px-5 py-3 text-right font-bold">Properties</th>
                    <th class="px-5 py-3 text-right font-bold">Units</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($agents as $agent)
                    <tr>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ $agent->name }}</div>
                            <div class="text-slate-500">{{ $agent->email }}</div>
                        </td>
                        <td class="px-5 py-4 text-right">{{ (int) ($propertyCounts[$agent->id] ?? 0) }}</td>
                        <td class="px-5 py-4 text-right">{{ (int) ($unitCounts[$agent->id] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-slate-500">No agents found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">
        {{ $agents->links() }}
    </div>
@endsection

