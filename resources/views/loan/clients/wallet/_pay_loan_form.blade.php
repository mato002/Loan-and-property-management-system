@php
    /** @var \App\Models\LoanClient $loan_client */
    /** @var \Illuminate\Support\Collection<int, \App\Models\LoanBookLoan> $payLoanLoans */
    $walletModel = $loan_client->wallet;
@endphp
<div class="space-y-4">
    @if ($errors->has('wallet'))
        <p class="text-sm text-red-600">{{ $errors->first('wallet') }}</p>
    @endif

    @if (! $walletModel || ! $walletModel->isActive())
        <p class="text-sm text-amber-700">Wallet must be active to pay from balance.</p>
    @elseif($payLoanLoans->isEmpty())
        <p class="text-sm text-amber-700">No eligible loans with an outstanding balance.</p>
    @else
        <p class="text-sm text-slate-600">Available wallet balance: <strong class="text-slate-900">KSh {{ number_format((float) $walletModel->balance, 2) }}</strong></p>
        <form method="post" action="{{ route('loan.clients.wallet.pay_loan.store', $loan_client) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="form_context" value="pay_loan" />
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Loan</label>
                <select name="loan_book_loan_id" class="w-full rounded-lg border-slate-200 text-sm @error('loan_book_loan_id') border-red-500 @enderror" required>
                    @foreach ($payLoanLoans as $loan)
                        <option value="{{ $loan->id }}" @selected((string) old('loan_book_loan_id') === (string) $loan->id)>
                            {{ $loan->loan_number }} — outstanding KSh {{ number_format((float) $loan->balance, 2) }}
                        </option>
                    @endforeach
                </select>
                @error('loan_book_loan_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $walletModel->balance }}" value="{{ old('amount') }}" class="w-full rounded-lg border-slate-200 text-sm @error('amount') border-red-500 @enderror" required />
                @error('amount')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Payment kind</label>
                <select name="payment_kind" class="w-full rounded-lg border-slate-200 text-sm @error('payment_kind') border-red-500 @enderror">
                    <option value="normal" @selected(old('payment_kind', 'normal') === 'normal')>Normal</option>
                    <option value="prepayment" @selected(old('payment_kind') === 'prepayment')>Prepayment</option>
                </select>
                @error('payment_kind')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Post repayment from wallet</button>
        </form>
    @endif
</div>
