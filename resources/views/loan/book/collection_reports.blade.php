<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions"></x-slot>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ ($reportMode ?? 'detail') === 'branch' ? 'Branches in result' : 'Rows in result' }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ ($reportMode ?? 'detail') === 'branch' ? (method_exists($byBranch, 'count') ? $byBranch->count() : 0) : ($detailRows->count() ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Period</p>
                <p class="mt-1 text-base font-semibold text-slate-900">{{ $from }} → {{ $to }}</p>
            </div>
            <div class="rounded-xl border border-[#264040] bg-[#2f4f4f] p-4 text-white shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8db1af]">Collections report</p>
                <p class="mt-1 text-lg font-semibold">{{ ($reportMode ?? 'detail') === 'branch' ? 'By branch' : 'Detailed rows' }}</p>
                <p class="mt-1 text-xs text-[#d4e4e3]">{{ ($reportMode ?? 'detail') === 'branch' ? 'Receipt lines and totals aggregated by branch.' : 'Client-level collection, arrears, paid and balance metrics.' }}</p>
            </div>
        </div>

        <form
            method="get"
            action="{{ route('loan.book.collection_reports') }}"
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
                    <label class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                    <input type="date" name="from" value="{{ $from }}" @change="autoSubmit()" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                    <input type="date" name="to" value="{{ $to }}" @change="autoSubmit()" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Branch</label>
                    <select name="branch" @change="autoSubmit()" class="rounded-lg border-slate-200 text-sm min-w-[10rem]">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $b)
                            <option value="{{ $b }}" @selected(($branch ?? '') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Loan, client, phone, branch..." @input="autoSubmitDebounced(500)" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Report view</label>
                    <select name="report_mode" @change="autoSubmit()" class="rounded-lg border-slate-200 text-sm">
                        <option value="detail" @selected(($reportMode ?? 'detail') === 'detail')>Detailed rows</option>
                        <option value="branch" @selected(($reportMode ?? 'detail') === 'branch')>Collection by branch</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Per page</label>
                    <select name="per_page" @change="autoSubmit()" class="rounded-lg border-slate-200 text-sm">
                        @foreach ([10, 20, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Apply</button>
                <a href="{{ route('loan.book.collection_reports') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.collection_reports', array_merge(request()->query(), ['export' => 'csv'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.collection_reports', array_merge(request()->query(), ['export' => 'xls'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.collection_reports', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        @if (($reportMode ?? 'detail') === 'branch')
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden" x-data="{ columnMenuOpen: false, cols: { rowNo: true, branch: true, lines: true, total: true } }">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">Collections by branch</h2>
                <div class="flex items-center gap-2">
                    <p class="text-xs text-slate-500 mt-1">{{ $from }} → {{ $to }}</p>
                    <div class="relative" @click.outside="columnMenuOpen = false">
                        <button type="button" @click="columnMenuOpen = !columnMenuOpen" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Columns
                        </button>
                        <div x-show="columnMenuOpen" x-cloak class="absolute right-0 mt-2 z-20 w-56 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Show / hide columns</p>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.rowNo" class="rounded border-slate-300">#</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.branch" class="rounded border-slate-300">Branch</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.lines" class="rounded border-slate-300">Lines</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.total" class="rounded border-slate-300">Total</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th x-show="cols.rowNo" class="px-5 py-3">#</th>
                            <th x-show="cols.branch" class="px-5 py-3">Branch</th>
                            <th x-show="cols.lines" class="px-5 py-3 text-right">Lines</th>
                            <th x-show="cols.total" class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $branchLinesTotal = 0;
                            $branchAmountTotal = 0.0;
                        @endphp
                        @forelse ($byBranch as $row)
                            @php
                                $branchLinesTotal += (int) $row->receipt_count;
                                $branchAmountTotal += (float) $row->total;
                            @endphp
                            @php
                                $branchDrillUrl = route('loan.book.collection_reports', array_merge(request()->query(), [
                                    'report_mode' => 'detail',
                                    'branch' => (string) ($row->branch ?? ''),
                                    'page' => 1,
                                ]));
                            @endphp
                            <tr
                                class="cursor-pointer hover:bg-slate-50/80"
                                role="link"
                                tabindex="0"
                                @click="window.location.href = @js($branchDrillUrl)"
                                @keydown.enter.prevent="window.location.href = @js($branchDrillUrl)"
                                @keydown.space.prevent="window.location.href = @js($branchDrillUrl)"
                            >
                                <td x-show="cols.rowNo" class="px-5 py-3 text-slate-500 tabular-nums">{{ (($byBranch->currentPage() - 1) * $byBranch->perPage()) + $loop->iteration }}</td>
                                <td x-show="cols.branch" class="px-5 py-3 font-medium text-slate-900">{{ $row->branch ?? '—' }}</td>
                                <td x-show="cols.lines" class="px-5 py-3 text-right tabular-nums text-slate-600">{{ (int) $row->receipt_count }}</td>
                                <td x-show="cols.total" class="px-5 py-3 text-right tabular-nums font-semibold text-slate-900">{{ number_format((float) $row->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500">No receipts in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($byBranch->count() > 0)
                        <tfoot class="bg-slate-100/80">
                            <tr class="border-t border-slate-200">
                                <td x-show="cols.rowNo" class="px-5 py-3 text-slate-600">—</td>
                                <td x-show="cols.branch" class="px-5 py-3 font-bold text-slate-800">Totals (this page)</td>
                                <td x-show="cols.lines" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($branchLinesTotal) }}</td>
                                <td x-show="cols.total" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($branchAmountTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if (method_exists($byBranch, 'hasPages') && $byBranch->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $byBranch->withQueryString()->links() }}</div>
            @endif
        </div>
        @else
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden" x-data="{ columnMenuOpen: false, cols: { rowNo: true, client: true, contact: true, portfolio: true, branch: true, collection: true, arrears: true, paid: true, balance: true } }">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Collection detail</h2>
                <div class="flex items-center gap-2">
                    <p class="text-xs text-slate-500">{{ $detailRows->total() }} row(s)</p>
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
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.portfolio" class="rounded border-slate-300">Portfolio</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.branch" class="rounded border-slate-300">Branch</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.collection" class="rounded border-slate-300">Collection</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.arrears" class="rounded border-slate-300">Arrears</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.paid" class="rounded border-slate-300">Paid</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.balance" class="rounded border-slate-300">Balance</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th x-show="cols.rowNo" class="px-5 py-3">#</th>
                            <th x-show="cols.client" class="px-5 py-3">Client</th>
                            <th x-show="cols.contact" class="px-5 py-3">Contact</th>
                            <th x-show="cols.portfolio" class="px-5 py-3">Portfolio</th>
                            <th x-show="cols.branch" class="px-5 py-3">Branch</th>
                            <th x-show="cols.collection" class="px-5 py-3 text-right">Collection</th>
                            <th x-show="cols.arrears" class="px-5 py-3 text-right">Arrears</th>
                            <th x-show="cols.paid" class="px-5 py-3 text-right">Paid</th>
                            <th x-show="cols.balance" class="px-5 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $detailCollectionTotal = 0.0;
                            $detailArrearsTotal = 0.0;
                            $detailPaidTotal = 0.0;
                            $detailBalanceTotal = 0.0;
                        @endphp
                        @forelse ($detailRows as $row)
                            @php
                                $detailCollectionTotal += (float) ($row->collection_total ?? 0);
                                $detailArrearsTotal += (float) ($row->dpd ?? 0);
                                $detailPaidTotal += (float) ($row->paid_total ?? 0);
                                $detailBalanceTotal += (float) ($row->balance ?? 0);
                            @endphp
                            @php
                                $detailRowUrl = !empty($row->client_id) ? route('loan.clients.show', (int) $row->client_id) : null;
                            @endphp
                            <tr
                                class="hover:bg-slate-50/80 {{ $detailRowUrl ? 'cursor-pointer' : '' }}"
                                @if ($detailRowUrl)
                                    role="link"
                                    tabindex="0"
                                    @click="window.location.href = @js($detailRowUrl)"
                                    @keydown.enter.prevent="window.location.href = @js($detailRowUrl)"
                                    @keydown.space.prevent="window.location.href = @js($detailRowUrl)"
                                @endif
                            >
                                <td x-show="cols.rowNo" class="px-5 py-3 text-slate-500 tabular-nums">{{ (($detailRows->currentPage() - 1) * $detailRows->perPage()) + $loop->iteration }}</td>
                                <td x-show="cols.client" class="px-5 py-3 font-medium text-slate-900">{{ trim((string) ($row->client_name ?? '')) !== '' ? $row->client_name : '—' }}</td>
                                <td x-show="cols.contact" class="px-5 py-3 text-slate-600"><x-phone-link :value="$row->client_phone" /></td>
                                <td x-show="cols.portfolio" class="px-5 py-3 text-slate-600">{{ trim((string) ($row->portfolio_name ?? '')) !== '' ? $row->portfolio_name : '—' }}</td>
                                <td x-show="cols.branch" class="px-5 py-3 text-slate-600">{{ $row->branch ?? '—' }}</td>
                                <td x-show="cols.collection" class="px-5 py-3 text-right tabular-nums font-semibold text-slate-900">{{ number_format((float) ($row->collection_total ?? 0), 2) }}</td>
                                <td x-show="cols.arrears" class="px-5 py-3 text-right tabular-nums {{ (int) ($row->dpd ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-slate-600' }}">{{ number_format((float) ($row->dpd ?? 0), 0) }}</td>
                                <td x-show="cols.paid" class="px-5 py-3 text-right tabular-nums text-emerald-700">{{ number_format((float) ($row->paid_total ?? 0), 2) }}</td>
                                <td x-show="cols.balance" class="px-5 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) ($row->balance ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">No detailed rows in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($detailRows->count() > 0)
                        <tfoot class="bg-slate-100/80">
                            <tr class="border-t border-slate-200">
                                <td x-show="cols.rowNo" class="px-5 py-3 text-slate-600">—</td>
                                <td x-show="cols.client" class="px-5 py-3 font-bold text-slate-800">Totals (this page)</td>
                                <td x-show="cols.contact" class="px-5 py-3"></td>
                                <td x-show="cols.portfolio" class="px-5 py-3"></td>
                                <td x-show="cols.branch" class="px-5 py-3"></td>
                                <td x-show="cols.collection" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($detailCollectionTotal, 2) }}</td>
                                <td x-show="cols.arrears" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($detailArrearsTotal, 0) }}</td>
                                <td x-show="cols.paid" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($detailPaidTotal, 2) }}</td>
                                <td x-show="cols.balance" class="px-5 py-3 text-right tabular-nums font-bold text-slate-900">{{ number_format($detailBalanceTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if ($detailRows->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $detailRows->withQueryString()->links() }}</div>
            @endif
        </div>
        @endif
    </x-loan.page>
</x-loan-layout>
