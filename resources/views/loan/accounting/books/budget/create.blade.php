<x-loan-layout>
    <x-loan.page title="Add budget line" subtitle="">
        <x-slot name="actions"><a href="{{ route('loan.accounting.budget.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a></x-slot>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            <form method="post" action="{{ route('loan.accounting.budget.store') }}">
                @csrf
                <div><label class="text-xs font-semibold text-slate-600">Fiscal year</label><input name="fiscal_year" type="number" min="2000" max="2100" value="{{ old('fiscal_year', now()->year) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Month (empty = full year window)</label><input name="month" type="number" min="1" max="12" value="{{ old('month') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="1–12"/></div>
                <div><label class="text-xs font-semibold text-slate-600">GL account (optional)</label>
                    <select name="accounting_chart_account_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        <option value="">—</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected(old('accounting_chart_account_id') == $a->id)>{{ $a->code }} · {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Branch</label><input name="branch" value="{{ old('branch') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Amount</label><input name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Label (if no account)</label><input name="label" value="{{ old('label') }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
