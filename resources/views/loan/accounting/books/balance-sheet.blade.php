<x-loan-layout>
    <x-loan.page title="Balance sheet" subtitle="Point-in-time view from the ledger (interpret with your auditor if opening balances predate the system).">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.reports.hub') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reports</a>
        </x-slot>

        <form method="get" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">As of</label>
                <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="rounded-lg border-slate-200 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Run</button>
        </form>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold">Assets</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @foreach ($assets as $r)
                            <tr>
                                <td class="px-5 py-2"><span class="font-mono text-xs text-slate-500">{{ $r['account']->code }}</span> {{ $r['account']->name }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ number_format($r['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold"><tr><td class="px-5 py-2">Total assets</td><td class="px-5 py-2 text-right">{{ number_format($totalAssets, 2) }}</td></tr></tfoot>
                </table>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold">Liabilities</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @foreach ($liabilities as $r)
                            <tr><td class="px-5 py-2 font-mono text-xs text-slate-500">{{ $r['account']->code }}</td><td class="px-5 py-2">{{ $r['account']->name }}</td><td class="px-5 py-2 text-right tabular-nums">{{ number_format($r['amount'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold"><tr><td colspan="2" class="px-5 py-2">Total liabilities</td><td class="px-5 py-2 text-right">{{ number_format($totalLiabilities, 2) }}</td></tr></tfoot>
                </table>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b font-semibold">Equity</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @foreach ($equity as $r)
                            <tr>
                                <td class="px-5 py-2"><span class="font-mono text-xs text-slate-500">{{ $r['account']->code }}</span> {{ $r['account']->name }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ number_format($r['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="text-slate-700">
                            <td class="px-5 py-2">Current year result (P&amp;L YTD)</td>
                            <td class="px-5 py-2 text-right tabular-nums font-medium">{{ number_format($netIncomeYtd, 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold"><tr><td class="px-5 py-2">Total equity (incl. YTD)</td><td class="px-5 py-2 text-right">{{ number_format($totalEquity + $netIncomeYtd, 2) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
        <p class="text-xs text-slate-500 mt-4 max-w-2xl">Check: assets ({{ number_format($totalAssets, 2) }}) vs liabilities + equity incl. YTD ({{ number_format($totalLiabilities + $totalEquity + $netIncomeYtd, 2) }}). Differences usually mean unrecorded opening balances or off–balance-sheet items.</p>
    </x-loan.page>
</x-loan-layout>
