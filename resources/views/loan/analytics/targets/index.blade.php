<x-loan-layout>
    <x-loan.page
        title="Targets & accruals"
        subtitle="Monthly disbursement, collection, and interest-accrual targets by branch."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.analytics.targets.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add target
            </a>
        </x-slot>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Period targets</h2>
                <p class="text-xs text-slate-500">{{ $targets->total() }} row(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Period</th>
                            <th class="px-5 py-3">Disbursement</th>
                            <th class="px-5 py-3">Collection</th>
                            <th class="px-5 py-3">Accrual</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($targets as $t)
                            @php
                                $periodLabel = \Carbon\Carbon::create($t->period_year, $t->period_month, 1)->format('M Y');
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $t->branch }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $periodLabel }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ number_format($t->disbursement_target, 2) }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ number_format($t->collection_target, 2) }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ number_format($t->accrual_target, 2) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.analytics.targets.edit', $t) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.analytics.targets.destroy', $t) }}" class="inline" data-swal-confirm="Delete this target row?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No targets. <a href="{{ route('loan.analytics.targets.create') }}" class="text-indigo-600 font-medium hover:underline">Add a period</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($targets->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $targets->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
