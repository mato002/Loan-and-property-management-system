<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div
            class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-3xl"
            x-data="loanProductPicker(@js($productMetaByName ?? []))"
            data-products-store-url="{{ route('loan.book.applications.products.store') }}"
        >
            <form method="post" action="{{ route('loan.book.applications.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="loan_client_id" class="block text-xs font-semibold text-slate-600 mb-1">Client</label>
                    <select
                        id="loan_client_id"
                        name="loan_client_id"
                        required
                        class="w-full rounded-lg border-slate-200 text-sm"
                        @change="autofillFromSelectedClient()"
                    >
                        <option value="">Select client…</option>
                        @foreach ($clients as $c)
                            <option
                                value="{{ $c->id }}"
                                data-full-name="{{ trim((string) $c->full_name) }}"
                                data-phone="{{ trim((string) ($c->phone ?? '')) }}"
                                data-id-number="{{ trim((string) ($c->id_number ?? '')) }}"
                                data-address="{{ trim((string) ($c->address ?? '')) }}"
                                data-branch="{{ trim((string) ($c->branch ?? '')) }}"
                                data-guarantor-full-name="{{ trim((string) ($c->guarantor_1_full_name ?? '')) }}"
                                data-guarantor-id-number="{{ trim((string) ($c->guarantor_1_id_number ?? '')) }}"
                                data-guarantor-phone="{{ trim((string) ($c->guarantor_1_phone ?? '')) }}"
                                @selected((string) old('loan_client_id', $selectedClientId ?? '') === (string) $c->id)
                            >{{ $c->full_name }} · {{ $c->client_number }}</option>
                        @endforeach
                    </select>
                    @error('loan_client_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="product_name" class="block text-xs font-semibold text-slate-600 mb-1">Product</label>
                    <div class="flex gap-2">
                        <select id="product_name" name="product_name" required class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select product...</option>
                            @foreach (($productOptions ?? []) as $productName)
                                <option value="{{ $productName }}" @selected(old('product_name', $defaultProductName ?? '') === $productName)>{{ $productName }}</option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-bold text-slate-700 transition-colors hover:bg-slate-50"
                            title="Create new product"
                            @click="openProductModal"
                        >+</button>
                    </div>
                    @error('product_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500" x-text="selectedProductHint"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="amount_requested" class="block text-xs font-semibold text-slate-600 mb-1">Amount requested</label>
                        <input id="amount_requested" name="amount_requested" type="number" step="0.01" min="0" value="{{ old('amount_requested') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount_requested')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="term_value" class="block text-xs font-semibold text-slate-600 mb-1">Term length</label>
                        <input id="term_value" name="term_value" type="number" min="1" value="{{ old('term_value', old('term_months', 12)) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('term_value')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="term_unit" class="block text-xs font-semibold text-slate-600 mb-1">Term unit</label>
                        <select id="term_unit" name="term_unit" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('term_unit', 'monthly') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('term_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">Interest rate (%)</label>
                        <input id="interest_rate" name="interest_rate" type="number" step="0.0001" min="0" max="1000" value="{{ old('interest_rate') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('interest_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="interest_rate_period" class="block text-xs font-semibold text-slate-600 mb-1">Interest period</label>
                        <select id="interest_rate_period" name="interest_rate_period" class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['daily' => 'Per day', 'weekly' => 'Per week', 'monthly' => 'Per month', 'annual' => 'Per year'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('interest_rate_period', 'annual') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('interest_rate_period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="stage" class="block text-xs font-semibold text-slate-600 mb-1">Stage</label>
                    <select id="stage" name="stage" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($stages as $value => $label)
                            <option value="{{ $value }}" @selected(old('stage', \App\Models\LoanBookApplication::STAGE_SUBMITTED) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('stage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch (optional)</label>
                    <div class="flex gap-2">
                        <select id="branch" name="branch" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select branch...</option>
                            @foreach (($branchOptions ?? []) as $branchName)
                                <option value="{{ $branchName }}" @selected(old('branch') === $branchName)>{{ $branchName }}</option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-lg font-bold text-slate-700 transition-colors hover:bg-slate-50"
                            title="Create branch"
                            @click="openBranchModal"
                        >+</button>
                    </div>
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="purpose" class="block text-xs font-semibold text-slate-600 mb-1">Purpose</label>
                    <textarea id="purpose" name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose', $defaultPurpose ?? '') }}</textarea>
                    @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Loan department form</h3>
                        <p class="mt-1 text-xs text-slate-500">Applicant <strong>name</strong>, <strong>phone</strong>, <strong>home address</strong> and <strong>ID</strong> are taken from the selected client’s profile. Complete the fields below to match your paper form.</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">Selected client profile</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-700">
                            <p><span class="font-semibold text-slate-600">Name:</span> <span x-text="selectedClientPreview.fullName || '—'"></span></p>
                            <p><span class="font-semibold text-slate-600">Phone:</span> <span x-text="selectedClientPreview.phone || '—'"></span></p>
                            <p><span class="font-semibold text-slate-600">ID No.:</span> <span x-text="selectedClientPreview.idNumber || '—'"></span></p>
                            <p class="sm:col-span-2"><span class="font-semibold text-slate-600">Address:</span> <span x-text="selectedClientPreview.address || '—'"></span></p>
                        </div>
                    </div>
                    <div>
                        <label for="applicant_pin_location_code" class="block text-xs font-semibold text-slate-600 mb-1">Home / business PIN location code</label>
                        <input id="applicant_pin_location_code" name="applicant_pin_location_code" type="text" value="{{ old('applicant_pin_location_code') }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. map / PIN code for home or business" />
                        @error('applicant_pin_location_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="applicant_signature_name" class="block text-xs font-semibold text-slate-600 mb-1">Applicant sign (full name)</label>
                        <input id="applicant_signature_name" name="applicant_signature_name" type="text" value="{{ old('applicant_signature_name') }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Type full name as on the signature line" autocomplete="name" />
                        @error('applicant_signature_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Guarantor details</h4>
                        <p class="text-xs text-slate-500 mb-3">If left blank, primary guarantor is copied from the client profile when you save (when present).</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label for="guarantor_full_name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                                <input id="guarantor_full_name" name="guarantor_full_name" type="text" value="{{ old('guarantor_full_name') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_full_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="guarantor_id_number" class="block text-xs font-semibold text-slate-600 mb-1">ID no.</label>
                                <input id="guarantor_id_number" name="guarantor_id_number" type="text" value="{{ old('guarantor_id_number') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_id_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="guarantor_phone" class="block text-xs font-semibold text-slate-600 mb-1">Tel no.</label>
                                <input id="guarantor_phone" name="guarantor_phone" type="text" value="{{ old('guarantor_phone') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="guarantor_signature_name" class="block text-xs font-semibold text-slate-600 mb-1">Guarantor signature (full name)</label>
                                <input id="guarantor_signature_name" name="guarantor_signature_name" type="text" value="{{ old('guarantor_signature_name') }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Type full name as signature" />
                                @error('guarantor_signature_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">Repayment agreement</h4>
                        <p class="text-xs text-slate-700 leading-relaxed border border-slate-200 rounded-lg bg-white p-3">
                            I hereby agree to surrender all my properties to {{ config('app.name') }} or Auctioneers to auction my property if I fail or I don't pay the loan as agreed in the conditions.
                        </p>
                        <label class="mt-3 flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="repayment_agreement_accepted" value="1" class="mt-1 rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('repayment_agreement_accepted')) />
                            <span class="text-sm text-slate-700">Applicant confirms they have read and accept the repayment agreement above.</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Internal notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save application</button>
                </div>
            </form>

            <div
                x-show="showProductModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
                @keydown.escape.window="closeProductModal"
                @click.self="closeProductModal"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-800">Create loan product</h3>
                    <p class="mt-1 text-xs text-slate-500">Save a product and immediately select it for this application.</p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label for="new_product_name" class="block text-xs font-semibold text-slate-600 mb-1">Product name</label>
                            <input id="new_product_name" x-model.trim="newProductName" type="text" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. Salary advance" />
                        </div>
                        <div>
                            <label for="new_product_description" class="block text-xs font-semibold text-slate-600 mb-1">Description (optional)</label>
                            <textarea id="new_product_description" x-model.trim="newProductDescription" rows="3" class="w-full rounded-lg border-slate-200 text-sm"></textarea>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="new_product_interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">Default interest % (optional)</label>
                                <input id="new_product_interest_rate" x-model.trim="newProductInterestRate" type="number" step="0.0001" min="0" max="100" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="new_product_interest_rate_period" class="block text-xs font-semibold text-slate-600 mb-1">Default interest period</label>
                                <select id="new_product_interest_rate_period" x-model="newProductInterestRatePeriod" class="w-full rounded-lg border-slate-200 text-sm">
                                    <option value="daily">Per day</option>
                                    <option value="weekly">Per week</option>
                                    <option value="monthly">Per month</option>
                                    <option value="annual">Per year</option>
                                </select>
                            </div>
                            <div>
                                <label for="new_product_term_months" class="block text-xs font-semibold text-slate-600 mb-1">Default term length (optional)</label>
                                <input id="new_product_term_months" x-model.trim="newProductTermMonths" type="number" min="1" max="600" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                            </div>
                            <div>
                                <label for="new_product_term_unit" class="block text-xs font-semibold text-slate-600 mb-1">Default term unit</label>
                                <select id="new_product_term_unit" x-model="newProductTermUnit" class="w-full rounded-lg border-slate-200 text-sm">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <p x-show="productModalError" x-text="productModalError" class="mt-3 text-xs text-red-600"></p>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="closeProductModal">Cancel</button>
                        <button type="button" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040] disabled:cursor-not-allowed disabled:opacity-70" @click="saveNewProduct" :disabled="isSavingProduct">
                            <span x-show="!isSavingProduct">Save product</span>
                            <span x-show="isSavingProduct">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>

            <div
                x-show="showBranchModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
                @keydown.escape.window="closeBranchModal"
                @click.self="closeBranchModal"
            >
                <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-800">Create branch</h3>
                    <p class="mt-1 text-xs text-slate-500">Add a branch and use it immediately in this application.</p>

                    <div class="mt-4">
                        <label for="new_branch_name" class="block text-xs font-semibold text-slate-600 mb-1">Branch name</label>
                        <input id="new_branch_name" x-model.trim="newBranchName" type="text" class="w-full rounded-lg border-slate-200 text-sm" placeholder="e.g. Nairobi CBD" />
                    </div>

                    <p x-show="branchModalError" x-text="branchModalError" class="mt-3 text-xs text-red-600"></p>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" @click="closeBranchModal">Cancel</button>
                        <button type="button" class="rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040] disabled:cursor-not-allowed disabled:opacity-70" @click="saveNewBranch" :disabled="isSavingBranch">
                            <span x-show="!isSavingBranch">Save branch</span>
                            <span x-show="isSavingBranch">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    function loanProductPicker(productMetaByName = {}) {
        const productStoreUrl = @json(route('loan.book.applications.products.store'));
        return {
            productMetaByName,
            showProductModal: false,
            newProductName: '',
            newProductDescription: '',
            newProductInterestRate: '',
            newProductTermMonths: '',
            newProductTermUnit: 'monthly',
            newProductInterestRatePeriod: 'annual',
            productModalError: '',
            isSavingProduct: false,
            showBranchModal: false,
            newBranchName: '',
            branchModalError: '',
            isSavingBranch: false,
            lastAutoFilled: {},
            selectedClientPreview: {
                fullName: '',
                phone: '',
                idNumber: '',
                address: '',
            },
            selectedProductHint: '',
            init() {
                this.$nextTick(() => {
                    this.autofillFromSelectedClient();
                    this.applyProductDefaults();
                    const productSelect = this.$el.querySelector('#product_name');
                    productSelect?.addEventListener('change', () => this.applyProductDefaults());
                });
            },
            openProductModal() {
                this.productModalError = '';
                this.showProductModal = true;
            },
            closeProductModal() {
                if (this.isSavingProduct) return;
                this.showProductModal = false;
                this.newProductName = '';
                this.newProductDescription = '';
                this.newProductInterestRate = '';
                this.newProductTermMonths = '';
                this.newProductTermUnit = 'monthly';
                this.newProductInterestRatePeriod = 'annual';
                this.productModalError = '';
            },
            applyProductDefaults() {
                const productSelect = this.$el.querySelector('#product_name');
                const termInput = this.$el.querySelector('#term_value');
                const termUnitSelect = this.$el.querySelector('#term_unit');
                const interestInput = this.$el.querySelector('#interest_rate');
                const interestPeriodSelect = this.$el.querySelector('#interest_rate_period');
                const name = (productSelect?.value ?? '').trim();
                if (!name) {
                    this.selectedProductHint = '';
                    return;
                }

                const meta = this.productMetaByName[name] ?? null;
                if (!meta) {
                    this.selectedProductHint = '';
                    return;
                }

                const parts = [];
                if (meta.default_interest_rate !== null && meta.default_interest_rate !== undefined) {
                    const defaultInterestPeriod = String(meta.default_interest_rate_period ?? 'annual').toLowerCase();
                    const interestPeriodLabel = {
                        daily: 'day',
                        weekly: 'week',
                        monthly: 'month',
                        annual: 'year',
                    }[defaultInterestPeriod] ?? defaultInterestPeriod;
                    parts.push(`Default interest: ${Number(meta.default_interest_rate).toFixed(4)}% per ${interestPeriodLabel}.`);
                    const currentRate = (interestInput?.value ?? '').trim();
                    if (interestInput && currentRate === '') {
                        interestInput.value = String(meta.default_interest_rate);
                        interestInput.dispatchEvent(new Event('input', { bubbles: true }));
                        interestInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (interestPeriodSelect && (interestPeriodSelect.value ?? '') === 'annual') {
                        interestPeriodSelect.value = defaultInterestPeriod;
                        interestPeriodSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                if (meta.default_term_months) {
                    const defaultTermUnit = String(meta.default_term_unit ?? 'monthly').toLowerCase();
                    parts.push(`Default term: ${meta.default_term_months} ${defaultTermUnit}.`);
                    const current = (termInput?.value ?? '').trim();
                    if (termInput && (current === '' || current === '12')) {
                        termInput.value = String(meta.default_term_months);
                        termInput.dispatchEvent(new Event('input', { bubbles: true }));
                        termInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (termUnitSelect && (termUnitSelect.value ?? '') === 'monthly') {
                        termUnitSelect.value = defaultTermUnit;
                        termUnitSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                this.selectedProductHint = parts.join(' ');
            },
            openBranchModal() {
                this.branchModalError = '';
                this.showBranchModal = true;
            },
            closeBranchModal() {
                if (this.isSavingBranch) return;
                this.showBranchModal = false;
                this.newBranchName = '';
                this.branchModalError = '';
            },
            clientDataset() {
                const clientSelect = this.$el.querySelector('#loan_client_id');
                const selectedOption = clientSelect?.selectedOptions?.[0];
                if (!selectedOption || !selectedOption.value) {
                    return null;
                }

                return {
                    fullName: (selectedOption.dataset.fullName ?? '').trim(),
                    phone: (selectedOption.dataset.phone ?? '').trim(),
                    idNumber: (selectedOption.dataset.idNumber ?? '').trim(),
                    address: (selectedOption.dataset.address ?? '').trim(),
                    branch: (selectedOption.dataset.branch ?? '').trim(),
                    guarantorFullName: (selectedOption.dataset.guarantorFullName ?? '').trim(),
                    guarantorIdNumber: (selectedOption.dataset.guarantorIdNumber ?? '').trim(),
                    guarantorPhone: (selectedOption.dataset.guarantorPhone ?? '').trim(),
                };
            },
            updateClientPreview(data) {
                this.selectedClientPreview = {
                    fullName: data?.fullName ?? '',
                    phone: data?.phone ?? '',
                    idNumber: data?.idNumber ?? '',
                    address: data?.address ?? '',
                };
            },
            setIfSafe(fieldId, nextValue) {
                const input = this.$el.querySelector(`#${fieldId}`);
                if (!input) return;

                const current = (input.value ?? '').trim();
                const previousAuto = (this.lastAutoFilled[fieldId] ?? '').trim();
                const candidate = (nextValue ?? '').trim();

                if (candidate === '') return;
                if (current !== '' && current !== previousAuto) return;

                input.value = candidate;
                this.lastAutoFilled[fieldId] = candidate;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
            autofillFromSelectedClient() {
                const data = this.clientDataset();
                this.updateClientPreview(data);
                if (!data) return;

                this.setIfSafe('branch', data.branch);
                this.setIfSafe('applicant_signature_name', data.fullName);
                this.setIfSafe('guarantor_full_name', data.guarantorFullName);
                this.setIfSafe('guarantor_id_number', data.guarantorIdNumber);
                this.setIfSafe('guarantor_phone', data.guarantorPhone);

                const guarantorSignature = data.guarantorFullName !== '' ? data.guarantorFullName : data.fullName;
                this.setIfSafe('guarantor_signature_name', guarantorSignature);
            },
            async saveNewProduct() {
                this.productModalError = '';
                if (!this.newProductName) {
                    this.productModalError = 'Product name is required.';
                    return;
                }
                if (!productStoreUrl || String(productStoreUrl).includes('/undefined')) {
                    this.productModalError = 'Product endpoint is invalid. Reload page and try again.';
                    return;
                }

                this.isSavingProduct = true;
                try {
                    const response = await fetch(productStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                        },
                        body: JSON.stringify({
                            name: this.newProductName,
                            description: this.newProductDescription,
                            default_interest_rate: this.newProductInterestRate,
                            default_term_months: this.newProductTermMonths,
                            default_term_unit: this.newProductTermUnit,
                            default_interest_rate_period: this.newProductInterestRatePeriod,
                        }),
                    });

                    const data = await response.json();
                    if (!response.ok || !data?.ok || !data?.product?.name) {
                        this.productModalError = data?.message ?? 'Unable to save product. Please try again.';
                        return;
                    }

                    const productSelect = this.$el.querySelector('#product_name');
                    const existingOption = Array.from(productSelect.options).find((option) => option.value === data.product.name);
                    if (!existingOption) {
                        const option = new Option(data.product.name, data.product.name, true, true);
                        productSelect.add(option);
                    } else {
                        existingOption.selected = true;
                    }
                    this.productMetaByName[data.product.name] = {
                        default_interest_rate: data.product.default_interest_rate ?? null,
                        default_term_months: data.product.default_term_months ?? null,
                        default_term_unit: data.product.default_term_unit ?? 'monthly',
                        default_interest_rate_period: data.product.default_interest_rate_period ?? 'annual',
                    };
                    productSelect.dispatchEvent(new Event('change'));
                    this.closeProductModal();
                } catch (error) {
                    this.productModalError = 'Unable to save product. Please check your connection.';
                } finally {
                    this.isSavingProduct = false;
                }
            },
            async saveNewBranch() {
                this.branchModalError = '';
                if (!this.newBranchName) {
                    this.branchModalError = 'Branch name is required.';
                    return;
                }

                this.isSavingBranch = true;
                try {
                    const response = await fetch(@json(route('loan.clients.branches.store')), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                        },
                        body: JSON.stringify({ name: this.newBranchName }),
                    });

                    const data = await response.json();
                    if (!response.ok || !data?.ok || !data?.branch?.name) {
                        this.branchModalError = data?.message ?? 'Unable to save branch. Please try again.';
                        return;
                    }

                    const branchSelect = this.$el.querySelector('#branch');
                    const existingOption = Array.from(branchSelect.options).find((option) => option.value === data.branch.name);
                    if (!existingOption) {
                        const option = new Option(data.branch.name, data.branch.name, true, true);
                        branchSelect.add(option);
                    } else {
                        existingOption.selected = true;
                    }
                    branchSelect.dispatchEvent(new Event('change'));
                    this.closeBranchModal();
                } catch (error) {
                    this.branchModalError = 'Unable to save branch. Please check your connection.';
                } finally {
                    this.isSavingBranch = false;
                }
            },
        };
    }
</script>
