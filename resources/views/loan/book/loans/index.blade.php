<x-loan-layout>
    <style>
        .loan-register-table {
            table-layout: fixed;
            width: 100%;
        }

        .loan-register-table th,
        .loan-register-table td {
            padding: 0.45rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.15rem;
            vertical-align: top;
            word-break: break-word;
        }
    </style>

    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.loan_arrears') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Arrears</a>
            <a href="{{ route('loan.book.checkoff_loans') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Checkoff</a>
            <a href="{{ route('loan.book.loans.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Create loan</a>
        </x-slot>

        <div
            x-data="{
                columnMenuOpen: false,
                storageKey: 'loan.book.loans.index.columns.v1',
                defaultCols: {
                    loanNo: true,
                    client: true,
                    contact: true,
                    officer: true,
                    disbursement: true,
                    product: true,
                    loan: true,
                    toPay: true,
                    paid: true,
                    percent: true,
                    balance: true,
                    dpd: true,
                    status: true,
                    maturity: true,
                    actions: true
                },
                cols: {},
                init() {
                    this.cols = { ...this.defaultCols };
                    try {
                        const saved = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
                        if (saved && typeof saved === 'object') {
                            Object.keys(this.defaultCols).forEach((k) => {
                                if (Object.prototype.hasOwnProperty.call(saved, k)) {
                                    this.cols[k] = !!saved[k];
                                }
                            });
                        }
                    } catch (e) {}

                    this.$watch('cols', (value) => {
                        localStorage.setItem(this.storageKey, JSON.stringify(value));
                    }, { deep: true });
                },
                visibleExportCols() {
                    return Object.keys(this.defaultCols).filter((k) => k !== 'actions' && !!this.cols[k]);
                },
                exportUrl(format) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('export', format);
                    url.searchParams.set('cols', this.visibleExportCols().join(','));
                    return `${url.pathname}?${url.searchParams.toString()}`;
                },
                openRow(url, event) {
                    const target = event?.target;
                    if (!url || (target && target.closest('a, button, input, select, textarea, form, label, summary, details'))) {
                        return;
                    }

                    window.location.href = url;
                }
            }"
        >
        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Loan #, client, product..." oninput="window.clearTimeout(this._autoSearchTimer); this._autoSearchTimer = window.setTimeout(() => this.form.requestSubmit(), 1100);" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
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
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Next step</label>
                    <select name="next_step" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        <option value="disburse" @selected(($nextStep ?? '') === 'disburse')>Disburse now</option>
                        <option value="record_payment" @selected(($nextStep ?? '') === 'record_payment')>Record payment</option>
                        <option value="sync_schedule" @selected(($nextStep ?? '') === 'sync_schedule')>Sync schedule</option>
                        <option value="arrears" @selected(($nextStep ?? '') === 'arrears')>Arrears follow-up</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Disbursed from</label>
                    <input type="date" name="disbursed_from" value="{{ $disbursedFrom ?? '' }}" onchange="this.form.requestSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Disbursed to</label>
                    <input type="date" name="disbursed_to" value="{{ $disbursedTo ?? '' }}" onchange="this.form.requestSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Maturity from</label>
                    <input type="date" name="maturity_from" value="{{ $maturityFrom ?? '' }}" onchange="this.form.requestSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Maturity to</label>
                    <input type="date" name="maturity_to" value="{{ $maturityTo ?? '' }}" onchange="this.form.requestSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
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
                    <a :href="exportUrl('csv')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a :href="exportUrl('xls')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a :href="exportUrl('pdf')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
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
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-2 sm:flex-row sm:justify-between sm:items-start">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Loan register</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Progress uses posted repayments only.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="relative" @click.outside="columnMenuOpen = false">
                        <button type="button" @click="columnMenuOpen = !columnMenuOpen" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Columns
                        </button>
                        <div x-show="columnMenuOpen" x-cloak class="absolute right-0 mt-2 z-20 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Show / hide columns</p>
                            <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.loanNo" class="rounded border-slate-300">Loan #</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.client" class="rounded border-slate-300">Client</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.contact" class="rounded border-slate-300">Contact</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.officer" class="rounded border-slate-300">Loan officer</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.disbursement" class="rounded border-slate-300">Disbursement</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.product" class="rounded border-slate-300">Product</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.loan" class="rounded border-slate-300">Loan</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.toPay" class="rounded border-slate-300">To-pay</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.paid" class="rounded border-slate-300">Paid</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.percent" class="rounded border-slate-300">Percent</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.balance" class="rounded border-slate-300">Balance</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.dpd" class="rounded border-slate-300">DPD</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.status" class="rounded border-slate-300">Status</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.maturity" class="rounded border-slate-300">Maturity</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.actions" class="rounded border-slate-300">Actions</label>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">{{ $loans->total() }} account(s)</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="loan-register-table min-w-full w-full text-xs">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th x-show="cols.loanNo" class="px-5 py-3">Loan #</th>
                            <th x-show="cols.client" class="px-5 py-3">Client</th>
                            <th x-show="cols.contact" class="px-5 py-3">Contact</th>
                            <th x-show="cols.officer" class="px-5 py-3">Loan officer</th>
                            <th x-show="cols.disbursement" class="px-5 py-3">Disbursement</th>
                            <th x-show="cols.product" class="px-5 py-3">Product</th>
                            <th x-show="cols.loan" class="px-5 py-3 text-right">Loan</th>
                            <th x-show="cols.toPay" class="px-5 py-3 text-right">To-pay</th>
                            <th x-show="cols.paid" class="px-5 py-3 text-right">Paid</th>
                            <th x-show="cols.percent" class="px-5 py-3">Percent</th>
                            <th x-show="cols.balance" class="px-5 py-3 text-right">Balance</th>
                            <th x-show="cols.dpd" class="px-5 py-3">DPD</th>
                            <th x-show="cols.status" class="px-5 py-3">Status</th>
                            <th x-show="cols.maturity" class="px-5 py-3">Maturity</th>
                            <th x-show="cols.actions" class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $loanPrincipalTotal = 0.0;
                            $loanToPayTotal = 0.0;
                            $loanPaidTotal = 0.0;
                            $loanBalanceTotal = 0.0;
                        @endphp
                        @forelse ($loans as $loan)
                            @php
                                $posted = (float) ($loan->processed_repayments_sum_amount ?? 0);
                                $paid = $posted;
                                $balance = (float) $loan->balance;
                                $remaining = max(0, $balance);
                                $totalRepayable = $paid + $remaining;
                                $progress = $totalRepayable > 0.00001 ? min(100, max(0, ($paid / $totalRepayable) * 100)) : 0;
                                $officerName = trim((string) ($loan->loanClient?->assignedEmployee?->full_name ?? ''));
                                $rowRedirectUrl = $loan->loanClient
                                    ? route('loan.clients.show', $loan->loanClient)
                                    : route('loan.book.loans.show', $loan);
                                $loanPrincipalTotal += (float) $loan->principal;
                                $loanToPayTotal += $totalRepayable;
                                $loanPaidTotal += $paid;
                                $loanBalanceTotal += $remaining;
                                $statusClasses = match ($loan->status) {
                                    \App\Models\LoanBookLoan::STATUS_ACTIVE => 'bg-emerald-100 text-emerald-700',
                                    \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT => 'bg-amber-100 text-amber-700',
                                    \App\Models\LoanBookLoan::STATUS_CLOSED => 'bg-slate-200 text-slate-700',
                                    \App\Models\LoanBookLoan::STATUS_WRITTEN_OFF => 'bg-rose-100 text-rose-700',
                                    \App\Models\LoanBookLoan::STATUS_RESTRUCTURED => 'bg-indigo-100 text-indigo-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr
                                class="cursor-pointer hover:bg-slate-50/80"
                                role="link"
                                tabindex="0"
                                @click="openRow('{{ $rowRedirectUrl }}', $event)"
                                @keydown.enter.prevent="openRow('{{ $rowRedirectUrl }}', $event)"
                                @keydown.space.prevent="openRow('{{ $rowRedirectUrl }}', $event)"
                            >
                                <td x-show="cols.loanNo" class="px-5 py-3 font-mono text-xs text-indigo-600 font-medium">{{ $loan->loan_number }}</td>
                                <td x-show="cols.client" class="px-5 py-3 font-medium text-slate-900">
                                    @if ($loan->loanClient)
                                        <a href="{{ route('loan.clients.show', $loan->loanClient) }}" class="text-[#2f4f4f] hover:underline">
                                            {{ $loan->loanClient->full_name }}
                                        </a>
                                    @else
                                        Client record missing
                                    @endif
                                </td>
                                <td x-show="cols.contact" class="px-5 py-3 text-slate-600"><x-phone-link :value="$loan->loanClient?->phone" /></td>
                                <td x-show="cols.officer" class="px-5 py-3 text-slate-600">{{ $officerName !== '' ? $officerName : '—' }}</td>
                                <td x-show="cols.disbursement" class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $loan->disbursed_at?->format('d-m-Y') ?? '—' }}</td>
                                <td x-show="cols.product" class="px-5 py-3 text-slate-600">{{ $loan->product_name }}</td>
                                <td x-show="cols.loan" class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $loan->principal, 2) }}</td>
                                <td x-show="cols.toPay" class="px-5 py-3 text-right tabular-nums font-semibold text-slate-800">{{ number_format($totalRepayable, 2) }}</td>
                                <td x-show="cols.paid" class="px-5 py-3 text-right tabular-nums text-emerald-700">
                                    <span class="font-semibold">{{ number_format($paid, 2) }}</span>
                                </td>
                                <td x-show="cols.percent" class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ number_format($progress, 1) }}%</td>
                                <td x-show="cols.balance" class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format($remaining, 2) }}</td>
                                <td x-show="cols.dpd" class="px-5 py-3 tabular-nums {{ $loan->dpd > 0 ? 'text-red-600 font-semibold' : 'text-slate-600' }}">{{ $loan->dpd }}</td>
                                <td x-show="cols.status" class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-semibold {{ $statusClasses }}">
                                        {{ str_replace('_', ' ', $loan->status) }}
                                    </span>
                                </td>
                                <td x-show="cols.maturity" class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ $loan->maturity_date?->format('d-m-Y') ?? '—' }}</td>
                                <td x-show="cols.actions" class="px-5 py-3 text-right whitespace-nowrap">
                                    <details class="relative inline-block text-left">
                                        <summary class="inline-flex cursor-pointer list-none items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                            Actions
                                        </summary>
                                        <div class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                            @if ($loan->status === \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT)
                                                <a href="{{ route('loan.book.disbursements.create', ['loan_book_loan_id' => $loan->id]) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-emerald-700 hover:bg-slate-50">Disburse</a>
                                            @endif
                                            <a href="{{ route('loan.payments.create', ['loan_book_loan_id' => $loan->id]) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-teal-700 hover:bg-slate-50">Record payment</a>
                                            <a href="{{ route('loan.payments.unposted', ['q' => $loan->loan_number]) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-teal-700 hover:bg-slate-50">Unposted</a>
                                            @if ($loan->loan_book_application_id)
                                                <form method="post" action="{{ route('loan.book.loans.sync_schedule', $loan) }}" data-swal-confirm="Sync term/rate period from linked application and recompute interest snapshot?">
                                                    @csrf
                                                    <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-amber-700 hover:bg-slate-50">Sync schedule</button>
                                                </form>
                                            @endif
                                            <form method="post" action="{{ route('loan.book.loans.rebuild_snapshot', $loan) }}" data-swal-confirm="Rebuild repayment snapshot from disbursements and processed payments?">
                                                @csrf
                                                <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">Rebuild</button>
                                            </form>
                                            <a href="{{ route('loan.book.loans.show', $loan) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                            <a href="{{ route('loan.book.loans.edit', $loan) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-indigo-600 hover:bg-slate-50">Edit</a>
                                            <form method="post" action="{{ route('loan.book.loans.destroy', $loan) }}" data-swal-confirm="Delete this loan? No disbursements or collections may exist.">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-red-600 hover:bg-slate-50">Delete</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-5 py-12 text-center text-slate-500">No loans booked yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($loans->count() > 0)
                        <tfoot class="bg-slate-100/80">
                            <tr class="border-t border-slate-200">
                                <td x-show="cols.loanNo" class="px-5 py-3 font-bold text-slate-800">Totals (this page)</td>
                                <td x-show="cols.client" class="px-5 py-3"></td>
                                <td x-show="cols.contact" class="px-5 py-3"></td>
                                <td x-show="cols.officer" class="px-5 py-3"></td>
                                <td x-show="cols.disbursement" class="px-5 py-3"></td>
                                <td x-show="cols.product" class="px-5 py-3"></td>
                                <td x-show="cols.loan" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($loanPrincipalTotal, 2) }}</td>
                                <td x-show="cols.toPay" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($loanToPayTotal, 2) }}</td>
                                <td x-show="cols.paid" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($loanPaidTotal, 2) }}</td>
                                <td x-show="cols.percent" class="px-5 py-3"></td>
                                <td x-show="cols.balance" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($loanBalanceTotal, 2) }}</td>
                                <td x-show="cols.dpd" class="px-5 py-3"></td>
                                <td x-show="cols.status" class="px-5 py-3"></td>
                                <td x-show="cols.maturity" class="px-5 py-3"></td>
                                <td x-show="cols.actions" class="px-5 py-3"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if ($loans->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $loans->withQueryString()->links() }}</div>
            @endif
        </div>
        </div>
    </x-loan.page>
</x-loan-layout>
