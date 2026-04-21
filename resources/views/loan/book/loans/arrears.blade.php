<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">All loans</a>
        </x-slot>

        <form
            method="get"
            class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            x-data="{
                smsModalOpen: false,
                timer: null,
                autoSubmit() { this.$el.requestSubmit(); },
                autoSubmitDebounced(delay = 450) {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.$el.requestSubmit(), delay);
                }
            }"
        >
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                <div class="lg:col-span-3">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Select period From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div class="lg:col-span-3">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div class="lg:col-span-3">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Filter loans</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Loan #, client, contact..." @input="autoSubmitDebounced(500)" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div class="lg:col-span-3">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Region</label>
                    <select name="region" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($regions ?? []) as $r)
                            <option value="{{ $r }}" @selected(($region ?? '') === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $b)
                            <option value="{{ $b }}" @selected(($branch ?? '') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Loan officer</label>
                    <select name="officer" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($officers ?? []) as $off)
                            <option value="{{ $off }}" @selected(($officer ?? '') === $off)>{{ $off }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Product</label>
                    <select name="product" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($products ?? []) as $p)
                            <option value="{{ $p }}" @selected(($product ?? '') === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($statuses ?? []) as $key => $label)
                            <option value="{{ $key }}" @selected(($status ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">DPD min</label>
                    <input type="number" min="1" name="dpd_min" value="{{ $dpdMin ?? 1 }}" @input="autoSubmitDebounced(450)" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">DPD max</label>
                    <input type="number" min="1" name="dpd_max" value="{{ $dpdMax ?? '' }}" @input="autoSubmitDebounced(450)" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm" placeholder="Any">
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" @change="autoSubmit()" class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 20, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.loan_arrears') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" @click="smsModalOpen = true" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Send SMS</button>
                    <a href="{{ route('loan.book.loan_arrears', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.loan_arrears', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.loan_arrears', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>

            <div
                x-show="smsModalOpen"
                x-cloak
                x-transition.opacity
                @keydown.escape.window="smsModalOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4"
            >
                <div @click.away="smsModalOpen = false" class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-2xl">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-xl font-semibold text-slate-800">Send SMS to {{ $loans->total() }} Clients</h3>
                        <button type="button" @click="smsModalOpen = false" class="text-2xl leading-none text-slate-400 hover:text-red-500">&times;</button>
                    </div>
                    <p class="mb-4 text-sm text-slate-600">
                        Use words <strong>CLIENT</strong>, <strong>IDNO</strong>, <strong>ARREARS</strong> &amp; <strong>STARTDAY</strong> to pick client name, id no, arrears amount &amp; fall date respectively.
                    </p>
                    <div>
                        <input type="hidden" form="arrearsSmsForm" name="q" value="{{ $q ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="branch" value="{{ $branch ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="region" value="{{ $region ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="officer" value="{{ $officer ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="product" value="{{ $product ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="status" value="{{ $status ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="from" value="{{ $from ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="to" value="{{ $to ?? '' }}">
                        <input type="hidden" form="arrearsSmsForm" name="dpd_min" value="{{ $dpdMin ?? 1 }}">
                        <input type="hidden" form="arrearsSmsForm" name="dpd_max" value="{{ $dpdMax ?? '' }}">
                        <div class="mb-2">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Type Message</label>
                            <textarea form="arrearsSmsForm" name="message" rows="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-[#2f4f4f] focus:outline-none">Dear CLIENT,
Kindly pay your arrears that have accumulated to ARREARS from STARTDAY using A/C IDNO to avoid your securities being auctioned. Query 0759731174.</textarea>
                        </div>
                        <p class="mb-4 text-xs text-slate-500">160 chars, 1 Msgg</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Arrears to Pick</label>
                                <select form="arrearsSmsForm" name="arrears_pick" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                                    <option value="period">Period Arrears</option>
                                    <option value="accumulated">Accumulated</option>
                                    <option value="total">Total Balance</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Send Sample to</label>
                                <select form="arrearsSmsForm" name="sample_to" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                                    <option value="">-- Select --</option>
                                    @foreach (($sampleRecipients ?? []) as $recipient)
                                        <option value="{{ $recipient['phone'] }}">{{ $recipient['name'] }} ({{ $recipient['phone'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <button type="submit" form="arrearsSmsForm" class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Send</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <form id="arrearsSmsForm" method="post" action="{{ route('loan.book.loan_arrears.send_sms') }}" class="hidden">
            @csrf
        </form>

        <div
            class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden"
            x-data="{
                columnMenuOpen: false,
                cols: {
                    rowNo: true,
                    client: true,
                    contact: true,
                    branch: true,
                    officer: true,
                    loan: true,
                    disbursed: true,
                    cycles: true,
                    pArrears: true,
                    accumulated: true,
                    installment: true,
                    fallDate: true,
                    days: true,
                    totalBalance: true
                }
            }"
        >
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Loan arrears register</h2>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="relative" @click.outside="columnMenuOpen = false">
                        <button type="button" @click="columnMenuOpen = !columnMenuOpen" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Columns
                        </button>
                        <div x-show="columnMenuOpen" x-cloak class="absolute right-0 mt-2 z-20 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Show / hide columns</p>
                            <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.rowNo" class="rounded border-slate-300">#</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.client" class="rounded border-slate-300">Client</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.contact" class="rounded border-slate-300">Contact</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.branch" class="rounded border-slate-300">Branch</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.officer" class="rounded border-slate-300">Loan officer</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.loan" class="rounded border-slate-300">Loan</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.disbursed" class="rounded border-slate-300">Disbursed</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.cycles" class="rounded border-slate-300">Cycles</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.pArrears" class="rounded border-slate-300">P.Arrears</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.accumulated" class="rounded border-slate-300">Accumulated</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.installment" class="rounded border-slate-300">Installment</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.fallDate" class="rounded border-slate-300">Fall Date</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.days" class="rounded border-slate-300">Days</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.totalBalance" class="rounded border-slate-300">T.Bal</label>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ $loans->total() }} loan(s)</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th x-show="cols.rowNo" class="px-5 py-3">#</th>
                            <th x-show="cols.client" class="px-5 py-3">Client</th>
                            <th x-show="cols.contact" class="px-5 py-3">Contact</th>
                            <th x-show="cols.branch" class="px-5 py-3">Branch</th>
                            <th x-show="cols.officer" class="px-5 py-3">Loan officer</th>
                            <th x-show="cols.loan" class="px-5 py-3 text-right">Loan</th>
                            <th x-show="cols.disbursed" class="px-5 py-3">Disbursement</th>
                            <th x-show="cols.cycles" class="px-5 py-3">Cycles</th>
                            <th x-show="cols.pArrears" class="px-5 py-3 text-right">P.Arrears</th>
                            <th x-show="cols.accumulated" class="px-5 py-3 text-right">Accumulated</th>
                            <th x-show="cols.installment" class="px-5 py-3">Installment</th>
                            <th x-show="cols.fallDate" class="px-5 py-3">Fall Date</th>
                            <th x-show="cols.days" class="px-5 py-3">Days</th>
                            <th x-show="cols.totalBalance" class="px-5 py-3 text-right">T.Bal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($loans as $loan)
                            @php
                                $termValue = max(1, (int) ($loan->term_value ?? 1));
                                $paid = (float) ($loan->processed_repayments_sum_amount ?? 0);
                                $totalBalance = (float) ($loan->balance ?? 0);
                                $periodicArrears = $termValue > 0 ? max(0, $totalBalance / $termValue) : $totalBalance;
                                $loanAmount = (float) ($loan->principal ?? 0);
                                $totalRepayable = max(0.01, $loanAmount + max(0, $totalBalance));
                                $installmentNo = min($termValue, max(1, (int) floor(($paid / $totalRepayable) * $termValue) + 1));
                                $fallDate = $loan->disbursed_at ? $loan->disbursed_at->copy()->addDays((int) ($loan->dpd ?? 0)) : null;
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td x-show="cols.rowNo" class="px-5 py-3 text-slate-500 tabular-nums">{{ (($loans->currentPage() - 1) * $loans->perPage()) + $loop->iteration }}</td>
                                <td x-show="cols.client" class="px-5 py-3 font-medium text-slate-900">{{ $loan->loanClient?->full_name ?? '—' }}</td>
                                <td x-show="cols.contact" class="px-5 py-3 text-slate-600"><x-phone-link :value="$loan->loanClient?->phone" /></td>
                                <td x-show="cols.branch" class="px-5 py-3 text-slate-500">{{ $loan->branch ?? '—' }}</td>
                                <td x-show="cols.officer" class="px-5 py-3 text-slate-600">{{ $loan->loanClient?->assignedEmployee?->full_name ?? '—' }}</td>
                                <td x-show="cols.loan" class="px-5 py-3 text-right tabular-nums">{{ number_format($loanAmount, 2) }}</td>
                                <td x-show="cols.disbursed" class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ optional($loan->disbursed_at)->format('d-m-Y') ?? '—' }}</td>
                                <td x-show="cols.cycles" class="px-5 py-3 tabular-nums text-slate-700">{{ $termValue }}</td>
                                <td x-show="cols.pArrears" class="px-5 py-3 text-right tabular-nums">{{ number_format($periodicArrears, 2) }}</td>
                                <td x-show="cols.accumulated" class="px-5 py-3 text-right tabular-nums">{{ number_format($paid, 2) }}</td>
                                <td x-show="cols.installment" class="px-5 py-3 tabular-nums text-slate-700">{{ $installmentNo }}/{{ $termValue }}</td>
                                <td x-show="cols.fallDate" class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ optional($fallDate)->format('d-m-Y') ?? '—' }}</td>
                                <td x-show="cols.days" class="px-5 py-3 font-semibold text-red-600 tabular-nums">{{ $loan->dpd }}</td>
                                <td x-show="cols.totalBalance" class="px-5 py-3 text-right tabular-nums">{{ number_format($totalBalance, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-5 py-12 text-center text-slate-500">No arrears in the register — great job.</td>
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
