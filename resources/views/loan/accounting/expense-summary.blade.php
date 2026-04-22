<x-loan-layout>
    <x-loan.page title="Expense summary" subtitle="Roll-up of operating costs from utilities, petty cash, paid requisitions, salary advances, and expense accounts in the journal.">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 mb-6">
            <form method="get" action="{{ route('loan.accounting.expense_summary') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="year" class="block text-xs font-semibold text-slate-600 mb-1">Year</label>
                    <select id="year" name="year" class="rounded-lg border-slate-200 text-sm">
                        @for ($y = now()->year + 1; $y >= now()->year - 5; $y--)
                            <option value="{{ $y }}" @selected((int) ($year ?? now()->year) === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="month" class="block text-xs font-semibold text-slate-600 mb-1">Month</label>
                    <select id="month" name="month" class="rounded-lg border-slate-200 text-sm">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) ($month ?? now()->month) === $m)>{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="from" class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                    <input id="from" name="from" type="date" value="{{ $from }}" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label for="to" class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                    <input id="to" name="to" type="date" value="{{ $to }}" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Apply</button>
            </form>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700">Daily company expenses</h2>
                <p class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} - {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase tracking-wide">
                        <tr>
                            @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dayName)
                                <th class="px-3 py-2 text-left font-semibold border-b border-slate-200">{{ $dayName }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($weeks ?? []) as $week)
                            <tr>
                                @foreach ($week as $cell)
                                    <td class="align-top border-b border-r border-slate-100 p-2 {{ $cell['in_range'] ? 'bg-white' : 'bg-slate-50 text-slate-400' }}">
                                        <p class="text-[11px] font-semibold {{ $cell['in_range'] ? 'text-slate-700' : 'text-slate-400' }}">
                                            {{ $cell['date']->format('d-m-Y') }}
                                        </p>
                                        @if ($cell['in_range'])
                                            <div class="mt-1 space-y-0.5">
                                                @foreach ($cell['breakdown'] as $label => $amount)
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="truncate text-[11px] text-slate-600">{{ $label }}</span>
                                                        <span class="tabular-nums text-[11px] font-medium text-slate-700">{{ number_format((float) $amount, 0) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-1 border-t border-slate-200 pt-1 flex items-center justify-between">
                                                <span class="text-[11px] font-semibold text-slate-700">Total</span>
                                                <span class="tabular-nums text-[11px] font-bold text-slate-900">{{ number_format((float) $cell['total'], 0) }}</span>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Expense totals by source</h2>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="px-5 py-3 text-slate-700">Utility payments</td>
                        <td class="px-5 py-3 text-right tabular-nums font-medium">KES {{ number_format($utilities, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 text-slate-700">Petty cash disbursements</td>
                        <td class="px-5 py-3 text-right tabular-nums font-medium">KES {{ number_format($pettyOut, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 text-slate-700">Paid requisitions</td>
                        <td class="px-5 py-3 text-right tabular-nums font-medium">KES {{ number_format($requisitionsPaid, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 text-slate-700">Salary advances (approved / settled, by request date)</td>
                        <td class="px-5 py-3 text-right tabular-nums font-medium">KES {{ number_format($advances, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 text-slate-700">Journal — expense accounts (net Dr − Cr)</td>
                        <td class="px-5 py-3 text-right tabular-nums font-medium">KES {{ number_format($journalExpense, 2) }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-slate-50 font-semibold text-slate-900">
                    <tr>
                        <td class="px-5 py-3">Total (components may overlap if you also journal the same spend)</td>
                        <td class="px-5 py-3 text-right tabular-nums">KES {{ number_format($total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-xs text-slate-500 max-w-2xl">The total sums all categories for a quick dashboard figure. For audit-ready reporting, post operational spend through the journal only, or use this view as a checklist rather than a single P&amp;L line.</p>
    </x-loan.page>
</x-loan-layout>
