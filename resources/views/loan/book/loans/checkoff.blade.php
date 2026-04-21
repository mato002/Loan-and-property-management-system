<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">All loans</a>
        </x-slot>

        <form
            method="get"
            class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            x-data="{
                timer: null,
                autoSubmit() { this.$el.requestSubmit(); },
                autoSubmitDebounced(delay = 450) {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.$el.requestSubmit(), delay);
                }
            }"
        >
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Loan #, client, employer..." @input="autoSubmitDebounced(500)" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($statuses ?? []) as $k => $lbl)
                            <option value="{{ $k }}" @selected(($status ?? '') === $k)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $b)
                            <option value="{{ $b }}" @selected(($branch ?? '') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 20, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.checkoff_loans') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.checkoff_loans', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.checkoff_loans', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.checkoff_loans', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total facilities</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $loans->total() }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Current page</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $loans->count() }}</p>
            </div>
            <div class="rounded-xl border border-[#264040] bg-[#2f4f4f] p-4 text-white shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8db1af]">Portfolio type</p>
                <p class="mt-1 text-lg font-semibold">Checkoff loans</p>
                <p class="mt-1 text-xs text-[#d4e4e3]">Employer-linked deductions and repayment tracking.</p>
            </div>
        </div>

        <div
            class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden"
            x-data="{
                columnMenuOpen: false,
                cols: {
                    rowNo: true,
                    loanNo: true,
                    client: true,
                    officer: true,
                    employer: true,
                    disbursed: true,
                    balance: true,
                    status: true,
                    action: true
                }
            }"
        >
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Checkoff register</h2>
                    <p class="text-xs text-slate-500 mt-1">Employer tagged facilities and repayment posture.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative" @click.outside="columnMenuOpen = false">
                        <button type="button" @click="columnMenuOpen = !columnMenuOpen" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Columns
                        </button>
                        <div x-show="columnMenuOpen" x-cloak class="absolute right-0 mt-2 z-20 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Show / hide columns</p>
                            <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.rowNo" class="rounded border-slate-300">#</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.loanNo" class="rounded border-slate-300">ID No</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.client" class="rounded border-slate-300">Client</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.officer" class="rounded border-slate-300">Loan officer</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.employer" class="rounded border-slate-300">Confirmed by</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.disbursed" class="rounded border-slate-300">Date</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.balance" class="rounded border-slate-300">Checkoff</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.status" class="rounded border-slate-300">Receipt</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.action" class="rounded border-slate-300">Action</label>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">{{ $loans->total() }} facility(ies)</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th x-show="cols.rowNo" class="px-5 py-3">#</th>
                            <th x-show="cols.client" class="px-5 py-3">Client</th>
                            <th x-show="cols.loanNo" class="px-5 py-3">ID No</th>
                            <th x-show="cols.balance" class="px-5 py-3 text-right">Checkoff</th>
                            <th x-show="cols.officer" class="px-5 py-3">Loan officer</th>
                            <th x-show="cols.employer" class="px-5 py-3">Confirmed by</th>
                            <th x-show="cols.disbursed" class="px-5 py-3">Date</th>
                            <th x-show="cols.status" class="px-5 py-3">Receipt</th>
                            <th x-show="cols.action" class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($loans as $loan)
                            @php
                                $statusClasses = match ($loan->status) {
                                    \App\Models\LoanBookLoan::STATUS_ACTIVE => 'bg-emerald-100 text-emerald-700',
                                    \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT => 'bg-amber-100 text-amber-700',
                                    \App\Models\LoanBookLoan::STATUS_CLOSED => 'bg-slate-200 text-slate-700',
                                    \App\Models\LoanBookLoan::STATUS_WRITTEN_OFF => 'bg-rose-100 text-rose-700',
                                    \App\Models\LoanBookLoan::STATUS_RESTRUCTURED => 'bg-indigo-100 text-indigo-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td x-show="cols.rowNo" class="px-5 py-3 text-slate-500 tabular-nums">{{ (($loans->currentPage() - 1) * $loans->perPage()) + $loop->iteration }}</td>
                                <td x-show="cols.client" class="px-5 py-3 font-medium text-slate-900">{{ $loan->loanClient?->full_name ?? '—' }}</td>
                                <td x-show="cols.loanNo" class="px-5 py-3 text-slate-600">{{ $loan->loanClient?->id_number ?? '—' }}</td>
                                <td x-show="cols.balance" class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $loan->balance, 2) }}</td>
                                <td x-show="cols.officer" class="px-5 py-3 text-slate-600">{{ $loan->loanClient?->assignedEmployee?->full_name ?? '—' }}</td>
                                <td x-show="cols.employer" class="px-5 py-3 text-slate-600">System</td>
                                <td x-show="cols.disbursed" class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ optional($loan->disbursed_at)->format('d-m-Y, H:i') ?? '—' }}</td>
                                <td x-show="cols.status" class="px-5 py-3 font-mono text-xs text-indigo-600">{{ $loan->loan_number }}</td>
                                <td x-show="cols.action" class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.book.loans.show', $loan) }}" class="text-slate-700 font-medium text-sm hover:underline mr-3">View</a>
                                    <a href="{{ route('loan.book.loans.edit', $loan) }}" class="text-indigo-600 font-medium text-sm hover:underline">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">No checkoff loans flagged yet. Edit a loan and enable checkoff.</td>
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
