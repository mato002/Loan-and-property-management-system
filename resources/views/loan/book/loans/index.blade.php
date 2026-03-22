<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.loan_arrears') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Arrears</a>
            <a href="{{ route('loan.book.checkoff_loans') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Checkoff</a>
            <a href="{{ route('loan.book.loans.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Create loan</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Loan register</h2>
                <p class="text-xs text-slate-500">{{ $loans->total() }} account(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Loan #</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3">DPD</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($loans as $loan)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $loan->loan_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $loan->loanClient->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $loan->product_name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $loan->balance, 2) }}</td>
                                <td class="px-5 py-3 tabular-nums {{ $loan->dpd > 0 ? 'text-red-600 font-semibold' : 'text-slate-600' }}">{{ $loan->dpd }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $loan->status) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.book.loans.edit', $loan) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.book.loans.destroy', $loan) }}" class="inline" data-swal-confirm="Delete this loan? No disbursements or collections may exist.">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">No loans booked yet.</td>
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
