<x-loan-layout>
    <style>
        .loan-compact-table {
            table-layout: fixed;
            width: 100%;
        }

        .loan-compact-table th,
        .loan-compact-table td {
            padding: 0.45rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.15rem;
            vertical-align: top;
            word-break: break-word;
        }
    </style>

    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <form method="get" action="{{ route('loan.book.collection_sheet.index') }}" class="flex flex-wrap items-center gap-2">
                <label class="text-xs font-semibold text-slate-600">From</label>
                <input type="date" name="from" value="{{ $filterFrom }}" class="rounded-lg border-slate-200 text-sm" />
                <label class="text-xs font-semibold text-slate-600">To</label>
                <input type="date" name="to" value="{{ $filterTo }}" class="rounded-lg border-slate-200 text-sm" />
                <input type="hidden" name="q" value="{{ $q ?? '' }}">
                <input type="hidden" name="channel" value="{{ $channel ?? '' }}">
                <input type="hidden" name="per_page" value="{{ $perPage ?? 25 }}">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Go</button>
            </form>
            <a href="{{ route('loan.book.collection_mtd') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">MTD</a>
        </x-slot>

        @error('accounting')
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
        @enderror

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <input type="hidden" name="from" value="{{ $filterFrom }}">
            <input type="hidden" name="to" value="{{ $filterTo }}">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Loan #, client..." oninput="window.clearTimeout(this._autoSearchTimer); this._autoSearchTimer = window.setTimeout(() => this.form.requestSubmit(), 1100);" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Channel</label>
                    <select name="channel" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($channels ?? []) as $ch)
                            <option value="{{ $ch }}" @selected(($channel ?? '') === $ch)>{{ ucfirst($ch) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.collection_sheet.index', ['from' => $filterFrom, 'to' => $filterTo]) }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.collection_sheet.index', array_merge(request()->query(), ['from' => $filterFrom, 'to' => $filterTo, 'export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.collection_sheet.index', array_merge(request()->query(), ['from' => $filterFrom, 'to' => $filterTo, 'export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.collection_sheet.index', array_merge(request()->query(), ['from' => $filterFrom, 'to' => $filterTo, 'export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-700">Lines for {{ $filterFrom }} to {{ $filterTo }}</h2>
                    <p class="text-xs text-slate-500 mt-1">{{ $entries->total() }} receipt(s)</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="loan-compact-table min-w-full w-full text-xs">
                        <thead class="bg-slate-50 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3">#</th>
                                <th class="px-5 py-3">Client</th>
                                <th class="px-5 py-3">Contact</th>
                                <th class="px-5 py-3">Portfolio</th>
                                <th class="px-5 py-3">Branch</th>
                                <th class="px-5 py-3">Installment</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                                <th class="px-5 py-3 text-right">Accumulated</th>
                                <th class="px-5 py-3 text-right">Paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @php
                                $totalAmount = 0.0;
                                $totalAccumulated = 0.0;
                                $totalPaid = 0.0;
                            @endphp
                            @forelse ($entries as $row)
                                @php
                                    $loan = $row->loan;
                                    $termValue = max(1, (int) ($loan?->term_value ?? 1));
                                    $principal = (float) ($loan?->principal ?? 0);
                                    $balance = (float) ($loan?->balance ?? 0);
                                    $totalRepayable = max(0.01, $principal + max(0, $balance));
                                    $paidTotal = (float) ($loan?->processed_repayments_sum_amount ?? 0);
                                    $installmentIndex = $termValue > 0
                                        ? min($termValue, max(1, (int) floor(($paidTotal / $totalRepayable) * $termValue) + 1))
                                        : 1;
                                    $installmentProgress = $installmentIndex.'/'.$termValue;
                                    $accumulated = (float) ($loan?->period_collection_sum ?? 0);
                                    $portfolioName = $loan?->loanClient?->assignedEmployee?->full_name ?? '—';
                                    $totalAmount += (float) $row->amount;
                                    $totalAccumulated += $accumulated;
                                    $totalPaid += $paidTotal;
                                @endphp
                                @php
                                    $rowClient = $row->loan?->loanClient;
                                    $rowUrl = $rowClient ? route('loan.clients.show', $rowClient) : null;
                                @endphp
                                <tr
                                    class="hover:bg-slate-50/80 {{ $rowUrl ? 'cursor-pointer' : '' }}"
                                    @if ($rowUrl)
                                        role="link"
                                        tabindex="0"
                                        onclick="if (event.target.closest('a, button, input, select, textarea, form, label, summary, details')) return; window.location.href='{{ $rowUrl }}';"
                                        onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label, summary, details')) { event.preventDefault(); window.location.href='{{ $rowUrl }}'; }"
                                    @endif
                                >
                                    <td class="px-5 py-3 text-slate-500 tabular-nums">{{ (($entries->currentPage() - 1) * $entries->perPage()) + $loop->iteration }}</td>
                                    <td class="px-5 py-3 text-slate-800">{{ $row->loan?->loanClient?->full_name ?? '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600"><x-phone-link :value="$row->loan?->loanClient?->phone" /></td>
                                    <td class="px-5 py-3 text-slate-600">{{ $portfolioName }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $row->loan?->branch ?? '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $installmentProgress }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums font-medium">{{ number_format((float) $row->amount, 2) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format($accumulated, 2) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format($paidTotal, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-10 text-center text-slate-500">No receipts in this date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($entries->count() > 0)
                            <tfoot class="bg-slate-100/80">
                                <tr class="border-t border-slate-200">
                                    <td colspan="6" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-700">
                                        Totals (this page)
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">
                                        {{ number_format($totalAmount, 2) }}
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">
                                        {{ number_format($totalAccumulated, 2) }}
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">
                                        {{ number_format($totalPaid, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @if ($entries->hasPages())
                    <div class="px-5 py-3 border-t border-slate-100">{{ $entries->withQueryString()->links() }}</div>
                @endif
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-800">Add receipt</h2>
                <form method="post" action="{{ route('loan.book.collection_sheet.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="collected_on" value="{{ $filterTo }}" />
                    <div>
                        <label for="loan_book_loan_id" class="block text-xs font-semibold text-slate-600 mb-1">Loan</label>
                        <select id="loan_book_loan_id" name="loan_book_loan_id" required class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select…</option>
                            @foreach ($loans as $l)
                                <option value="{{ $l->id }}" @selected(old('loan_book_loan_id') == $l->id)>{{ $l->loan_number }} · {{ $l->loanClient?->full_name ?? 'Unknown client' }}</option>
                            @endforeach
                        </select>
                        @error('loan_book_loan_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="channel" class="block text-xs font-semibold text-slate-600 mb-1">Channel</label>
                        <select id="channel" name="channel" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank', 'checkoff' => 'Checkoff'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('channel') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="collected_by_employee_id" class="block text-xs font-semibold text-slate-600 mb-1">Received by (optional)</label>
                        <select id="collected_by_employee_id" name="collected_by_employee_id" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">—</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected(old('collected_by_employee_id') == $e->id)>{{ $e->full_name }}</option>
                            @endforeach
                        </select>
                        @error('collected_by_employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                        <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                        @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="rounded-lg border border-amber-100 bg-amber-50/80 px-3 py-2.5">
                        <label class="flex items-start gap-2 text-sm text-slate-800 cursor-pointer">
                            <input type="checkbox" name="sync_to_accounting" value="1" class="mt-0.5 rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('sync_to_accounting')) />
                            <span><span class="font-semibold">Also post to general ledger</span>
                                <span class="block text-xs text-slate-600 mt-0.5">Only tick this if you are <strong>not</strong> also recording the same cash as a processed pay-in, or the books will double-count.</span>
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="w-full inline-flex justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Post to sheet</button>
                </form>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
