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

        @if($latest)
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Status</div><div class="font-bold text-slate-900">{{ strtoupper($latest->status) }}</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Fetched</div><div class="font-bold text-slate-900">{{ $latest->fetched_count }}</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Matched</div><div class="font-bold text-slate-900">{{ $latest->matched_count }}</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Unmatched</div><div class="font-bold text-slate-900">{{ $latest->unmatched_count }}</div></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Duplicates</div><div class="font-bold text-slate-900">{{ $latest->duplicate_count }}</div></div>
            </div>
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
                    @forelse ($runs as $run)
                        <tr>
                            <td class="px-4 py-3">{{ optional($run->started_at)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3">{{ ucfirst($run->trigger) }}</td>
                            <td class="px-4 py-3">{{ ucfirst($run->status) }}</td>
                            <td class="px-4 py-3 text-right">{{ $run->fetched_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $run->matched_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $run->unmatched_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $run->duplicate_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $run->error_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No sync runs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    </x-property.page>
</x-property-layout>

