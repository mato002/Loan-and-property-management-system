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
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label for="product_name" class="block text-xs font-semibold text-slate-600">Loan product</label>
                        <button type="button" id="open-product-modal" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">+ Add product</button>
                    </div>
                    <select id="product_name" name="product_name" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select product...</option>
                        @foreach (($productOptions ?? []) as $productName)
                            <option value="{{ $productName }}" @selected(old('product_name') === $productName)>{{ $productName }}</option>
                        @endforeach
                    </select>
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
                        <label for="interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">Interest rate %</label>
                        <input id="interest_rate" name="interest_rate" type="number" step="0.0001" min="0" max="100" value="{{ old('interest_rate') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" placeholder="Filled from linked application when set" />
                        @error('interest_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="term_value" class="block text-xs font-semibold text-slate-600 mb-1">Term length (number)</label>
                        <input id="term_value" name="term_value" type="number" min="1" value="{{ old('term_value', 12) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('term_value')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="term_unit" class="block text-xs font-semibold text-slate-600 mb-1">Term unit</label>
                        <select id="term_unit" name="term_unit" class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $unit => $label)
                                <option value="{{ $unit }}" @selected(old('term_unit', 'monthly') === $unit)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500">How long the loan runs (e.g. 6 monthly = 6 months).</p>
                        @error('term_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="dpd" class="block text-xs font-semibold text-slate-600 mb-1">Days past due (DPD)</label>
                        <input id="dpd" name="dpd" type="number" min="0" value="0" readonly class="w-full rounded-lg border-slate-200 bg-slate-50 text-sm tabular-nums text-slate-600" />
                        <p class="mt-1 text-[11px] text-slate-500">New loans start at 0 days past due.</p>
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
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label for="loan_branch_id" class="block text-xs font-semibold text-slate-600">Directory branch</label>
                        <button type="button" id="open-branch-modal" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">+ Add branch</button>
                    </div>
                    <select id="loan_branch_id" name="loan_branch_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">— Manual label only —</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('loan_branch_id') == $b->id)>{{ $b->name }}@if ($b->region) — {{ $b->region->name }}@endif</option>
                        @endforeach
                    </select>
                    @error('loan_branch_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
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
                'term_value' => (int) ($application->term_value ?? 0),
                'term_unit' => (string) ($application->term_unit ?? 'monthly'),
                'interest_rate' => $application->interest_rate !== null ? (float) $application->interest_rate : null,
                'interest_rate_period' => (string) ($application->interest_rate_period ?? 'annual'),
                'branch' => (string) ($application->branch ?? ''),
            ],
        ];
    });
@endphp

@php
    $branchDirectoryMap = $branches->mapWithKeys(fn ($b) => [strtolower(trim((string) $b->name)) => (int) $b->id]);
@endphp

<dialog id="product-modal" class="rounded-xl border border-slate-200 p-0 shadow-xl backdrop:bg-slate-900/30">
    <form method="dialog" class="w-[min(92vw,460px)]">
        <div class="border-b border-slate-100 px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">Add loan product</h3>
        </div>
        <div class="space-y-3 px-5 py-4">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Product name</label>
                <input id="modal_product_name" type="text" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. Business working capital" />
            </div>
            <p id="product-modal-error" class="hidden text-xs text-red-600"></p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
            <button value="cancel" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button id="save-product-btn" type="button" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Save product</button>
        </div>
    </form>
</dialog>

