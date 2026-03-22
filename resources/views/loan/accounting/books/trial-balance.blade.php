<x-loan-layout>
    <x-loan.page title="Trial balance" subtitle="Cumulative activity through the selected date.">
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

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-3">Code</th>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3 text-right">Debit</th>
                        <th class="px-5 py-3 text-right">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-5 py-2 font-mono text-xs">{{ $r['account']->code }}</td>
                            <td class="px-5 py-2 text-slate-800">{{ $r['account']->name }}</td>
                            <td class="px-5 py-2 text-right tabular-nums">{{ $r['debit'] > 0 ? number_format($r['debit'], 2) : '—' }}</td>
                            <td class="px-5 py-2 text-right tabular-nums">{{ $r['credit'] > 0 ? number_format($r['credit'], 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No journal activity through this date.</td></tr>
                    @endforelse
                </tbody>
                @if (count($rows))
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="2" class="px-5 py-3">Totals</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($sumDr, 2) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($sumCr, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-loan.page>
</x-loan-layout>
