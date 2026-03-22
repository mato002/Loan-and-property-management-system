<x-loan-layout>
    <x-loan.page
        title="Overpayments"
        subtitle="Payment lines tagged as overpayments (all statuses)."
    >
        @include('loan.payments.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">Overpayments</h2>
                <p class="text-xs text-slate-500">{{ $payments->total() }} row(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Loan</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $p)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $p->reference }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loan_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loanClient?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600 capitalize">{{ $p->status }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->channel }}</td>
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $p->transaction_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">No overpayment records.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
