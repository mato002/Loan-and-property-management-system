<x-property-layout>
    <x-slot name="header">Equity Sync Status</x-slot>

    <x-property.page
        title="Equity Sync Status"
        subtitle="Monitor automatic bank sync runs and trigger manual sync from the agent workspace."
    >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-property.module-status label="Revenue / Equity" />
            <form method="post" action="{{ route('property.equity.sync_status.sync') }}">
                @csrf
                <button class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                    Run Sync Now
                </button>
            </form>
        </div>
        <form method="get" class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-8 items-end">
            <div>
                <label class="text-xs text-slate-500">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Message, status, trigger..." class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Status</label>
                <select name="status" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="">All</option>
                    <option value="success" @selected(($filters['status'] ?? '') === 'success')>Success</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                    <option value="running" @selected(($filters['status'] ?? '') === 'running')>Running</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Trigger</label>
                <select name="trigger" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="">All</option>
                    <option value="manual" @selected(($filters['trigger'] ?? '') === 'manual')>Manual</option>
                    <option value="auto" @selected(($filters['trigger'] ?? '') === 'auto')>Auto</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Sort</label>
                <select name="sort" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="started_at" @selected(($filters['sort'] ?? 'started_at') === 'started_at')>Started</option>
                    <option value="finished_at" @selected(($filters['sort'] ?? '') === 'finished_at')>Finished</option>
                    <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Status</option>
                    <option value="fetched_count" @selected(($filters['sort'] ?? '') === 'fetched_count')>Fetched</option>
                    <option value="matched_count" @selected(($filters['sort'] ?? '') === 'matched_count')>Matched</option>
                    <option value="unmatched_count" @selected(($filters['sort'] ?? '') === 'unmatched_count')>Unmatched</option>
                    <option value="error_count" @selected(($filters['sort'] ?? '') === 'error_count')>Errors</option>
                    <option value="id" @selected(($filters['sort'] ?? '') === 'id')>ID</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Dir / Per page</label>
                <div class="flex gap-2">
                    <select name="dir" class="block w-full rounded-xl border-slate-300 shadow-sm">
                        <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
                        <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
                    </select>
                    <select name="per_page" class="block w-full rounded-xl border-slate-300 shadow-sm">
                        @foreach ([10, 20, 30, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
                <a href="{{ url()->current() }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                @include('property.agent.partials.export_dropdown', [
                    'csvUrl' => route('property.equity.sync_status', array_merge(request()->query(), ['export' => 'csv'])),
                    'xlsUrl' => route('property.equity.sync_status', array_merge(request()->query(), ['export' => 'xls'])),
                    'pdfUrl' => route('property.equity.sync_status', array_merge(request()->query(), ['export' => 'pdf'])),
                    'class' => 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50',
                ])
            </div>
        </form>

        @if($latest)
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Status</div><div class="font-bold text-slate-900">{{ strtoupper((string) ($latest->status ?? 'unknown')) }}</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Fetched</div><div class="font-bold text-slate-900">{{ (int) ($liveStats['fetched'] ?? 0) }}</div><div class="text-[11px] text-slate-500">Live total</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Matched</div><div class="font-bold text-slate-900">{{ (int) ($liveStats['matched'] ?? 0) }}</div><div class="text-[11px] text-slate-500">Live total</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Unmatched</div><div class="font-bold text-slate-900">{{ (int) ($liveStats['unmatched'] ?? 0) }}</div><div class="text-[11px] text-slate-500">Live queue</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Duplicates</div><div class="font-bold text-slate-900">{{ (int) ($liveStats['duplicates'] ?? 0) }}</div><div class="text-[11px] text-slate-500">Live total</div></div>
            </div>
            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs text-slate-500">Latest started</div>
                    <div class="font-semibold text-slate-900">{{ optional($latest->started_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs text-slate-500">Latest finished</div>
                    <div class="font-semibold text-slate-900">{{ optional($latest->finished_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-xs text-slate-500">Latest successful run</div>
                    <div class="font-semibold text-slate-900">
                        {{ optional($latestSuccess?->started_at)->format('Y-m-d H:i:s') ?? 'No successful run yet' }}
                    </div>
                </div>
            </div>
            @if (!empty($latest->message))
                <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase text-slate-500">Latest run message</p>
                    <p class="mt-1 text-sm text-slate-700">{{ $latest->message }}</p>
                </div>
            @endif
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold">Started</th>
                        <th class="px-4 py-3 text-left font-bold">Trigger</th>
                        <th class="px-4 py-3 text-left font-bold">Status</th>
                        <th class="px-4 py-3 text-right font-bold">Fetched</th>
                        <th class="px-4 py-3 text-right font-bold">Matched</th>
                        <th class="px-4 py-3 text-right font-bold">Unmatched</th>
                        <th class="px-4 py-3 text-right font-bold">Duplicates</th>
                        <th class="px-4 py-3 text-right font-bold">Errors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr class="bg-emerald-50/60">
                        <td class="px-4 py-3 font-semibold text-emerald-800">Now (live)</td>
                        <td class="px-4 py-3 text-emerald-800">System</td>
                        <td class="px-4 py-3 text-emerald-800">Current totals</td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-800">{{ (int) ($liveStats['fetched'] ?? 0) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-800">{{ (int) ($liveStats['matched'] ?? 0) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-800">{{ (int) ($liveStats['unmatched'] ?? 0) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-800">{{ (int) ($liveStats['duplicates'] ?? 0) }}</td>
                        <td class="px-4 py-3 text-right text-emerald-800">—</td>
                    </tr>
                    @forelse ($runs as $run)
                        <tr>
                            <td class="px-4 py-3">{{ optional($run->started_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3">{{ ucfirst($run->trigger) }}</td>
                            <td class="px-4 py-3">{{ ucfirst($run->status) }}</td>
                            <td class="px-4 py-3 text-right">{{ (int) ($run->fetched_count ?? 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ (int) ($run->matched_count ?? 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ (int) ($run->unmatched_count ?? 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ (int) ($run->duplicate_count ?? 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ (int) ($run->error_count ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No sync runs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
        <script>
            setTimeout(function () {
                if (typeof window !== 'undefined' && window.location) {
                    window.location.reload();
                }
            }, 30000);
        </script>
    </x-property.page>
</x-property-layout>

