<x-loan-layout>
    <x-loan.page title="Budget report" subtitle="Budget vs journal actuals (income/expense style movement).">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.budget.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Budget lines</a>
            <a href="{{ route('loan.accounting.books') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Books hub</a>
        </x-slot>

        <form method="get" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Fiscal year</label>
                <input type="number" name="fiscal_year" value="{{ $year }}" min="2000" max="2100" class="rounded-lg border-slate-200 text-sm w-28"/>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Branch filter</label>
                <input type="text" name="branch" value="{{ $branch }}" placeholder="optional" class="rounded-lg border-slate-200 text-sm w-40"/>
            </div>
            <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Run</button>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase text-left">
                    <tr>
                        <th class="px-5 py-3">Budget</th>
                        <th class="px-5 py-3 text-right">Target</th>
                        <th class="px-5 py-3 text-right">Actual (journal)</th>
                        <th class="px-5 py-3 text-right">Variance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-5 py-3">
                                <span class="font-medium text-slate-800">{{ $r['budget']->fiscal_year }} @if($r['budget']->month)/ M{{ $r['budget']->month }} @endif</span>
                                @if ($r['budget']->branch)<span class="text-xs text-slate-500 block">{{ $r['budget']->branch }}</span>@endif
                                @if ($r['budget']->account)<span class="text-xs font-mono text-slate-600 block">{{ $r['budget']->account->code }} {{ $r['budget']->account->name }}</span>
                                @elseif($r['budget']->label)<span class="text-xs block">{{ $r['budget']->label }}</span>@endif
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $r['budget']->amount, 2) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ number_format($r['actual'], 2) }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-medium {{ $r['variance'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ number_format($r['variance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">No budget lines for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-500 mt-4">Lines without a linked GL account show zero actual until you attach an account.</p>
    </x-loan.page>
</x-loan-layout>
