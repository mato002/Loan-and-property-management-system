<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ route('loan.book.disbursements.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                @error('accounting')
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
                @enderror
                <div>
                    <label for="loan_book_loan_id" class="block text-xs font-semibold text-slate-600 mb-1">Loan account</label>
                    <select id="loan_book_loan_id" name="loan_book_loan_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        @foreach ($loans as $l)
                            <option value="{{ $l->id }}" @selected(old('loan_book_loan_id') == $l->id)>{{ $l->loan_number }} · {{ $l->loanClient->full_name }}</option>
                        @endforeach
                    </select>
                    @error('loan_book_loan_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reference" class="block text-xs font-semibold text-slate-600 mb-1">Reference / voucher</label>
                    <input id="reference" name="reference" value="{{ old('reference') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="method" class="block text-xs font-semibold text-slate-600 mb-1">Method</label>
                    <select id="method" name="method" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank / EFT', 'cheque' => 'Cheque'] as $v => $lab)
                            <option value="{{ $v }}" @selected(old('method') === $v)>{{ $lab }}</option>
                        @endforeach
                    </select>
                    @error('method')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="disbursed_at" class="block text-xs font-semibold text-slate-600 mb-1">Disbursement date</label>
                    <input id="disbursed_at" name="disbursed_at" type="date" value="{{ old('disbursed_at', now()->toDateString()) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('disbursed_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
