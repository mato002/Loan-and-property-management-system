<x-loan-layout>
    <x-loan.page
        title="SMS schedules"
        subtitle="Pending sends run automatically when due (scheduler / cron)."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.compose') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New schedule
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">All schedules</h2>
                <p class="text-xs text-slate-500">{{ $schedules->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Scheduled</th>
                            <th class="px-5 py-3">Recipients</th>
                            <th class="px-5 py-3">Preview</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($schedules as $row)
                            <tr class="hover:bg-slate-50/80 align-top">
                                <td class="px-5 py-3 text-slate-600 tabular-nums whitespace-nowrap">{{ $row->scheduled_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ is_array($row->recipients) ? count($row->recipients) : 0 }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs">
                                    <span class="line-clamp-2" title="{{ $row->body }}">{{ $row->body }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($row->status === 'pending')
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-900 border border-amber-100">Pending</span>
                                    @elseif ($row->status === 'sent')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Sent</span>
                                    @elseif ($row->status === 'cancelled')
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">Cancelled</span>
                                    @elseif ($row->status === 'failed')
                                        <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 border border-red-100" title="{{ $row->failure_reason }}">Failed</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 border border-slate-200">{{ $row->status }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($row->status === 'pending')
                                        <form method="post" action="{{ route('loan.bulksms.schedules.cancel', $row) }}" class="inline" data-swal-confirm="Cancel this scheduled send?">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Cancel</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                                    No schedules. Use <a href="{{ route('loan.bulksms.compose') }}" class="text-indigo-600 font-medium hover:underline">Send or schedule SMS</a> with a future date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($schedules->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $schedules->links() }}
                </div>
            @endif
        </div>

        <p class="text-xs text-slate-500 mt-4">Ensure <code class="bg-slate-100 px-1 rounded">php artisan schedule:run</code> is triggered every minute in production (e.g. cron), or run <code class="bg-slate-100 px-1 rounded">php artisan bulksms:dispatch-schedules</code> manually.</p>
    </x-loan.page>
</x-loan-layout>
