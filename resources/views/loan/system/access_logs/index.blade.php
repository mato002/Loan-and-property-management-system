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
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Activity type</label>
                <select name="activity_type" class="rounded-lg border-slate-200 text-sm min-w-[130px]">
                    <option value="">Any</option>
                    @foreach (($activityTypes ?? []) as $type)
                        <option value="{{ $type }}" @selected(request('activity_type') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Route</label>
                <select name="route_name" class="rounded-lg border-slate-200 text-sm min-w-[220px]">
                    <option value="">All routes</option>
                    @foreach (($routes ?? []) as $routeName)
                        <option value="{{ $routeName }}" @selected(request('route_name') === $routeName)>{{ $routeName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">IP</label>
                <select name="ip_address" class="rounded-lg border-slate-200 text-sm min-w-[140px]">
                    <option value="">All IPs</option>
                    @foreach (($ips ?? []) as $ip)
                        <option value="{{ $ip }}" @selected(request('ip_address') === $ip)>{{ $ip }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="rounded-lg border-slate-200 text-sm min-w-[150px]" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="rounded-lg border-slate-200 text-sm min-w-[150px]" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Per page</label>
                <select name="per_page" class="rounded-lg border-slate-200 text-sm min-w-[90px]">
                    @foreach ([20, 40, 80, 120, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? 40) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Search activity / path / route</label>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="e.g. accessed clients" class="w-full rounded-lg border-slate-200 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Filter</button>
            @if (request()->hasAny(['user_id', 'method', 'activity_type', 'route_name', 'ip_address', 'from_date', 'to_date', 'q', 'per_page']))
                <a href="{{ route('loan.system.access_logs.index') }}" class="text-sm text-indigo-600 hover:underline self-center">Reset</a>
            @endif
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('loan.system.access_logs.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                <a href="{{ route('loan.system.access_logs.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('loan.system.access_logs.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">When</th>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Activity</th>
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
                            <td class="px-5 py-3 text-xs text-slate-700 max-w-sm truncate" title="{{ $log->activity }}">{{ $log->activity ?? '—' }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-700 max-w-xs truncate" title="{{ $log->path }}">{{ $log->path }}</td>
                            <td class="px-5 py-3 text-xs text-slate-600 max-w-[200px] truncate" title="{{ $log->route_name }}">{{ $log->route_name ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500">No log entries yet. Browse any loan module page after migrations are applied.</td>
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
