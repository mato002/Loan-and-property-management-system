<x-loan-layout>
    <x-loan.page title="Cashflow" subtitle="Estimated movement: journals on cash-flagged accounts plus petty, utilities, paid requisitions, and approved advances.">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 mb-6">
            <form method="get" action="{{ route('loan.accounting.cashflow') }}" class="flex flex-wrap items-end gap-3">
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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold text-slate-500 uppercase">Journal (cash accounts)</p>
                <p class="text-lg font-semibold tabular-nums mt-1 {{ $journalCashNet >= 0 ? 'text-emerald-700' : 'text-red-700' }}">Dr − Cr: {{ number_format($journalCashNet, 2) }}</p>
                <p class="text-xs text-slate-500 mt-2">Positive = net debit to cash/bank.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold text-slate-500 uppercase">Petty cash net</p>
                <p class="text-lg font-semibold tabular-nums mt-1">{{ number_format($pettyNet, 2) }}</p>
                <p class="text-xs text-slate-500 mt-2">Receipts {{ number_format($pettyIn, 2) }} − disbursements {{ number_format($pettyOut, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold text-slate-500 uppercase">Operating outflows</p>
                <p class="text-lg font-semibold tabular-nums mt-1 text-red-700">−{{ number_format($utilitiesOut + $reqPaid + $advancesOut, 2) }}</p>
                <p class="text-xs text-slate-500 mt-2">Utilities, paid reqs, approved advances</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4">
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="px-5 py-2 text-slate-600">Utilities paid</td><td class="px-5 py-2 text-right tabular-nums">KES {{ number_format($utilitiesOut, 2) }}</td></tr>
                    <tr><td class="px-5 py-2 text-slate-600">Requisitions marked paid</td><td class="px-5 py-2 text-right tabular-nums">KES {{ number_format($reqPaid, 2) }}</td></tr>
                    <tr><td class="px-5 py-2 text-slate-600">Salary advances approved (by request date)</td><td class="px-5 py-2 text-right tabular-nums">KES {{ number_format($advancesOut, 2) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5">
            <p class="text-xs font-semibold text-slate-600 uppercase">Rough combined estimate</p>
            <p class="text-2xl font-semibold tabular-nums text-slate-900 mt-1">KES {{ number_format($combinedEstimate, 2) }}</p>
            <p class="text-xs text-slate-500 mt-2">Journal cash net + petty net − utilities − paid requisitions − approved advances. Adjust processes so operational data is not double-counted with journals.</p>
        </div>
    </x-loan.page>
</x-loan-layout>
