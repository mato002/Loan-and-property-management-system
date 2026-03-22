<x-loan-layout>
    <x-loan.page
        title="Business performance"
        subtitle="Snapshot KPIs by date and branch. Connect LoanBook exports or ETL later for automation."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.analytics.performance.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add snapshot
            </a>
        </x-slot>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Snapshots</h2>
                <p class="text-xs text-slate-500">{{ $records->total() }} row(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Outstanding</th>
                            <th class="px-5 py-3">Disb. (period)</th>
                            <th class="px-5 py-3">Coll. (period)</th>
                            <th class="px-5 py-3">NPL %</th>
                            <th class="px-5 py-3">Borrowers</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $r)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 text-slate-600 tabular-nums whitespace-nowrap">{{ $r->record_date->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $r->branch ?: '— (all)' }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $r->total_outstanding !== null ? number_format($r->total_outstanding, 2) : '—' }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $r->disbursements_period !== null ? number_format($r->disbursements_period, 2) : '—' }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $r->collections_period !== null ? number_format($r->collections_period, 2) : '—' }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $r->npl_rate !== null ? number_format($r->npl_rate, 2).'%' : '—' }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $r->active_borrowers_count ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.analytics.performance.edit', $r) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.analytics.performance.destroy', $r) }}" class="inline" onsubmit="return confirm('Delete this snapshot?');">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">
                                    No snapshots. <a href="{{ route('loan.analytics.performance.create') }}" class="text-indigo-600 font-medium hover:underline">Record performance</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($records->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $records->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
