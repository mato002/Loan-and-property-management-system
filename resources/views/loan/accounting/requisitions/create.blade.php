<x-loan-layout>
    <x-loan.page title="New requisition" subtitle="">
        @php
            $mapped = $accountingRequisitionMappedFields ?? [];
            $customFormFields = $accountingRequisitionCustomFields ?? [];
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.requisitions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-xl overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.requisitions.store') }}" enctype="multipart/form-data" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="title" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['title']['label'] ?? 'Title' }}</label>
                    <input id="title" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="purpose" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['purpose']['label'] ?? 'Purpose / details' }}</label>
                    <textarea id="purpose" name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose') }}</textarea>
                    @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['amount']['label'] ?? 'Amount' }}</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="currency" class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', 'KES') }}" maxlength="8" class="w-full rounded-lg border-slate-200 text-sm uppercase" />
                    @error('currency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @if (!empty($customFormFields))
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Additional requisition fields</h4>
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
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Submit</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
