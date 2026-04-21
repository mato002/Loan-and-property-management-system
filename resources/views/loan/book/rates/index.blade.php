<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.collection_rates.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">New target</a>
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
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Branch..." @input="autoSubmitDebounced(500)" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
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
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Year</label>
                    <select name="year" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($years ?? []) as $y)
                            <option value="{{ $y }}" @selected((string) ($year ?? '') === (string) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Month</label>
                    <select name="month" @change="autoSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}" @selected((string) ($month ?? '') === (string) $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
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
                <a href="{{ route('loan.book.collection_rates.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.book.collection_rates.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.collection_rates.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.collection_rates.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total targets</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $rates->total() }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Current page</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $rates->count() }}</p>
            </div>
            <div class="rounded-xl border border-[#264040] bg-[#2f4f4f] p-4 text-white shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8db1af]">Collection setup</p>
                <p class="mt-1 text-lg font-semibold">Rates & targets</p>
                <p class="mt-1 text-xs text-[#d4e4e3]">Monthly branch targets used for planning and PAR tracking.</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Monthly targets</h2>
                <p class="text-xs text-slate-500">{{ $rates->total() }} row(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3 text-right">Disbursed Loan</th>
                            <th class="px-5 py-3 text-right">Loan+Charges</th>
                            <th class="px-5 py-3 text-right">OTC</th>
                            <th class="px-5 py-3 text-right">OC</th>
                            <th class="px-5 py-3 text-right">DD7</th>
                            <th class="px-5 py-3 text-right">CG7</th>
                            <th class="px-5 py-3 text-right">Arrears</th>
                            <th class="px-5 py-3 text-right">OTC%</th>
                            <th class="px-5 py-3 text-right">OC%</th>
                            <th class="px-5 py-3 text-right">GC%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $totals = [
                                'disbursed_loan' => 0,
                                'loan_plus_charges' => 0,
                                'otc' => 0,
                                'oc' => 0,
                                'dd7' => 0,
                                'cg7' => 0,
                                'arrears' => 0,
                            ];
                        @endphp
                        @forelse ($rates as $rate)
                            @php
                                $totals['disbursed_loan'] += (float) ($rate->disbursed_loan ?? 0);
                                $totals['loan_plus_charges'] += (float) ($rate->loan_plus_charges ?? 0);
                                $totals['otc'] += (float) ($rate->otc ?? 0);
                                $totals['oc'] += (float) ($rate->oc ?? 0);
                                $totals['dd7'] += (float) ($rate->dd7 ?? 0);
                                $totals['cg7'] += (float) ($rate->cg7 ?? 0);
                                $totals['arrears'] += (float) ($rate->arrears ?? 0);
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $rate->branch }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->disbursed_loan ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">{{ number_format((float) ($rate->loan_plus_charges ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->otc ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->oc ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->dd7 ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->cg7 ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->arrears ?? 0), 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->otc_percent ?? 0), 2) }}%</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->oc_percent ?? 0), 2) }}%</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) ($rate->gc_percent ?? 0), 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-5 py-12 text-center text-slate-500">No targets defined.</td>
                            </tr>
                        @endforelse
                        @if ($rates->count() > 0)
                            @php
                                $base = max(0.00001, (float) $totals['loan_plus_charges']);
                                $totalOtcPct = ((float) $totals['otc'] / $base) * 100;
                                $totalOcPct = ((float) $totals['oc'] / $base) * 100;
                                $totalGcPct = ((float) $totals['oc'] / $base) * 100;
                            @endphp
                            <tr class="bg-slate-50 font-semibold text-slate-700">
                                <td class="px-5 py-3">Totals</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $totals['disbursed_loan'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $totals['loan_plus_charges'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $totals['otc'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $totals['oc'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $totals['dd7'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $totals['cg7'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $totals['arrears'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($totalOtcPct, 2) }}%</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($totalOcPct, 2) }}%</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ number_format($totalGcPct, 2) }}%</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            @if ($rates->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $rates->withQueryString()->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
