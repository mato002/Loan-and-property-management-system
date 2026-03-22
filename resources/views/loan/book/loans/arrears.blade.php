<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">All loans</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Past-due accounts</h2>
                <p class="text-xs text-slate-500 mt-1">{{ $loans->total() }} loan(s) with DPD &gt; 0</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Loan #</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3">DPD</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($loans as $loan)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $loan->loan_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $loan->loanClient->full_name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $loan->balance, 2) }}</td>
                                <td class="px-5 py-3 font-semibold text-red-600 tabular-nums">{{ $loan->dpd }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ $loan->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('loan.book.loans.edit', $loan) }}" class="text-indigo-600 font-medium text-sm hover:underline">Update</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No arrears in the register — great job.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($loans->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $loans->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
