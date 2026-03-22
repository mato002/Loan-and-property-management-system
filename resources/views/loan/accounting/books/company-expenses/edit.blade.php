<x-loan-layout>
    <x-loan.page title="Edit expense" subtitle="">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.company_expenses.index') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl max-w-lg p-6 space-y-4">
            <form method="post" action="{{ route('loan.accounting.company_expenses.update', $row) }}">
                @csrf @method('patch')
                <div><label class="text-xs font-semibold text-slate-600">Title</label><input name="title" value="{{ old('title', $row->title) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Category</label><input name="category" value="{{ old('category', $row->category) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Amount</label><input name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $row->amount) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm tabular-nums"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Currency</label><input name="currency" value="{{ old('currency', $row->currency) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm uppercase"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Date</label><input name="expense_date" type="date" value="{{ old('expense_date', $row->expense_date->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Method</label>
                    <select name="payment_method" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['bank' => 'Bank', 'cash' => 'Cash', 'mpesa' => 'M-Pesa', 'cheque' => 'Cheque'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('payment_method', $row->payment_method) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="text-xs font-semibold text-slate-600">Reference</label><input name="reference" value="{{ old('reference', $row->reference) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"/></div>
                <div><label class="text-xs font-semibold text-slate-600">Notes</label><textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $row->notes) }}</textarea></div>
                <button type="submit" class="rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#264040]">Update</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
