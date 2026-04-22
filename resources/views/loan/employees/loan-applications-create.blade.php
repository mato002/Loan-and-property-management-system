<x-loan-layout>
    <x-loan.page
        title="New staff loan application"
        subtitle="A reference (SLA-xxxxx) is assigned after save."
    >
        @php
            $mapped = $staffLoanMappedFields ?? [];
            $customFormFields = $staffLoanCustomFields ?? [];
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.employees.loan_applications') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to pipeline
            </a>
        </x-slot>

        @if ($employees->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <a href="{{ route('loan.employees.create') }}" class="font-semibold underline">Add an employee</a> first.
            </div>
        @else
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
                <form method="post" action="{{ route('loan.employees.loan_applications.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="employee_id" value="Employee" />
                        <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            <option value="">Choose…</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>{{ $emp->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                    </div>
                    <div>
                        <x-input-label for="product" :value="$mapped['product']['label'] ?? 'Product'" />
                        <x-text-input id="product" name="product" type="text" class="mt-1 block w-full" :value="old('product')" required placeholder="e.g. Salary advance" />
                        <x-input-error class="mt-2" :messages="$errors->get('product')" />
                    </div>
                    <div>
                        <x-input-label for="amount" :value="$mapped['amount']['label'] ?? 'Amount (Ksh)'" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('amount')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                    </div>
                    <div>
                        <x-input-label for="stage" :value="$mapped['stage']['label'] ?? 'Stage (optional)'" />
                        <x-text-input id="stage" name="stage" type="text" class="mt-1 block w-full" :value="old('stage', 'Submitted')" />
                        <x-input-error class="mt-2" :messages="$errors->get('stage')" />
                    </div>
                    @if (!empty($customFormFields))
                        <div class="border-t border-slate-200 pt-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Additional staff loan fields</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach ($customFormFields as $field)
                                    @php
                                        $fieldKey = (string) ($field['key'] ?? '');
                                        $fieldType = (string) ($field['data_type'] ?? 'alphanumeric');
                                        $fieldLabel = (string) ($field['label'] ?? $fieldKey);
                                        $fieldValue = old("form_meta.$fieldKey");
                                        $options = (array) ($field['select_options'] ?? []);
                                    @endphp
                                    @if ($fieldType === 'long_text')
                                        <div class="sm:col-span-2">
                                            <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                            <textarea id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" rows="3" class="w-full rounded-lg border-slate-300 text-sm">{{ $fieldValue }}</textarea>
                                            @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                    @elseif ($fieldType === 'number')
                                        <div>
                                            <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                            <input id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" type="number" step="0.01" value="{{ $fieldValue }}" class="w-full rounded-lg border-slate-300 text-sm tabular-nums" />
                                            @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                    @elseif ($fieldType === 'select')
                                        <div>
                                            <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                            <select id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" class="w-full rounded-lg border-slate-300 text-sm">
                                                <option value="">Select…</option>
                                                @foreach ($options as $option)
                                                    <option value="{{ $option }}" @selected((string) $fieldValue === (string) $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                    @else
                                        <div>
                                            <label for="form_meta_{{ $fieldKey }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $fieldLabel }}</label>
                                            <input id="form_meta_{{ $fieldKey }}" name="form_meta[{{ $fieldKey }}]" type="text" value="{{ $fieldValue }}" class="w-full rounded-lg border-slate-300 text-sm" />
                                            @error("form_meta.$fieldKey")<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <x-primary-button>{{ __('Submit application') }}</x-primary-button>
                </form>
            </div>
        @endif
    </x-loan.page>
</x-loan-layout>
