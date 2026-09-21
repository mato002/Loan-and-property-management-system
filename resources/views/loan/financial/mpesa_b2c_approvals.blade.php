<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.financial.mpesa_payouts') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Live B2C payouts</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Requested</th>
                            <th class="px-4 py-3 text-left">Loan / client</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-left">Requested by</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($pending as $row)
                            <tr>
                                <td class="px-4 py-3 text-slate-600">{{ optional($row->payout_requested_at ?? $row->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row->loan?->loan_number ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $row->loan?->loanClient?->full_name ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ number_format((float) $row->amount, 2) }}</td>
                                <td class="px-4 py-3 font-mono text-slate-700">{{ $row->payout_phone ?: ($row->loan?->loanClient?->phone ?: '—') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row->payoutRequestedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('loan.book.disbursements.show', $row) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open</a>
                                    @if ($canApprove ?? false)
                                        <form method="post" action="{{ route('loan.book.disbursements.approve_payout', $row) }}" class="inline-block ml-1" data-swal-confirm="Approve and send this B2C payout via M-Pesa?">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center rounded-lg bg-[#2f4f4f] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#264040]">Approve</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No B2C disbursements awaiting approval.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($pending->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">{{ $pending->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
