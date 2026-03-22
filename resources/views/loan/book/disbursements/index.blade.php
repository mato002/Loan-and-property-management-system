<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Record disbursement</a>
        </x-slot>

        @error('disbursement')
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 mb-4">{{ $message }}</div>
        @enderror

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Disbursement log</h2>
                <p class="text-xs text-slate-500">{{ $disbursements->total() }} row(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Loan</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($disbursements as $d)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $d->disbursed_at->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600">{{ $d->loan->loan_number }}</td>
                                <td class="px-5 py-3 text-slate-800">{{ $d->loan->loanClient->full_name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">{{ number_format((float) $d->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $d->method }}</td>
                                <td class="px-5 py-3 text-slate-500 text-xs">{{ $d->reference }}</td>
                                <td class="px-5 py-3 text-xs">
                                    @if ($d->accounting_journal_entry_id)
                                        <a href="{{ route('loan.accounting.journal.show', $d->accounting_journal_entry_id) }}" class="text-indigo-600 font-semibold hover:underline">#{{ $d->accounting_journal_entry_id }}</a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="post" action="{{ route('loan.book.disbursements.destroy', $d) }}" class="inline" data-swal-confirm="Remove this disbursement line?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">No disbursements recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($disbursements->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $disbursements->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
