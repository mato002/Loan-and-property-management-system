<x-loan-layout>
    <x-loan.page title="New reconciliation" subtitle="">
        <x-slot name="actions"><a href="{{ route('loan.accounting.reconciliation.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a></x-slot>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            @if ($cashAccounts->isEmpty())
                <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-4">No cash-flagged accounts in the chart. Open <a href="{{ route('loan.accounting.chart.index') }}" class="font-semibold underline">chart of accounts</a> and mark bank/cash lines as cash accounts.</p>
            @else
            <form method="post" action="{{ route('loan.accounting.reconciliation.store') }}">
                @csrf
                <div><label class="text-xs font-semibold text-slate-600">Cash / bank account</label>
                    <select name="accounting_chart_account_id" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($cashAccounts as $a)
                            <option value="{{ $a->id }}" @selected(old('accounting_chart_account_id') == $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                        @endforeach
                    </select>
                    @error('accounting_chart_account_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Statement date</label><input name="statement_date" type="date" value="{{ old('statement_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Statement ending balance</label><input name="statement_balance" type="number" step="0.01" value="{{ old('statement_balance') }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Adjustment (optional)</label><input name="adjustment_amount" type="number" step="0.01" value="{{ old('adjustment_amount', 0) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Outstanding items</label><textarea name="outstanding_items" rows="3" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Deposits in transit, unpresented cheques…">{{ old('outstanding_items') }}</textarea></div>
                <div><label class="text-xs font-semibold text-slate-600">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                        <option value="reconciled" @selected(old('status') === 'reconciled')>Reconciled</option>
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Save</button>
            </form>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
