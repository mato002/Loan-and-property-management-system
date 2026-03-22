<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.dashboard') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dashboard</a>
        </x-slot>
        @include('loan.accounting.partials.flash')

        <form method="get" action="{{ route('loan.system.access_logs.index') }}" class="flex flex-wrap gap-3 items-end mb-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">User</label>
                <select name="user_id" class="rounded-lg border-slate-200 text-sm min-w-[180px]">
                    <option value="">All users</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Method</label>
                <select name="method" class="rounded-lg border-slate-200 text-sm min-w-[100px]">
                    <option value="">Any</option>
                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m)
                        <option value="{{ $m }}" @selected(request('method') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Search path / route</label>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="e.g. loan/clients" class="w-full rounded-lg border-slate-200 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Filter</button>
            @if (request()->hasAny(['user_id', 'method', 'q']))
                <a href="{{ route('loan.system.access_logs.index') }}" class="text-sm text-indigo-600 hover:underline self-center">Reset</a>
            @endif
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">When</th>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Path</th>
                        <th class="px-5 py-3">Route</th>
                        <th class="px-5 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-5 py-3 text-slate-600 whitespace-nowrap text-xs">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-5 py-3 text-slate-800">{{ $log->user?->name ?? '—' }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $log->method }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-700 max-w-xs truncate" title="{{ $log->path }}">{{ $log->path }}</td>
                            <td class="px-5 py-3 text-xs text-slate-600 max-w-[200px] truncate" title="{{ $log->route_name }}">{{ $log->route_name ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">No log entries yet. Browse any loan module page after migrations are applied.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if ($logs->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $logs->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
