@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\LoanFormFieldDefinition> $dynamicLoanApplicationFields */
    $fields = $dynamicLoanApplicationFields ?? collect();
@endphp
@if ($fields->isNotEmpty())
    <div class="space-y-4">
        @foreach ($fields as $field)
            @php
                $fk = (string) $field->field_key;
                $selectOpts = collect(explode(',', (string) ($field->select_options ?? '')))
                    ->map(fn (string $o): string => trim($o))
                    ->filter()
                    ->values();
                $oldVal = old('form_meta.'.$fk, data_get($draftApplication?->form_meta, $fk));
                $existingFileUrl = null;
                if (is_string($oldVal) && $oldVal !== '' && in_array((string) $field->data_type, [\App\Models\LoanFormFieldDefinition::TYPE_IMAGE, \App\Models\LoanFormFieldDefinition::TYPE_DOCUMENT], true)) {
                    $existingFileUrl = \Illuminate\Support\Str::startsWith($oldVal, ['http://', 'https://']) ? $oldVal : asset('storage/'.ltrim($oldVal, '/'));
                }
            @endphp
            <div
                data-field-key="{{ $fk }}"
                data-is-core="0"
            >
                <label for="form_meta_{{ $fk }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $field->label }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                @switch($field->data_type)
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_LONG_TEXT)
                        <textarea
                            id="form_meta_{{ $fk }}"
                            name="form_meta[{{ $fk }}]"
                            rows="3"
                            class="w-full rounded-lg border-slate-200 text-sm"
                        >{{ is_scalar($oldVal) || $oldVal === null ? (string) ($oldVal ?? '') : '' }}</textarea>
                        @break
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_SELECT)
                        <select id="form_meta_{{ $fk }}" name="form_meta[{{ $fk }}]" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Select…</option>
                            @foreach ($selectOpts as $opt)
                                <option value="{{ $opt }}" @selected((string) $oldVal === (string) $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                        @break
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_MONEY)
                        <input
                            id="form_meta_{{ $fk }}"
                            name="form_meta[{{ $fk }}]"
                            type="number"
                            step="0.01"
                            min="0"
                            value="{{ is_scalar($oldVal) || $oldVal === null ? (string) ($oldVal ?? '') : '' }}"
                            class="w-full rounded-lg border-slate-200 text-sm tabular-nums"
                        />
                        @break
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_NUMBER)
                        <input
                            id="form_meta_{{ $fk }}"
                            name="form_meta[{{ $fk }}]"
                            type="number"
                            step="any"
                            value="{{ is_scalar($oldVal) || $oldVal === null ? (string) ($oldVal ?? '') : '' }}"
                            class="w-full rounded-lg border-slate-200 text-sm tabular-nums"
                        />
                        @break
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_DATE)
                        <input
                            id="form_meta_{{ $fk }}"
                            name="form_meta[{{ $fk }}]"
                            type="date"
                            value="{{ is_scalar($oldVal) || $oldVal === null ? (string) ($oldVal ?? '') : '' }}"
                            class="w-full rounded-lg border-slate-200 text-sm"
                        />
                        @break
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_BOOLEAN)
                        <select id="form_meta_{{ $fk }}" name="form_meta[{{ $fk }}]" class="w-full rounded-lg border-slate-200 text-sm">
                            <option value="">—</option>
                            <option value="1" @selected((string) $oldVal === '1' || $oldVal === true || $oldVal === 1)>Yes</option>
                            <option value="0" @selected((string) $oldVal === '0' || $oldVal === false || $oldVal === 0)>No</option>
                        </select>
                        @break
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_IMAGE)
                        <input
                            id="form_meta_{{ $fk }}"
                            name="form_meta[{{ $fk }}]"
                            type="file"
                            accept="image/*"
                            class="block w-full text-sm text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700"
                        />
                        @if ($existingFileUrl)
                            <p class="mt-1 text-[11px] text-slate-500">
                                Current file:
                                <a href="{{ $existingFileUrl }}" target="_blank" rel="noopener" class="font-semibold text-blue-700 underline">View</a>
                            </p>
                        @endif
                        @break
                    @case(\App\Models\LoanFormFieldDefinition::TYPE_DOCUMENT)
                        <input
                            id="form_meta_{{ $fk }}"
                            name="form_meta[{{ $fk }}]"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt,application/pdf"
                            class="block w-full text-sm text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700"
                        />
                        @if ($existingFileUrl)
                            <p class="mt-1 text-[11px] text-slate-500">
                                Current file:
                                <a href="{{ $existingFileUrl }}" target="_blank" rel="noopener" class="font-semibold text-blue-700 underline">Download</a>
                            </p>
                        @endif
                        @break
                    @default
                        <input
                            id="form_meta_{{ $fk }}"
                            name="form_meta[{{ $fk }}]"
                            type="text"
                            value="{{ is_scalar($oldVal) || $oldVal === null ? (string) ($oldVal ?? '') : '' }}"
                            class="w-full rounded-lg border-slate-200 text-sm"
                        />
                @endswitch
                @error('form_meta.'.$fk)
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
@endif
