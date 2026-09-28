<x-loan-layout>
    <x-loan.page
        title="STK repayment"
        subtitle="Send an M-Pesa STK Push to the borrower's phone. On success, a payment is created (and may auto-post)."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.payments.create') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Manual payment</a>
            <a href="{{ route('loan.payments.unposted') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unposted</a>
        </x-slot>

        @unless ($stkConfigured ?? false)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Daraja STK is not configured. Missing: {{ implode(', ', $stkMissing ?? []) }}. Update <code class="font-mono text-xs">.env</code> then run <code class="font-mono text-xs">php artisan config:clear</code>.
            </div>
        @endunless

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ route('loan.payments.stk.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="loan_book_loan_id" class="block text-xs font-semibold text-slate-600 mb-1">Loan account</label>
                    <select id="loan_book_loan_id" name="loan_book_loan_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        @foreach ($loans as $l)
                            <option
                                value="{{ $l->id }}"
                                data-phone="{{ $l->loanClient?->phone ?? '' }}"
                                data-balance="{{ number_format((float) ($l->balance ?? 0), 2, '.', '') }}"
                                @selected((int) old('loan_book_loan_id', (int) ($selectedLoanId ?? 0)) === (int) $l->id)
                            >{{ $l->loan_number }} · {{ $l->loanClient?->full_name ?? '—' }} · Bal {{ number_format((float) ($l->balance ?? 0), 2) }}</option>
                        @endforeach
                    </select>
                    @error('loan_book_loan_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="1" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="mpesa_phone" class="block text-xs font-semibold text-slate-600 mb-1">M-Pesa phone</label>
                    <input id="mpesa_phone" name="mpesa_phone" value="{{ old('mpesa_phone') }}" required class="w-full rounded-lg border-slate-200 text-sm" placeholder="07… or 2547…" />
                    @error('mpesa_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors" @disabled(! ($stkConfigured ?? false))>
                    Send STK Push
                </button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
<script>
    (() => {
        const form = document.querySelector('form[action="{{ route('loan.payments.stk.store') }}"]');
        if (!form) return;
        const loanSelect = form.querySelector('#loan_book_loan_id');
        const amountInput = form.querySelector('#amount');
        const phoneInput = form.querySelector('#mpesa_phone');
        const apply = () => {
            const opt = loanSelect?.selectedOptions?.[0];
            if (!opt) return;
            const phone = opt.getAttribute('data-phone') || '';
            const balance = opt.getAttribute('data-balance') || '';
            if (phoneInput && (!phoneInput.value || phoneInput.dataset.autofilled === '1')) {
                phoneInput.value = phone;
                phoneInput.dataset.autofilled = '1';
            }
            if (amountInput && (!amountInput.value || amountInput.dataset.autofilled === '1') && balance) {
                amountInput.value = balance;
                amountInput.dataset.autofilled = '1';
            }
        };
        loanSelect?.addEventListener('change', apply);
        if (loanSelect?.value) apply();
    })();
</script>
