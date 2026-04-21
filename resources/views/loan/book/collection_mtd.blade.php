<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_sheet.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Collection sheet</a>
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total collected</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">{{ number_format((float) ($totals->collected ?? 0), 2) }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $start }} → {{ $end }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Receipt lines</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-2">{{ (int) ($totals->receipt_count ?? 0) }}</p>
            </div>
            <div class="bg-[#2f4f4f] text-white rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-[#8db1af] uppercase tracking-wide">Period</p>
                <p class="text-lg font-semibold mt-2">Month to date</p>
                <p class="text-sm text-[#d4e4e3] mt-1">Targets vs actuals: see Collection rates.</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Progressive disbursements</h2>
                <p class="text-xs text-slate-500 mt-1">{{ $start }} → {{ $end }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3 text-right">Disbursed Amount</th>
                            <th class="px-5 py-3 text-right">Total Loans</th>
                            <th class="px-5 py-3 text-right">Loan+Charges</th>
                            <th class="px-5 py-3 text-right">Paid</th>
                            <th class="px-5 py-3 text-right">Arrears</th>
                            <th class="px-5 py-3 text-right">GC%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse (($branchPerformance ?? collect()) as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->branch }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $row->disbursed_amount, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((int) $row->total_loans) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $row->loan_plus_charges, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-emerald-700">{{ number_format((float) $row->paid, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums {{ (float) $row->arrears > 0 ? 'text-red-600 font-semibold' : 'text-slate-600' }}">{{ number_format((float) $row->arrears, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $row->gc_percent, 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">No branch disbursement/collection metrics for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (($branchPerformance ?? collect())->count() > 0)
                        <tfoot class="bg-slate-50 border-t border-slate-100">
                            <tr class="text-sm font-semibold text-slate-800">
                                <td class="px-5 py-3">Totals</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($branchTotals->disbursed_amount ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((int) ($branchTotals->total_loans ?? 0)) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($branchTotals->loan_plus_charges ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-emerald-700">{{ number_format((float) ($branchTotals->paid ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums {{ (float) ($branchTotals->arrears ?? 0) > 0 ? 'text-red-600' : '' }}">{{ number_format((float) ($branchTotals->arrears ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($branchTotals->gc_percent ?? 0), 2) }}%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">By channel</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($byChannel as $row)
                        <li class="px-5 py-3 flex justify-between text-sm">
                            <span class="text-slate-700 capitalize">{{ $row->channel }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ number_format((float) $row->total, 2) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-slate-500 text-sm">No collections this month yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Recent receipts</h2>
                </div>
                <ul class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
                    @forelse ($recent as $row)
                        <li class="px-5 py-3 text-sm">
                            <span class="font-mono text-xs text-indigo-600">{{ $row->loan?->loan_number ?? 'Unknown loan' }}</span>
                            <span class="text-slate-800"> · {{ $row->loan?->loanClient?->full_name ?? 'Unknown client' }}</span>
                            <span class="block text-xs text-slate-500 mt-0.5">{{ optional($row->transaction_at ?? $row->collected_on)->format('Y-m-d') }} · {{ number_format((float) $row->amount, 2) }} · {{ $row->channel }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-slate-500 text-sm">No data.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
