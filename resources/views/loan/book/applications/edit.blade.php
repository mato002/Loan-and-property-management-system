<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        @php
            $mapped = $loanFormMappedFields ?? [];
            $customFormFields = $loanFormCustomFields ?? [];
            $formMeta = (array) ($application->form_meta ?? []);
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-3xl" x-data="loanProductEditMeta(@js($productMetaByName ?? []))" x-init="init()">
            <form method="post" action="{{ route('loan.book.applications.update', $application) }}" enctype="multipart/form-data" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="loan_client_id" class="block text-xs font-semibold text-slate-600 mb-1">Client</label>
                    <select id="loan_client_id" name="loan_client_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('loan_client_id', $application->loan_client_id) == $c->id)>{{ $c->full_name }} · {{ $c->client_number }}</option>
                        @endforeach
                    </select>
                    @error('loan_client_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="product_name" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['product_name']['label'] ?? 'Product' }}</label>
                    <select id="product_name" name="product_name" required class="w-full rounded-lg border-slate-200 text-sm" @change="applyProductDefaults">
                        <option value="">Select product...</option>
                        @foreach (($productOptions ?? []) as $productName)
                            <option value="{{ $productName }}" @selected(old('product_name', $application->product_name) === $productName)>{{ $productName }}</option>
                        @endforeach
                    </select>
                    @error('product_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500" x-text="selectedProductHint"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="amount_requested" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['amount_requested']['label'] ?? 'Amount requested' }}</label>
                        <input id="amount_requested" name="amount_requested" type="number" step="0.01" min="0" value="{{ old('amount_requested', $application->amount_requested) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount_requested')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="term_unit" class="block text-xs font-semibold text-slate-600 mb-1">Term unit</label>
                        <select id="term_unit" name="term_unit" required class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('term_unit', $application->term_unit ?? 'monthly') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('term_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="term_value" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['term_value']['label'] ?? 'Term length' }}</label>
                        <input id="term_value" name="term_value" type="number" min="1" value="{{ old('term_value', $application->term_value ?? $application->term_months) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('term_value')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">Interest rate (%)</label>
                        <input id="interest_rate" name="interest_rate" type="number" step="0.0001" min="0" max="1000" value="{{ old('interest_rate', $application->interest_rate) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('interest_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="interest_rate_period" class="block text-xs font-semibold text-slate-600 mb-1">Interest period</label>
                        <select id="interest_rate_period" name="interest_rate_period" class="w-full rounded-lg border-slate-200 text-sm">
                            @foreach (['daily' => 'Per day', 'weekly' => 'Per week', 'monthly' => 'Per month', 'annual' => 'Per year'] as $v => $lab)
                                <option value="{{ $v }}" @selected(old('interest_rate_period', $application->interest_rate_period ?? 'annual') === $v)>{{ $lab }}</option>
                            @endforeach
                        </select>
                        @error('interest_rate_period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="stage" class="block text-xs font-semibold text-slate-600 mb-1">Stage</label>
                    <select id="stage" name="stage" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($stages as $value => $label)
                            @continue($value === \App\Models\LoanBookApplication::STAGE_DISBURSED && $application->stage !== \App\Models\LoanBookApplication::STAGE_DISBURSED)
                            <option value="{{ $value }}" @selected(old('stage', $application->stage) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">Disbursed is system-controlled and only updates after completed disbursement.</p>
                    @error('stage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">Branch</label>
                    <input id="branch" name="branch" value="{{ old('branch', $application->branch) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('branch')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="purpose" class="block text-xs font-semibold text-slate-600 mb-1">Purpose</label>
                    <textarea id="purpose" name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose', $application->purpose) }}</textarea>
                    @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                @php
                    $productMeta = ($productMetaByName ?? [])[$application->product_name] ?? null;
                    $chargesSummary = trim((string) ($productMeta['charges_summary'] ?? ''));
                @endphp
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">Additional application context</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 text-xs">
                        <div><dt class="text-slate-500">Submission source</dt><dd class="font-medium text-slate-800">{{ $application->submission_source ?: 'manual_internal' }}</dd></div>
                        <div><dt class="text-slate-500">Approved by</dt><dd class="font-medium text-slate-800">{{ $application->loanClient?->assignedEmployee?->full_name ?? 'None' }}</dd></div>
                        <div><dt class="text-slate-500">Guarantor2</dt><dd class="font-medium text-slate-800">{{ $application->loanClient?->guarantor_2_full_name ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Guarantor2 contact</dt><dd class="font-medium text-slate-800"><x-phone-link :value="$application->loanClient?->guarantor_2_phone" /></dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Charges</dt><dd class="font-medium text-slate-800">{{ $chargesSummary !== '' ? $chargesSummary : '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Deductions</dt><dd class="font-medium text-slate-800">Checkoff(0), Prepayment(0)</dd></div>
                    </dl>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Loan department form</h3>
                        <p class="mt-1 text-xs text-slate-500">Applicant <strong>name</strong>, <strong>phone</strong>, <strong>home address</strong> and <strong>ID</strong> are on the client profile.</p>
                    </div>
                    @if (isset($mapped['applicant_pin_location_code']))
                    <div>
                        <label for="applicant_pin_location_code" class="block text-xs font-semibold text-slate-600 mb-1">Home / business PIN location code</label>
                        <input id="applicant_pin_location_code" name="applicant_pin_location_code" type="text" value="{{ old('applicant_pin_location_code', $application->applicant_pin_location_code) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('applicant_pin_location_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    @if (isset($mapped['applicant_signature_name']))
                    <div>
                        <label for="applicant_signature_name" class="block text-xs font-semibold text-slate-600 mb-1">Applicant sign (full name)</label>
                        <input id="applicant_signature_name" name="applicant_signature_name" type="text" value="{{ old('applicant_signature_name', $application->applicant_signature_name) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('applicant_signature_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endif
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Guarantor details</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @if (isset($mapped['guarantor_full_name']))
                            <div class="sm:col-span-2">
                                <label for="guarantor_full_name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                                <input id="guarantor_full_name" name="guarantor_full_name" type="text" value="{{ old('guarantor_full_name', $application->guarantor_full_name) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_full_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            @endif
                            @if (isset($mapped['guarantor_id_number']))
                            <div>
                                <label for="guarantor_id_number" class="block text-xs font-semibold text-slate-600 mb-1">ID no.</label>
                                <input id="guarantor_id_number" name="guarantor_id_number" type="text" value="{{ old('guarantor_id_number', $application->guarantor_id_number) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_id_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            @endif
                            @if (isset($mapped['guarantor_phone']))
                            <div>
                                <label for="guarantor_phone" class="block text-xs font-semibold text-slate-600 mb-1">Tel no.</label>
                                <input id="guarantor_phone" name="guarantor_phone" type="text" value="{{ old('guarantor_phone', $application->guarantor_phone) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            @endif
                            @if (isset($mapped['guarantor_signature_name']))
                            <div class="sm:col-span-2">
                                <label for="guarantor_signature_name" class="block text-xs font-semibold text-slate-600 mb-1">Guarantor signature (full name)</label>
                                <input id="guarantor_signature_name" name="guarantor_signature_name" type="text" value="{{ old('guarantor_signature_name', $application->guarantor_signature_name) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_signature_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            @endif
                        </div>
                    </div>
                    @if (!empty($customFormFields))
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Additional setup fields</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($customFormFields as $field)
                                @php
                                    $fieldKey = (string) ($field['key'] ?? '');
                                    $fieldType = (string) ($field['data_type'] ?? 'alphanumeric');
                                    $fieldLabel = (string) ($field['label'] ?? $fieldKey);
                                    $fieldValue = old("form_meta.$fieldKey", $formMeta[$fieldKey] ?? '');
                                    $options = (array) ($field['select_options'] ?? []);
                                @endphp
                                @if ($fieldType === 'long_text')
                                    <div class="sm:col-span-2">
                                        <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                        <textarea id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ $fieldValue }}</textarea>
                                        @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                @elseif ($fieldType === 'number')
                                    <div>
                                        <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                        <input id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" type="number" step="0.01" value="{{ $fieldValue }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                                        @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                @elseif ($fieldType === 'select')
                                    <div>
                                        <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                        <select id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" class="w-full rounded-lg border-slate-200 text-sm">
                                            <option value="">Select…</option>
                                            @foreach ($options as $option)
                                                <option value="{{ $option }}" @selected((string) $fieldValue === (string) $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                @elseif ($fieldType === 'image')
                                    <div>
                                        <label for="form_files_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                        <input id="form_files_{{ $fieldKey }}" name="form_files[{{ $fieldKey }}]" type="file" accept="image/*" class="w-full rounded-lg border-slate-200 text-sm" />
                                        @if (!empty($formMeta[$fieldKey]))
                                            <a href="{{ Storage::url((string) $formMeta[$fieldKey]) }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-indigo-600 hover:underline">View uploaded file</a>
                                        @endif
                                        @error("form_files.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                @else
                                    <div>
                                        <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                        <input id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" type="text" value="{{ $fieldValue }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                        @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">Repayment agreement</h4>
                        <p class="text-xs text-slate-700 leading-relaxed border border-slate-200 rounded-lg bg-white p-3">
                            I hereby agree to surrender all my properties to {{ config('app.name') }} or Auctioneers to auction my property if I fail or I don't pay the loan as agreed in the conditions.
                        </p>
                        <label class="mt-3 flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="repayment_agreement_accepted" value="1" class="mt-1 rounded border-slate-300 text-[#2f4f4f] focus:ring-[#2f4f4f]" @checked(old('repayment_agreement_accepted', $application->repayment_agreement_accepted)) />
                            <span class="text-sm text-slate-700">Applicant confirms they have read and accept the repayment agreement above.</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Internal notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $application->notes) }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Update</button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
<script>
    function loanProductEditMeta(productMetaByName = {}) {
        return {
            productMetaByName,
            selectedProductHint: '',
            init() {
                this.applyProductDefaults();
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
                    if (termInput && current === '') {
                        termInput.value = String(meta.default_term_months);
                        termInput.dispatchEvent(new Event('input', { bubbles: true }));
                        termInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (termUnitSelect && (!(termUnitSelect.value ?? '').trim() || (termUnitSelect.value ?? '') === 'monthly')) {
                        termUnitSelect.value = defaultTermUnit;
                        termUnitSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                if (meta.charges_summary) {
                    parts.push(`Charges: ${meta.charges_summary}.`);
                }
                this.selectedProductHint = parts.join(' ');
            },
        };
    }
</script>
