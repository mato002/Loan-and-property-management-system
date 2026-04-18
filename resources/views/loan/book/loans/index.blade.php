<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.loan_arrears') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Arrears</a>
            <a href="{{ route('loan.book.checkoff_loans') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Checkoff</a>
            <a href="{{ route('loan.book.loans.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Create loan</a>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Loan #, client, product..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($statuses ?? []) as $k => $lbl)
                            <option value="{{ $k }}" @selected(($status ?? '') === $k)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $b)
                            <option value="{{ $b }}" @selected(($branch ?? '') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Repayment</label>
                    <select name="repayment" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        <option value="has_balance" @selected(($repayment ?? '') === 'has_balance')>Has balance</option>
                        <option value="fully_paid" @selected(($repayment ?? '') === 'fully_paid')>Fully paid</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 15, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.loans.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.loans.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.loans.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.loans.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick repayment filter:</span>
            <a href="{{ route('loan.book.loans.index', array_merge(request()->query(), ['repayment' => ''])) }}"
               class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition-colors {{ ($repayment ?? '') === '' ? 'border-[#2f4f4f] bg-[#2f4f4f] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                All
            </a>
            <a href="{{ route('loan.book.loans.index', array_merge(request()->query(), ['repayment' => 'has_balance'])) }}"
               class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition-colors {{ ($repayment ?? '') === 'has_balance' ? 'border-[#2f4f4f] bg-[#2f4f4f] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                Has balance
            </a>
            <a href="{{ route('loan.book.loans.index', array_merge(request()->query(), ['repayment' => 'fully_paid'])) }}"
               class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition-colors {{ ($repayment ?? '') === 'fully_paid' ? 'border-[#2f4f4f] bg-[#2f4f4f] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                Fully paid
            </a>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-1 sm:flex-row sm:justify-between sm:items-center">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Loan register</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Paid includes unposted pay-ins until you post them from <a href="{{ route('loan.payments.unposted') }}" class="text-indigo-600 font-semibold hover:underline">Unposted</a>.</p>
                </div>
                <p class="text-xs text-slate-500">{{ $loans->total() }} account(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Loan #</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3 text-right">Paid</th>
                            <th class="px-5 py-3 text-right">Remaining</th>
                            <th class="px-5 py-3">Progress</th>
                            <th class="px-5 py-3">DPD</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($loans as $loan)
                            @php
                                $posted = (float) ($loan->processed_repayments_sum_amount ?? 0);
                                $unposted = (float) ($loan->unposted_repayments_sum_amount ?? 0);
                                $paid = $posted + $unposted;
                                $balance = (float) $loan->balance;
                                $remaining = max(0, $balance - $unposted);
                                $totalRepayable = $paid + $remaining;
                                $progress = $totalRepayable > 0.00001 ? min(100, max(0, ($paid / $totalRepayable) * 100)) : 0;
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $loan->loan_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $loan->loanClient->full_name ?? 'Client record missing' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $loan->product_name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-emerald-700">
                                    <span class="font-semibold">{{ number_format($paid, 2) }}</span>
                                    @if ($unposted > 0.00001)
                                        <span class="block text-[10px] font-normal text-amber-800 leading-tight mt-0.5">{{ number_format($posted, 2) }} posted · {{ number_format($unposted, 2) }} unposted</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format($remaining, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ number_format($progress, 1) }}%</td>
                                <td class="px-5 py-3 tabular-nums {{ $loan->dpd > 0 ? 'text-red-600 font-semibold' : 'text-slate-600' }}">{{ $loan->dpd }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $loan->status) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if ($loan->status === \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT)
                                        <a href="{{ route('loan.book.disbursements.create', ['loan_book_loan_id' => $loan->id]) }}" class="text-emerald-700 font-medium text-sm hover:underline mr-3">Disburse</a>
                                    @endif
                                    <a href="{{ route('loan.book.loans.show', $loan) }}" class="text-slate-700 font-medium text-sm hover:underline mr-3">View</a>
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
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">No loans booked yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($loans->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $loans->withQueryString()->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
