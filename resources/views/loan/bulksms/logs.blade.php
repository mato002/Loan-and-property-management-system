<x-loan-layout>
    <x-loan.page
        title="SMS logs"
        subtitle="History of messages debited from the SMS wallet."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.bulksms.compose') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Send SMS
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">All entries</h2>
                <p class="text-xs text-slate-500">{{ $logs->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Phone</th>
                            <th class="px-5 py-3">Message</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Charged</th>
                            <th class="px-5 py-3">Schedule</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-slate-50/80 align-top">
                                <td class="px-5 py-3 text-slate-600 tabular-nums whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900 tabular-nums">{{ $log->phone }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs">
                                    <span class="line-clamp-2" title="{{ $log->message }}">{{ $log->message }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($log->status === 'sent')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Sent</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 border border-slate-200">{{ $log->status }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">
                                    {{ $log->charged_amount !== null ? number_format((float) $log->charged_amount, 4) : '—' }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    @if ($log->schedule)
                                        #{{ $log->schedule->id }}
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No SMS logs yet. <a href="{{ route('loan.bulksms.compose') }}" class="text-indigo-600 font-medium hover:underline">Send a message</a> or top up the wallet first.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
