<x-loan-layout>
    <x-loan.page title="Expense summary" subtitle="Roll-up of operating costs from utilities, petty cash, paid requisitions, salary advances, and expense accounts in the journal.">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 mb-6">
            <form method="get" action="{{ route('loan.accounting.expense_summary') }}" class="flex flex-wrap items-end gap-3">
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
