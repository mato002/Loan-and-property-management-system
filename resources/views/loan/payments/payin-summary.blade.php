<x-loan-layout>
    <x-loan.page
        title="Pay-in summary"
        subtitle="Totals for processed payments by channel over a date range."
    >
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
            <form method="get" action="{{ route('loan.payments.payin_summary') }}" class="px-5 py-4 flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="from" class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                        <input id="from" name="from" type="date" value="{{ $from }}" class="rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label for="to" class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                        <input id="to" name="to" type="date" value="{{ $to }}" class="rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label for="channel" class="block text-xs font-semibold text-slate-600 mb-1">Channel</label>
                        <input id="channel" name="channel" type="text" value="{{ $channel ?? '' }}" placeholder="All channels" class="rounded-lg border-slate-200 text-sm w-40" />
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Apply</button>
                    <a href="{{ route('loan.payments.payin_summary') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-semibold uppercase text-slate-500 mr-1">Export</span>
                    <a href="{{ route('loan.payments.payin_summary', array_merge(request()->except('export'), ['export' => 'csv'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.payments.payin_summary', array_merge(request()->except('export'), ['export' => 'xls'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.payments.payin_summary', array_merge(request()->except('export'), ['export' => 'pdf'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 mb-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total amount (processed)</p>
                <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">KES {{ number_format((float) $totals['amount'], 2) }}</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Payment count</p>
                <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1">{{ number_format($totals['count']) }}</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">By channel</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3 text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byChannel as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 text-slate-800 capitalize">{{ $row->channel }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">KES {{ number_format((float) $row->total_amount, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ number_format((int) $row->payment_count) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-12 text-center text-slate-500">No processed payments in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