<dialog id="branch-modal" class="rounded-xl border border-slate-200 p-0 shadow-xl backdrop:bg-slate-900/30">
    <form method="dialog" class="w-[min(92vw,500px)]">
        <div class="border-b border-slate-100 px-5 py-3">
            <h3 class="text-sm font-semibold text-slate-800">Use or create branch</h3>
        </div>
        <div class="space-y-3 px-5 py-4">
            <label class="mb-1 block text-xs font-semibold text-slate-600">Directory branch</label>
            <select id="modal_branch_id" class="w-full rounded-lg border-slate-200 text-sm">
                <option value="">— Manual label only —</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" data-name="{{ $b->name }}">{{ $b->name }}@if ($b->region) — {{ $b->region->name }}@endif</option>
                @endforeach
            </select>
            <p class="text-[11px] text-slate-500">Or create a new branch below.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Branch name</label>
                    <input id="new_branch_name" type="text" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. Nakuru Town" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Region</label>
                    <select id="new_branch_region_id" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select region…</option>
                        @foreach (($regions ?? collect()) as $region)
                            <option value="{{ $region->id }}">{{ $region->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p id="branch-modal-error" class="hidden text-xs text-red-600"></p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
            <button value="cancel" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button id="apply-branch-btn" type="button" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Use branch</button>
            <button id="save-branch-btn" type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save new branch</button>
        </div>
    </form>
</dialog>

<script>
    (() => {
        const applicationData = @json($applicationAutofillData);
        const branchDirectoryMap = @json($branchDirectoryMap);
        const clientBranchById = @json($clientBranchById ?? []);

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
        const interestRateInput = form.querySelector('#interest_rate');
        const termValueInput = form.querySelector('#term_value');
        const termUnitSelect = form.querySelector('#term_unit');
        const disbursedInput = form.querySelector('#disbursed_at');
        const loanBranchSelect = form.querySelector('#loan_branch_id');
        const productModal = document.getElementById('product-modal');
        const openProductModalBtn = document.getElementById('open-product-modal');
        const saveProductBtn = document.getElementById('save-product-btn');
        const modalProductName = document.getElementById('modal_product_name');
        const modalError = document.getElementById('product-modal-error');
        const branchModal = document.getElementById('branch-modal');
        const openBranchModalBtn = document.getElementById('open-branch-modal');
        const modalBranchSelect = document.getElementById('modal_branch_id');
        const applyBranchBtn = document.getElementById('apply-branch-btn');
        const saveBranchBtn = document.getElementById('save-branch-btn');
        const newBranchNameInput = document.getElementById('new_branch_name');
        const newBranchRegionInput = document.getElementById('new_branch_region_id');
        const branchModalError = document.getElementById('branch-modal-error');

        const calculateMaturityFromSchedule = () => {
            if (!maturityInput) return;
            const disbursedRaw = (disbursedInput?.value || '').trim();
            const termValue = Number(termValueInput?.value || 0);
            const termUnit = String(termUnitSelect?.value || 'monthly').toLowerCase();
            if (!disbursedRaw || termValue <= 0) return;

            const base = new Date(disbursedRaw);
            if (Number.isNaN(base.getTime())) return;
            const d = new Date(base.getTime());

            if (termUnit === 'daily') {
                d.setDate(d.getDate() + termValue);
            } else if (termUnit === 'weekly') {
                d.setDate(d.getDate() + (termValue * 7));
            } else {
                d.setMonth(d.getMonth() + termValue);
            }

            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            maturityInput.value = `${yyyy}-${mm}-${dd}`;
        };

        const calculateTermFromMaturity = () => {
            const disbursedRaw = (disbursedInput?.value || '').trim();
            const maturityRaw = (maturityInput?.value || '').trim();
            const termUnit = String(termUnitSelect?.value || 'monthly').toLowerCase();
            if (!disbursedRaw || !maturityRaw || !termValueInput) return;

            const disbursed = new Date(disbursedRaw);
            const maturity = new Date(maturityRaw);
            if (Number.isNaN(disbursed.getTime()) || Number.isNaN(maturity.getTime())) return;
            if (maturity.getTime() <= disbursed.getTime()) return;

            const diffMs = maturity.getTime() - disbursed.getTime();
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
            if (diffDays <= 0) return;

            let value = 1;
            if (termUnit === 'daily') {
                value = diffDays;
            } else if (termUnit === 'weekly') {
                value = Math.ceil(diffDays / 7);
            } else {
                value = Math.ceil(diffDays / 30);
            }

            termValueInput.value = String(Math.max(1, value));
        };

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
            if (branchInput) branchInput.value = selected.branch || '';
            if (loanBranchSelect) {
                const key = String(selected.branch || '').trim().toLowerCase();
                const matchedId = key && branchDirectoryMap[key] ? String(branchDirectoryMap[key]) : '';
                loanBranchSelect.value = matchedId;
            }
            if (statusSelect) statusSelect.value = '{{ \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT }}';
            if (interestRateInput && selected.interest_rate !== null) {
                interestRateInput.value = Number(selected.interest_rate).toFixed(4);
            }
            if (termValueInput && Number(selected.term_value || 0) > 0) {
                termValueInput.value = selected.term_value;
            } else if (termValueInput && Number(selected.term_months || 0) > 0) {
                termValueInput.value = selected.term_months;
            }
            if (termUnitSelect && selected.term_unit) {
                termUnitSelect.value = selected.term_unit;
            }
            if (maturityInput && !maturityInput.value) maturityInput.value = calculateMaturityDate(selected.term_months);
            if (notesInput && !notesInput.value) notesInput.value = 'Booked from application ' + (selected.reference || '') + '.';
            calculateMaturityFromSchedule();
        };

        const applyClientBranchDefaults = () => {
            if (!clientSelect || !branchInput) return;
            const selectedClientId = String(clientSelect.value || '');
            if (!selectedClientId) return;
            const clientBranch = String(clientBranchById[selectedClientId] || '').trim();
            if (!clientBranch) return;
            if (!branchInput.value || !applicationSelect?.value) {
                branchInput.value = clientBranch;
            }
            if (loanBranchSelect) {
                const matchedId = branchDirectoryMap[clientBranch.toLowerCase()] || '';
                if (matchedId && !loanBranchSelect.value) {
                    loanBranchSelect.value = String(matchedId);
                }
            }
        };

        if (applicationSelect) {
            applicationSelect.addEventListener('change', applyApplicationDefaults);
            const prefillId = @json($prefillApplicationId ?? null);
            if (prefillId && String(applicationSelect.value) === String(prefillId)) {
                applyApplicationDefaults();
            }
        }
        clientSelect?.addEventListener('change', applyClientBranchDefaults);

        termValueInput?.addEventListener('input', calculateMaturityFromSchedule);
        termUnitSelect?.addEventListener('change', calculateMaturityFromSchedule);
        disbursedInput?.addEventListener('change', calculateMaturityFromSchedule);
        maturityInput?.addEventListener('change', calculateTermFromMaturity);
        loanBranchSelect?.addEventListener('change', () => {
            const selectedOption = loanBranchSelect.options[loanBranchSelect.selectedIndex];
            if (!selectedOption || !branchInput) return;
            if (!loanBranchSelect.value) return;
            const branchName = selectedOption.textContent?.split('—')[0]?.trim() || '';
            if (branchName !== '') branchInput.value = branchName;
        });

        openProductModalBtn?.addEventListener('click', () => {
            modalError?.classList.add('hidden');
            modalError.textContent = '';
            productModal?.showModal();
            modalProductName?.focus();
        });

        saveProductBtn?.addEventListener('click', async () => {
            const name = (modalProductName?.value || '').trim();
            if (!name) {
                modalError.textContent = 'Product name is required.';
                modalError.classList.remove('hidden');
                return;
            }

            try {
                saveProductBtn.disabled = true;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]')?.value || '';
                const payload = new FormData();
                payload.append('name', name);

                const response = await fetch("{{ route('loan.book.applications.products.store') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload,
                });
                const data = await response.json();
                if (!response.ok || !data?.ok) {
                    throw new Error(data?.message || 'Failed to save product.');
                }

                if (productInput) {
                    const existing = Array.from(productInput.options).find((opt) => opt.value === data.product.name);
                    if (!existing) {
                        const option = document.createElement('option');
                        option.value = data.product.name;
                        option.textContent = data.product.name;
                        productInput.appendChild(option);
                    }
                    productInput.value = data.product.name;
                }
                productModal?.close();
            } catch (error) {
                modalError.textContent = error instanceof Error ? error.message : 'Failed to save product.';
                modalError.classList.remove('hidden');
            } finally {
                saveProductBtn.disabled = false;
            }
        });

        openBranchModalBtn?.addEventListener('click', () => {
            if (!branchModal) return;
            branchModalError?.classList.add('hidden');
            branchModalError.textContent = '';
            if (newBranchNameInput) newBranchNameInput.value = '';
            if (modalBranchSelect && loanBranchSelect) {
                modalBranchSelect.value = loanBranchSelect.value || '';
            }
            branchModal.showModal();
        });
        applyBranchBtn?.addEventListener('click', () => {
            if (!modalBranchSelect) return;
            if (loanBranchSelect) loanBranchSelect.value = modalBranchSelect.value || '';
            const selectedOption = modalBranchSelect.options[modalBranchSelect.selectedIndex];
            if (branchInput && selectedOption && modalBranchSelect.value) {
                const branchName = selectedOption.textContent?.split('—')[0]?.trim() || '';
                if (branchName !== '') branchInput.value = branchName;
            }
            branchModal?.close();
        });
        saveBranchBtn?.addEventListener('click', async () => {
            const name = (newBranchNameInput?.value || '').trim();
            const loanRegionId = (newBranchRegionInput?.value || '').trim();
            if (!name || !loanRegionId) {
                branchModalError.textContent = 'Branch name and region are required.';
                branchModalError.classList.remove('hidden');
                return;
            }
            try {
                saveBranchBtn.disabled = true;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]')?.value || '';
                const payload = new FormData();
                payload.append('name', name);
                payload.append('loan_region_id', loanRegionId);

                const response = await fetch("{{ route('loan.book.loans.quick_branch') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload,
                });
                const data = await response.json();
                if (!response.ok || !data?.ok) {
                    throw new Error(data?.message || 'Failed to save branch.');
                }
                const label = data.branch.region_name ? `${data.branch.name} — ${data.branch.region_name}` : data.branch.name;
                const option = document.createElement('option');
                option.value = String(data.branch.id);
                option.textContent = label;
                option.setAttribute('data-name', data.branch.name);
                loanBranchSelect?.appendChild(option.cloneNode(true));
                modalBranchSelect?.appendChild(option);
                if (loanBranchSelect) loanBranchSelect.value = String(data.branch.id);
                if (modalBranchSelect) modalBranchSelect.value = String(data.branch.id);
                if (branchInput) branchInput.value = data.branch.name;
                branchModal?.close();
            } catch (error) {
                branchModalError.textContent = error instanceof Error ? error.message : 'Failed to save branch.';
                branchModalError.classList.remove('hidden');
            } finally {
                saveBranchBtn.disabled = false;
            }
        });
        applyClientBranchDefaults();
    })();
</script>
