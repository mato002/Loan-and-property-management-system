<x-loan-layout>
    <x-loan.page title="Income statement" subtitle="Revenue and expenses for the period (from journal lines).">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.reports.hub') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reports</a>
        </x-slot>

        <form method="get" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-lg border-slate-200 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-lg border-slate-200 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Run</button>
        </form>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800">Income</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($incomeRows as $r)
                            <tr>
                                <td class="px-5 py-2"><span class="font-mono text-xs text-slate-500">{{ $r['account']->code }}</span> {{ $r['account']->name }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ number_format($r['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold"><tr><td class="px-5 py-2">Total income</td><td class="px-5 py-2 text-right">{{ number_format($incomeTotal, 2) }}</td></tr></tfoot>
                </table>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800">Expenses</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($expenseRows as $r)
                            <tr>
                                <td class="px-5 py-2"><span class="font-mono text-xs text-slate-500">{{ $r['account']->code }}</span> {{ $r['account']->name }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ number_format($r['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold"><tr><td class="px-5 py-2">Total expenses</td><td class="px-5 py-2 text-right">{{ number_format($expenseTotal, 2) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="mt-6 rounded-xl border border-indigo-200 bg-indigo-50/60 p-5 max-w-md">
            <p class="text-xs font-semibold text-slate-600 uppercase">Net income</p>
            <p class="text-2xl font-bold tabular-nums text-slate-900 mt-1">{{ number_format($netIncome, 2) }}</p>
        </div>
    </x-loan.page>
</x-loan-layout>
