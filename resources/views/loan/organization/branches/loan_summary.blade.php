<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.branches.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Branches</a>
            <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">View loans</a>
        </x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Loans</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">{{ number_format($totals['loans']) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total principal</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">{{ number_format($totals['principal'], 2) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total balance</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">{{ number_format($totals['balance'], 2) }}</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">By branch</h2>
                <p class="text-xs text-slate-500 mt-1">Uses linked branch when set; otherwise the legacy text label on the loan.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Region</th>
                            <th class="px-5 py-3 text-right">Loans</th>
                            <th class="px-5 py-3 text-right">Active</th>
                            <th class="px-5 py-3 text-right">Principal</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->branch_label }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $row->region_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((int) $row->loan_count) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((int) $row->active_count) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $row->total_principal, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $row->total_balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No loans in LoanBook yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
