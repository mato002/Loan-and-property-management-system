<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-3xl">
            <form method="post" action="{{ route('loan.book.applications.update', $application) }}" class="px-5 py-6 space-y-4">
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
                    <label for="product_name" class="block text-xs font-semibold text-slate-600 mb-1">Product</label>
                    <select id="product_name" name="product_name" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select product...</option>
                        @foreach (($productOptions ?? []) as $productName)
                            <option value="{{ $productName }}" @selected(old('product_name', $application->product_name) === $productName)>{{ $productName }}</option>
                        @endforeach
                    </select>
                    @error('product_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="amount_requested" class="block text-xs font-semibold text-slate-600 mb-1">Amount requested</label>
                        <input id="amount_requested" name="amount_requested" type="number" step="0.01" min="0" value="{{ old('amount_requested', $application->amount_requested) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('amount_requested')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="term_months" class="block text-xs font-semibold text-slate-600 mb-1">Term (months)</label>
                        <input id="term_months" name="term_months" type="number" min="1" value="{{ old('term_months', $application->term_months) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                        @error('term_months')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="stage" class="block text-xs font-semibold text-slate-600 mb-1">Stage</label>
                    <select id="stage" name="stage" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($stages as $value => $label)
                            <option value="{{ $value }}" @selected(old('stage', $application->stage) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
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

                <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4 space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Loan department form</h3>
                        <p class="mt-1 text-xs text-slate-500">Applicant <strong>name</strong>, <strong>phone</strong>, <strong>home address</strong> and <strong>ID</strong> are on the client profile.</p>
                    </div>
                    <div>
                        <label for="applicant_pin_location_code" class="block text-xs font-semibold text-slate-600 mb-1">Home / business PIN location code</label>
                        <input id="applicant_pin_location_code" name="applicant_pin_location_code" type="text" value="{{ old('applicant_pin_location_code', $application->applicant_pin_location_code) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('applicant_pin_location_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="applicant_signature_name" class="block text-xs font-semibold text-slate-600 mb-1">Applicant sign (full name)</label>
                        <input id="applicant_signature_name" name="applicant_signature_name" type="text" value="{{ old('applicant_signature_name', $application->applicant_signature_name) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                        @error('applicant_signature_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Guarantor details</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label for="guarantor_full_name" class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                                <input id="guarantor_full_name" name="guarantor_full_name" type="text" value="{{ old('guarantor_full_name', $application->guarantor_full_name) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_full_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="guarantor_id_number" class="block text-xs font-semibold text-slate-600 mb-1">ID no.</label>
                                <input id="guarantor_id_number" name="guarantor_id_number" type="text" value="{{ old('guarantor_id_number', $application->guarantor_id_number) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_id_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="guarantor_phone" class="block text-xs font-semibold text-slate-600 mb-1">Tel no.</label>
                                <input id="guarantor_phone" name="guarantor_phone" type="text" value="{{ old('guarantor_phone', $application->guarantor_phone) }}" class="w-full rounded-lg border-slate-200 text-sm" />
                                @error('guarantor_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="guarantor_signature_name" class="block text-xs font-semibold text-slate-600 mb-1">Guarantor signature (full name)</label>
                                <input id="guarantor_signature_name" name="guarantor_signature_name" type="text" value="{{ old('guarantor_signature_name', $application->guarantor_signature_name) }}" class="w-full rounded-lg border-slate-200 text-sm" />
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
