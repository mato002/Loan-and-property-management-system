<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-xl">
            <form method="post" action="{{ route('loan.book.disbursements.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                @error('accounting')
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p>{{ $message }}</p>
                        <a href="{{ route('loan.accounting.books.chart_rules') }}" class="mt-2 inline-flex items-center rounded-md border border-red-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                            Open Loan Ledger chart rules
                        </a>
                    </div>
                @enderror
                <div>
                    <label for="loan_book_loan_id" class="block text-xs font-semibold text-slate-600 mb-1">Loan account</label>
                    <select id="loan_book_loan_id" name="loan_book_loan_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        @foreach ($loans as $l)
                            <option value="{{ $l->id }}" @selected((string) old('loan_book_loan_id', request()->query('loan_book_loan_id', '')) === (string) $l->id)>{{ $l->loan_number }} · {{ $l->loanClient->full_name }}</option>
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
                    <label for="method" class="block text-xs font-semibold text-slate-600 mb-1">Method</label>
                    <select id="method" name="method" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank / EFT', 'cheque' => 'Cheque'] as $v => $lab)
                            <option value="{{ $v }}" @selected(old('method') === $v)>{{ $lab }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">M-Pesa means you already sent funds (e.g. from your till or agent). This form only records the payout and posts to the ledger immediately — it does not call Safaricom B2C.</p>
                    @if ($b2cPayoutConfigured ?? false)
                        <p class="mt-1 text-xs text-slate-500">Daraja B2C is configured: if an API-initiated payout fails, you can retry from the disbursement detail page.</p>
                    @endif
                    @error('method')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div id="payout_transaction_wrap" class="space-y-1 @if (! in_array((string) old('method', ''), ['mpesa', 'bank', 'cheque'], true)) hidden @endif">
                    <label for="payout_transaction_id" class="block text-xs font-semibold text-slate-600 mb-1">
                        <span id="payout_transaction_label">Transaction reference / ID</span>
                    </label>
                    <input id="payout_transaction_id" name="payout_transaction_id" type="text" value="{{ old('payout_transaction_id') }}" maxlength="80" autocomplete="off" class="w-full rounded-lg border-slate-200 text-sm" placeholder="" />
                    <p id="payout_transaction_hint" class="text-xs text-slate-500"></p>
                    @error('payout_transaction_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reference" class="block text-xs font-semibold text-slate-600 mb-1">Reference / voucher</label>
                    <input id="reference" name="reference" value="{{ old('reference') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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

@php
    $loanAutofillData = $loans->mapWithKeys(function ($loan) {
        return [
            (string) $loan->id => [
                'id' => (int) $loan->id,
                'loan_number' => (string) $loan->loan_number,
                'client_name' => (string) ($loan->loanClient->full_name ?? ''),
                'balance' => (float) ($loan->balance ?? 0),
                'principal' => (float) ($loan->principal ?? 0),
            ],
        ];
    });
@endphp

<script>
    (() => {
        const loanData = @json($loanAutofillData);
        const form = document.querySelector('form[action="{{ route('loan.book.disbursements.store') }}"]');
        if (!form) return;

        const loanSelect = form.querySelector('#loan_book_loan_id');
        const amountInput = form.querySelector('#amount');
        const referenceInput = form.querySelector('#reference');
        const notesInput = form.querySelector('#notes');
        const dateInput = form.querySelector('#disbursed_at');
        const methodSelect = form.querySelector('#method');
        const payoutWrap = form.querySelector('#payout_transaction_wrap');
        const payoutInput = form.querySelector('#payout_transaction_id');
        const payoutLabel = form.querySelector('#payout_transaction_label');
        const payoutHint = form.querySelector('#payout_transaction_hint');

        const methodsNeedTxnRef = new Set(['mpesa', 'bank', 'cheque']);
        const payoutCopy = {
            mpesa: {
                label: 'M-Pesa confirmation / transaction ID',
                hint: 'e.g. confirmation code from the SMS or transaction ID from your M-Pesa statement.',
                placeholder: 'SBI… / receipt code',
            },
            bank: {
                label: 'Bank / EFT reference',
                hint: 'Transfer reference, FT number, or bank confirmation as shown on your statement.',
                placeholder: 'FT / reference number',
            },
            cheque: {
                label: 'Cheque number',
                hint: 'The cheque serial number you issued for this payout.',
                placeholder: 'Cheque #',
            },
        };

        const syncPayoutTransactionField = () => {
            const m = methodSelect?.value ?? '';
            const show = methodsNeedTxnRef.has(m);
            if (payoutWrap) {
                payoutWrap.classList.toggle('hidden', !show);
            }
            if (payoutInput) {
                if (show) {
                    payoutInput.setAttribute('required', 'required');
                    const copy = payoutCopy[m] || { label: 'Transaction reference / ID', hint: '', placeholder: '' };
                    if (payoutLabel) payoutLabel.textContent = copy.label;
                    if (payoutHint) payoutHint.textContent = copy.hint;
                    payoutInput.placeholder = copy.placeholder || '';
                } else {
                    payoutInput.removeAttribute('required');
                    if (payoutLabel) payoutLabel.textContent = 'Transaction reference / ID';
                    if (payoutHint) payoutHint.textContent = '';
                    payoutInput.placeholder = '';
                }
            }
        };

        const initialAmount = amountInput?.value ?? '';
        const initialReference = referenceInput?.value ?? '';
        const initialNotes = notesInput?.value ?? '';

        const formatDateYmd = (rawDate) => {
            if (rawDate) return rawDate;
            const today = new Date();
            return today.toISOString().slice(0, 10);
        };

        const applyLoanDefaults = () => {
            const selectedId = loanSelect?.value ? String(loanSelect.value) : '';
            if (!selectedId || !loanData[selectedId]) return;

            const selectedLoan = loanData[selectedId];
            const suggestedAmount = Number(selectedLoan.balance || 0) > 0
                ? Number(selectedLoan.balance)
                : Number(selectedLoan.principal || 0);
            const amountText = suggestedAmount > 0 ? suggestedAmount.toFixed(2) : '';
            const ymd = formatDateYmd(dateInput?.value);
            const compactDate = ymd.replace(/-/g, '');
            const generatedRef = `DISB-${selectedLoan.loan_number}-${compactDate}`;

            if (amountInput && (amountInput.value === '' || amountInput.value === initialAmount)) {
                amountInput.value = amountText;
            }
            if (referenceInput && (referenceInput.value === '' || referenceInput.value === initialReference)) {
                referenceInput.value = generatedRef;
            }
            if (notesInput && (notesInput.value === '' || notesInput.value === initialNotes)) {
                notesInput.value = `Loan disbursement for ${selectedLoan.loan_number} - ${selectedLoan.client_name}.`;
            }
        };

        if (loanSelect) {
            loanSelect.addEventListener('change', applyLoanDefaults);
            if (loanSelect.value) {
                applyLoanDefaults();
            }
        }
        if (methodSelect) {
            methodSelect.addEventListener('change', syncPayoutTransactionField);
            syncPayoutTransactionField();
        }
    })();
</script>
