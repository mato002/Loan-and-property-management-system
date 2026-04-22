<x-loan-layout>
    <x-loan.page title="Edit salary advance" subtitle="">
        @php
            $mapped = $salaryAdvanceMappedFields ?? [];
            $customFormFields = $salaryAdvanceCustomFields ?? [];
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.advances.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-xl overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.advances.update', $row) }}" enctype="multipart/form-data" class="px-5 py-6 space-y-4">
                @csrf
                @method('patch')
                <div>
                    <label for="employee_id" class="block text-xs font-semibold text-slate-600 mb-1">Employee</label>
                    <select id="employee_id" name="employee_id" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}" @selected(old('employee_id', $row->employee_id) == $e->id)>{{ $e->employee_number }} · {{ $e->full_name }}</option>
                        @endforeach
                    </select>
                    @error('employee_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['amount']['label'] ?? 'Amount' }}</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $row->amount) }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="currency" class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', $row->currency) }}" maxlength="8" class="w-full rounded-lg border-slate-200 text-sm uppercase" />
                    @error('currency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="requested_on" class="block text-xs font-semibold text-slate-600 mb-1">Requested on</label>
                    <input id="requested_on" name="requested_on" type="date" value="{{ old('requested_on', $row->requested_on->toDateString()) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('requested_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reason_for_request" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['reason_for_request']['label'] ?? 'Reason for request' }}</label>
                    <textarea id="reason_for_request" name="reason_for_request" rows="2" maxlength="5000" class="w-full rounded-lg border-slate-200 text-sm">{{ old('reason_for_request', $row->reason_for_request) }}</textarea>
                    @error('reason_for_request')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @if (!empty($customFormFields))
                    <div class="border-t border-slate-200 pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Additional salary advance fields</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($customFormFields as $field)
                                @php
                                    $fieldKey = (string) ($field['key'] ?? '');
                                    $fieldType = (string) ($field['data_type'] ?? 'alphanumeric');
                                    $fieldLabel = (string) ($field['label'] ?? $fieldKey);
                                    $fieldValue = old("form_meta.$fieldKey", data_get($row->form_meta ?? [], $fieldKey));
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
                                        @if (!empty(data_get($row->form_meta ?? [], $fieldKey)))
                                            <p class="mt-1 text-[11px] text-slate-500">Current file saved. Upload to replace.</p>
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
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $row->notes) }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Update</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
