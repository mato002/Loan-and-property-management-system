<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Record disbursement</a>
        </x-slot>

        @error('disbursement')
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 mb-4">{{ $message }}</div>
        @enderror

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Daily disbursements</h2>
                <p class="text-xs text-slate-500">{{ optional($calendarCurrentMonth ?? null)->format('F Y') }}</p>
            </div>
            <form id="daily-disbursements-filter-form" method="get" class="mb-3 flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Year</label>
                    <select name="cal_year" class="js-auto-calendar-filter h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @for ($year = now()->year + 1; $year >= now()->year - 5; $year--)
                            <option value="{{ $year }}" @selected((int) ($calendarYear ?? now()->year) === $year)>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Month</label>
                    <select name="cal_month" class="js-auto-calendar-filter h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @for ($month = 1; $month <= 12; $month++)
                            <option value="{{ $month }}" @selected((int) ($calendarMonth ?? now()->month) === $month)>{{ \Carbon\Carbon::create(2000, $month, 1)->format('M') }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Region</label>
                    <select name="cal_region_id" class="js-auto-calendar-filter h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="0">-- Region --</option>
                        @foreach (($calendarRegionOptions ?? collect()) as $region)
                            <option value="{{ $region->id }}" @selected((int) ($calendarRegionId ?? 0) === (int) $region->id)>{{ $region->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="cal_branch_id" class="js-auto-calendar-filter h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="0">-- Branch --</option>
                        @foreach (($calendarBranchOptions ?? collect()) as $branch)
                            <option value="{{ $branch->id }}" @selected((int) ($calendarBranchId ?? 0) === (int) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Loan product</label>
                    <select name="cal_product" class="js-auto-calendar-filter h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">-- Loan Product --</option>
                        @foreach (($calendarProductOptions ?? collect()) as $productName)
                            <option value="{{ $productName }}" @selected((string) ($calendarProduct ?? '') === (string) $productName)>{{ $productName }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Apply</button>
                <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
            </form>
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-sm border border-slate-200">
                    <thead class="bg-slate-100 text-xs font-semibold text-slate-600 uppercase">
                        <tr>
                            @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dow)
                                <th class="border border-slate-200 px-3 py-2 text-center">{{ $dow }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($calendarWeeks ?? []) as $week)
                            <tr>
                                @foreach ($week as $day)
                                    @php
                                        $isCurrentMonth = (int) $day->month === (int) ($calendarMonth ?? now()->month);
                                        $dayNumber = (int) $day->day;
                                        $branchTotals = $isCurrentMonth ? (($calendarByDay[$dayNumber] ?? [])) : [];
                                        $dayTotal = array_sum($branchTotals);
                                    @endphp
                                    <td class="align-top border border-slate-200 px-2 py-2 {{ $isCurrentMonth ? 'bg-white' : 'bg-slate-50 text-slate-400' }}">
                                        <div class="mb-1 text-xs font-semibold {{ $isCurrentMonth ? 'text-slate-700' : 'text-slate-400' }}">
                                            {{ $day->format('d-m-Y') }}
                                        </div>
                                        @if ($isCurrentMonth && $branchTotals !== [])
                                            <div class="space-y-1 text-xs">
                                                @foreach ($branchTotals as $branchName => $amount)
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="truncate text-slate-700">{{ $branchName }}</span>
                                                        <span class="tabular-nums font-medium text-slate-800">{{ number_format((float) $amount, 0) }}</span>
                                                    </div>
                                                @endforeach
                                                <div class="mt-1 border-t border-slate-200 pt-1 text-right tabular-nums font-semibold text-slate-900">{{ number_format((float) $dayTotal, 0) }}</div>
                                            </div>
                                        @elseif ($isCurrentMonth)
                                            <p class="text-xs text-slate-400">—</p>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-3 sm:p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div class="w-full sm:w-auto">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Loan #, client, reference..." class="h-10 w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Method</label>
                    <select name="method" onchange="this.form.submit()" class="h-10 w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($methods ?? []) as $m)
                            <option value="{{ $m }}" @selected(($method ?? '') === $m)>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 20, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="w-full sm:w-auto sm:ml-auto flex flex-wrap items-center gap-2">
                    <a href="{{ route('loan.book.disbursements.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.disbursements.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.disbursements.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

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
                            <th class="px-5 py-3">Payout status</th>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($disbursements as $d)
                            <tr class="hover:bg-slate-50/80 @if (! $d->loan) bg-amber-50/60 @endif">
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ optional($d->disbursed_at)->format('Y-m-d') ?: '—' }}</td>
                                <td class="px-5 py-3 font-mono text-xs {{ $d->loan ? 'text-indigo-600' : 'text-amber-800' }}" @if (! $d->loan) title="loan_book_loan_id={{ $d->loan_book_loan_id }}" @endif>
                                    {{ $d->loan?->loan_number ?? __('Missing loan') }}
                                </td>
                                <td class="px-5 py-3 text-slate-800">{{ $d->loan?->loanClient?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">{{ number_format((float) $d->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $d->method }}</td>
                                <td class="px-5 py-3">
                                    @php($ps = strtolower((string) ($d->payout_status ?? 'completed')))
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $ps === 'completed' ? 'bg-emerald-100 text-emerald-700' : ($ps === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ ucfirst($ps) }}
                                    </span>
                                    @if ($ps === 'failed' && filled($d->payout_result_desc))
                                        <p class="mt-1 max-w-xs text-[11px] leading-4 text-red-700">{{ \Illuminate\Support\Str::limit((string) $d->payout_result_desc, 90) }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500 text-xs">{{ $d->reference }}</td>
                                <td class="px-5 py-3 text-xs">
                                    @if ($d->accounting_journal_entry_id)
                                        <a href="{{ route('loan.accounting.journal.show', $d->accounting_journal_entry_id) }}" class="text-indigo-600 font-semibold hover:underline">#{{ $d->accounting_journal_entry_id }}</a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.book.disbursements.show', $d) }}" class="text-slate-700 font-medium text-sm hover:underline mr-3">View</a>
                                    <form method="post" action="{{ route('loan.book.disbursements.destroy', $d) }}" class="inline" data-swal-confirm="Remove this disbursement line?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">No disbursements recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($disbursements->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $disbursements->withQueryString()->links() }}</div>
            @endif
        </div>

        <div class="mt-4 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Pending disbursement loans</h2>
                <p class="text-xs text-slate-500">{{ ($pendingLoans ?? collect())->count() }} shown</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Loan #</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse (($pendingLoans ?? collect()) as $loan)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-indigo-600">{{ $loan->loan_number }}</td>
                                <td class="px-5 py-3 text-slate-800">{{ $loan->loanClient?->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $loan->product_name }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $loan->balance, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', (string) $loan->status) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.book.disbursements.create', ['loan_book_loan_id' => $loan->id]) }}" class="text-indigo-600 font-medium text-sm hover:underline">Disburse now</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500">No pending loans found in your portfolio.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    (() => {
        const form = document.getElementById('daily-disbursements-filter-form');
        if (!form) return;

        form.querySelectorAll('.js-auto-calendar-filter').forEach((field) => {
            field.addEventListener('change', () => form.submit());
        });
    })();
</script>
