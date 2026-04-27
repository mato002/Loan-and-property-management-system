<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.branches.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to branches</a>
        </x-slot>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Branch-region changes</h2>
                <p class="text-xs text-slate-500">{{ $rows->total() }} request(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">From</th>
                            <th class="px-5 py-3">To</th>
                            <th class="px-5 py-3">Effective</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Reason</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->branch?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->fromRegion?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->toRegion?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ optional($row->effective_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($row->status === \App\Models\LoanBranchRegionChange::STATUS_PENDING)
                                        <span class="text-xs font-semibold text-amber-700">Pending</span>
                                    @elseif ($row->status === \App\Models\LoanBranchRegionChange::STATUS_APPROVED)
                                        <span class="text-xs font-semibold text-emerald-700">Approved</span>
                                    @else
                                        <span class="text-xs font-semibold text-rose-700">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ $row->reason ?: '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if ($row->status === \App\Models\LoanBranchRegionChange::STATUS_PENDING)
                                        <form method="post" action="{{ route('loan.branches.changes.approve', $row) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-emerald-700 font-medium text-sm hover:underline mr-3">Approve</button>
                                        </form>
                                        <form method="post" action="{{ route('loan.branches.changes.reject', $row) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="reject_reason" value="Rejected from structure change queue." />
                                            <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Reject</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">Completed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">No structure changes recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $rows->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>

