@extends('layouts.superadmin', ['title' => 'Agent branding — Super Admin'])

@section('content')
    <div class="mb-6">
        <a href="{{ route('superadmin.agent_workspaces') }}" class="text-sm font-semibold text-[#2f4f4f] hover:underline">&larr; Back to agent workspaces</a>
        <h1 class="mt-4 text-2xl font-black tracking-tight text-slate-900">Agent branding</h1>
        <p class="mt-1 text-sm text-slate-600">Pick an agent and set their company logo, name, and contact details — no impersonation needed. Saves go to that agent’s workspace (invoices, receipts, prints).</p>
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="get" action="{{ route('superadmin.agent_branding') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="agent-branding-select" class="block text-xs font-semibold text-slate-600">Select agent</label>
                <select
                    id="agent-branding-select"
                    name="agent"
                    required
                    class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Choose an agent…</option>
                    @foreach ($agents as $row)
                        <option value="{{ $row->id }}">{{ $row->name }} ({{ $row->email }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-[#2f4f4f] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#264040]">
                Open branding
            </button>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Agents</h2>
        </div>
        @if ($agents->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-slate-500">No agent workspaces yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-5 py-3 text-left font-bold">Agent</th>
                            <th class="px-5 py-3 text-left font-bold">Company name on file</th>
                            <th class="px-5 py-3 text-right font-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($agents as $row)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-900">{{ $row->name }}</div>
                                    <div class="text-slate-500">{{ $row->email }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-700">
                                    {{ $companyNames[$row->id] ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a
                                        href="{{ route('superadmin.agent_workspaces.branding', $row) }}"
                                        class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                                    >
                                        Edit branding
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
