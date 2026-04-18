<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-2xl">
            <form method="post" action="{{ route('loan.book.loans.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="loan_book_application_id" class="block text-xs font-semibold text-slate-600 mb-1">Link application (optional)</label>
                    <select id="loan_book_application_id" name="loan_book_application_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">— None —</option>
                        @foreach ($applications as $a)
                            <option value="{{ $a->id }}" @selected(old('loan_book_application_id', $prefillApplicationId ?? null) == $a->id)>{{ $a->reference }} · {{ $a->loanClient->full_name }}</option>
                        @endforeach
                    </select>
                    @error('loan_book_application_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="loan_client_id" class="block text-xs font-semibold text-slate-600 mb-1">Client</label>
                    <select id="loan_client_id" name="loan_client_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select…</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('loan_client_id') == $c->id)>{{ $c->full_name }} · {{ $c->client_number }}</option>
                        @endforeach
                    </select>
                    @error('loan_client_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="product_name" class="block text-xs font-semibold text-slate-600 mb-1">Product</label>
                    <input id="product_name" name="product_name" value="{{ old('product_name') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('product_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="principal" class="block text-xs font-semibold text-slate-600 mb-1">Principal</label>
                        <input id="principal" name="principal" type="number" step="0.01" min="0" value="{{ old('principal') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('principal')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="balance" class="block text-xs font-semibold text-slate-600 mb-1">Balance (optional)</label>
                        <input id="balance" name="balance" type="number" step="0.01" min="0" value="{{ old('balance') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" placeholder="Defaults to principal" />
                        @error('balance')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">Interest rate % p.a.</label>
                        <input id="interest_rate" name="interest_rate" type="number" step="0.0001" min="0" max="100" value="{{ old('interest_rate', '18') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('interest_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="dpd" class="block text-xs font-semibold text-slate-600 mb-1">DPD</label>
                        <input id="dpd" name="dpd" type="number" min="0" value="{{ old('dpd', 0) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('dpd')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select id="status" name="status" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', \App\Models\LoanBookLoan::STATUS_ACTIVE) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="disbursed_at" class="block text-xs font-semibold text-slate-600 mb-1">Disbursed at</label>
                        <input id="disbursed_at" name="disbursed_at" type="datetime-local" value="{{ old('disbursed_at') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('disbursed_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="maturity_date" class="block text-xs font-semibold text-slate-600 mb-1">Maturity</label>
                        <input id="maturity_date" name="maturity_date" type="date" value="{{ old('maturity_date') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('maturity_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input id="is_checkoff" name="is_checkoff" type="checkbox" value="1" class="rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('is_checkoff')) />
                    <label for="is_checkoff" class="text-sm text-slate-700">Checkoff / employer deduct</label>
                </div>
                <div>
                    <label for="checkoff_employer" class="block text-xs font-semibold text-slate-600 mb-1">Employer (if checkoff)</label>
                    <input id="checkoff_employer" name="checkoff_employer" value="{{ old('checkoff_employer') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('checkoff_employer')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @if ($branches->isNotEmpty())
                    <div>
                        <label for="loan_branch_id" class="block text-xs font-semibold text-slate-600 mb-1">Directory branch</label>
                        <select id="loan_branch_id" name="loan_branch_id" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">— Manual label only —</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected(old('loan_branch_id') == $b->id)>{{ $b->name }}@if ($b->region) — {{ $b->region->name }}@endif</option>
                            @endforeach
                        </select>
                        @error('loan_branch_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch label (on loan)</label>
                    <input id="branch" name="branch" value="{{ old('branch') }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Filled from directory when selected, or type manually" />
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save loan</button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>

@php
    $applicationAutofillData = $applications->mapWithKeys(function ($application) {
        return [
            (string) $application->id => [
                'id' => (int) $application->id,
                'reference' => (string) $application->reference,
                'loan_client_id' => (int) $application->loan_client_id,
                'product_name' => (string) $application->product_name,
                'amount_requested' => (float) $application->amount_requested,
                'term_months' => (int) ($application->term_months ?? 0),
                'branch' => (string) ($application->branch ?? ''),
            ],
        ];
    });
@endphp

<script>
    (() => {
        const applicationData = @json($applicationAutofillData);

        const form = document.querySelector('form[action="{{ route('loan.book.loans.store') }}"]');
        if (!form) return;

        const applicationSelect = form.querySelector('#loan_book_application_id');
        const clientSelect = form.querySelector('#loan_client_id');
        const productInput = form.querySelector('#product_name');
        const principalInput = form.querySelector('#principal');
        const balanceInput = form.querySelector('#balance');
        const statusSelect = form.querySelector('#status');
        const maturityInput = form.querySelector('#maturity_date');
        const branchInput = form.querySelector('#branch');
        const notesInput = form.querySelector('#notes');

        const calculateMaturityDate = (months) => {
            const parsedMonths = Number(months || 0);
            if (parsedMonths <= 0) return '';
            const date = new Date();
            date.setMonth(date.getMonth() + parsedMonths);
            return date.toISOString().slice(0, 10);
        };

        const applyApplicationDefaults = () => {
            const selectedId = applicationSelect?.value ? String(applicationSelect.value) : '';
            if (!selectedId || !applicationData[selectedId]) return;

            const selected = applicationData[selectedId];
            const amount = Number(selected.amount_requested || 0);
            const formattedAmount = amount > 0 ? amount.toFixed(2) : '';

            if (clientSelect) clientSelect.value = String(selected.loan_client_id || '');
            if (productInput) productInput.value = selected.product_name || '';
            if (principalInput) principalInput.value = formattedAmount;
            if (balanceInput && !balanceInput.value) balanceInput.value = formattedAmount;
            if (branchInput && !branchInput.value) branchInput.value = selected.branch || '';
            if (statusSelect) statusSelect.value = '{{ \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT }}';
            if (maturityInput && !maturityInput.value) maturityInput.value = calculateMaturityDate(selected.term_months);
            if (notesInput && !notesInput.value) notesInput.value = 'Booked from application ' + (selected.reference || '') + '.';
        };

        if (applicationSelect) {
            applicationSelect.addEventListener('change', applyApplicationDefaults);
            const prefillId = @json($prefillApplicationId ?? null);
            if (prefillId && String(applicationSelect.value) === String(prefillId)) {
                applyApplicationDefaults();
            }
        }
    })();
</script>
