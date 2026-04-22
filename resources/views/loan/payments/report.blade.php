<x-loan-layout>
    <x-loan.page
        title="Payments report"
        subtitle="Filter and export the payment register."
    >
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
            <form method="get" action="{{ route('loan.payments.report') }}" class="px-5 py-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 items-end [&>div:last-child]:col-span-full">
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any</option>
                        <option value="unposted" @selected(request('status') === 'unposted')>Unposted</option>
                        <option value="processed" @selected(request('status') === 'processed')>Processed</option>
                        <option value="reversed" @selected(request('status') === 'reversed')>Reversed</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div>
                    <label for="kind" class="block text-xs font-semibold text-slate-600 mb-1">Kind</label>
                    <select id="kind" name="kind" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any</option>
                        <option value="normal" @selected(request('kind') === 'normal')>Normal</option>
                        <option value="prepayment" @selected(request('kind') === 'prepayment')>Prepayment</option>
                        <option value="overpayment" @selected(request('kind') === 'overpayment')>Overpayment</option>
                        <option value="merged" @selected(request('kind') === 'merged')>Merged</option>
                        <option value="c2b_reversal" @selected(request('kind') === 'c2b_reversal')>C2B reversal</option>
                    </select>
                </div>
                <div>
                    <label for="channel" class="block text-xs font-semibold text-slate-600 mb-1">Channel</label>
                    <input id="channel" name="channel" value="{{ request('channel') }}" placeholder="e.g. mpesa" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label for="from" class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                    <input id="from" name="from" type="date" value="{{ request('from') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label for="to" class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                    <input id="to" name="to" type="date" value="{{ request('to') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label for="per_page" class="block text-xs font-semibold text-slate-600 mb-1">Per page</label>
                    <select id="per_page" name="per_page" class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ([10, 20, 25, 30, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 30) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2 justify-between w-full">
                    <div class="flex flex-wrap items-end gap-2">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                        <a href="{{ route('loan.payments.report') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase text-slate-500 mr-1">Export</span>
                        <a href="{{ route('loan.payments.report', array_merge(request()->except('export'), ['export' => 'csv'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                        <a href="{{ route('loan.payments.report', array_merge(request()->except('export'), ['export' => 'xls'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                        <a href="{{ route('loan.payments.report', array_merge(request()->except('export'), ['export' => 'pdf'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">Results</h2>
                <p class="text-xs text-slate-500">{{ $payments->total() }} row(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Loan</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Kind</th>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Posted by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $p)
                            @php
                                $paymentsReportRowUrl = $p->loan?->loanClient
                                    ? route('loan.clients.show', $p->loan->loanClient)
                                    : null;
                            @endphp
                            <tr
                                class="hover:bg-slate-50/80 {{ $paymentsReportRowUrl ? 'cursor-pointer' : '' }}"
                                @if ($paymentsReportRowUrl)
                                    role="link"
                                    tabindex="0"
                                    onclick="if (event.target.closest('a, button, input, select, textarea, form, label, summary, details')) return; window.location.href='{{ $paymentsReportRowUrl }}';"
                                    onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label, summary, details')) { event.preventDefault(); window.location.href='{{ $paymentsReportRowUrl }}'; }"
                                @endif
                            >
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $p->reference }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    @if ($p->loan)
                                        <a href="{{ route('loan.book.loans.show', $p->loan) }}" class="text-indigo-600 hover:underline">
                                            {{ $p->loan->loan_number }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    @if ($p->loan?->loanClient)
                                        <a href="{{ route('loan.clients.show', $p->loan->loanClient) }}" class="text-[#2f4f4f] hover:underline">
                                            {{ $p->loan->loanClient->full_name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $p->channel }}</td>
                                <td class="px-5 py-3 text-slate-600 capitalize">{{ $p->status }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ str_replace('_', ' ', $p->payment_kind) }}</td>
                                <td class="px-5 py-3 text-slate-600 whitespace-nowrap">{{ optional($p->transaction_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 text-xs">{{ $p->postedByUser?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-12 text-center text-slate-500">No rows match the filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $payments->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
