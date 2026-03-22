<x-loan-layout>
    <x-loan.page
        title="Unposted payments"
        subtitle="Queue of payments waiting to be posted to the loan book."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.payments.merge') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Merge payments</a>
            <a href="{{ route('loan.payments.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Record payment</a>
        </x-slot>

        @include('loan.payments.partials.flash')

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">Unposted queue</h2>
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
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">Kind</th>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Receipt</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $p)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $p->reference }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loan_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->loan?->loanClient?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->channel }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $p->payment_kind) }}</td>
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $p->transaction_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $p->mpesa_receipt_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <form method="post" action="{{ route('loan.payments.post', $p) }}" class="inline mr-2">
                                        @csrf
                                        <button type="submit" class="text-[#2f4f4f] hover:text-[#264040] font-medium text-sm">Post</button>
                                    </form>
                                    @if ($p->canEdit())
                                        <a href="{{ route('loan.payments.edit', $p) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-2">Edit</a>
                                        <form method="post" action="{{ route('loan.payments.destroy', $p) }}" class="inline" data-swal-confirm="Delete this unposted payment?">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">
                                    No unposted payments. Use <span class="font-medium text-slate-700">Record payment</span> or <span class="font-medium text-slate-700">Merge payments</span>.
                                </td>
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
