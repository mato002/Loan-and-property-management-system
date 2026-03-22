<x-loan-layout>
    <x-loan.page
        title="Record payment"
        subtitle="Creates an unposted payment line. Post it from the unposted queue when ready."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.payments.unposted') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ route('loan.payments.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="loan_book_loan_id" class="block text-xs font-semibold text-slate-600 mb-1">Loan account (optional)</label>
                    <select id="loan_book_loan_id" name="loan_book_loan_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Unallocated…</option>
                        @foreach ($loans as $l)
                            <option value="{{ $l->id }}" @selected(old('loan_book_loan_id') == $l->id)>{{ $l->loan_number }} · {{ $l->loanClient->full_name }}</option>
                        @endforeach
                    </select>
                    @error('loan_book_loan_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="currency" class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', 'KES') }}" maxlength="8" class="w-full rounded-lg border-slate-200 text-sm uppercase" />
                    @error('currency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="channel" class="block text-xs font-semibold text-slate-600 mb-1">Channel</label>
                    <select id="channel" name="channel" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank / EFT', 'cheque' => 'Cheque'] as $v => $lab)
                            <option value="{{ $v }}" @selected(old('channel', 'mpesa') === $v)>{{ $lab }}</option>
                        @endforeach
                    </select>
                    @error('channel')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="payment_kind" class="block text-xs font-semibold text-slate-600 mb-1">Kind</label>
                    <select id="payment_kind" name="payment_kind" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="normal" @selected(old('payment_kind', 'normal') === 'normal')>Normal</option>
                        <option value="prepayment" @selected(old('payment_kind') === 'prepayment')>Prepayment</option>
                        <option value="overpayment" @selected(old('payment_kind') === 'overpayment')>Overpayment</option>
                    </select>
                    @error('payment_kind')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="mpesa_receipt_number" class="block text-xs font-semibold text-slate-600 mb-1">M-Pesa receipt (optional)</label>
                    <input id="mpesa_receipt_number" name="mpesa_receipt_number" value="{{ old('mpesa_receipt_number') }}" class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                    @error('mpesa_receipt_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="payer_msisdn" class="block text-xs font-semibold text-slate-600 mb-1">Payer MSISDN (optional)</label>
                    <input id="payer_msisdn" name="payer_msisdn" value="{{ old('payer_msisdn') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('payer_msisdn')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="transaction_at" class="block text-xs font-semibold text-slate-600 mb-1">Transaction time</label>
                    <input id="transaction_at" name="transaction_at" type="datetime-local" value="{{ old('transaction_at', now()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('transaction_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
