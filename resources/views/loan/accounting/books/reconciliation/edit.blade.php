<x-loan-layout>
    <x-loan.page title="Edit reconciliation" subtitle="">
        <x-slot name="actions"><a href="{{ route('loan.accounting.reconciliation.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a></x-slot>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-6 max-w-lg">
            <p class="text-xs font-semibold text-slate-600 uppercase">Book balance (GL) as of statement date</p>
            <p class="text-xl font-bold tabular-nums text-slate-900 mt-1">Dr − Cr = {{ number_format($glBalance, 2) }}</p>
            <p class="text-xs text-slate-500 mt-2">Compare to statement balance plus adjustments and outstanding items.</p>
            @php
                $stmt = (float) $row->statement_balance;
                $adj = (float) $row->adjustment_amount;
                $diff = $stmt - ($glBalance + $adj);
            @endphp
            <p class="text-sm mt-3 text-slate-700">Difference (statement − (GL + adjustment)): <span class="font-semibold tabular-nums {{ abs($diff) < 0.01 ? 'text-emerald-700' : 'text-amber-700' }}">{{ number_format($diff, 2) }}</span></p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            <form method="post" action="{{ route('loan.accounting.reconciliation.update', $row) }}">
                @csrf @method('patch')
                <div><label class="text-xs font-semibold text-slate-600">Account</label>
                    <select name="accounting_chart_account_id" required class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($cashAccounts as $a)
                            <option value="{{ $a->id }}" @selected(old('accounting_chart_account_id', $row->accounting_chart_account_id) == $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Statement date</label><input name="statement_date" type="date" value="{{ old('statement_date', $row->statement_date->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Statement balance</label><input name="statement_balance" type="number" step="0.01" value="{{ old('statement_balance', $row->statement_balance) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Adjustment</label><input name="adjustment_amount" type="number" step="0.01" value="{{ old('adjustment_amount', $row->adjustment_amount) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Outstanding items</label><textarea name="outstanding_items" rows="3" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('outstanding_items', $row->outstanding_items) }}</textarea></div>
                <div><label class="text-xs font-semibold text-slate-600">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="draft" @selected(old('status', $row->status) === 'draft')>Draft</option>
                        <option value="reconciled" @selected(old('status', $row->status) === 'reconciled')>Reconciled</option>
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $row->notes) }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Update</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
