@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\LoanFormFieldDefinition> $dynamicLoanApplicationFields */
    $fields = $dynamicLoanApplicationFields ?? collect();
@endphp
@if ($fields->isNotEmpty())
    <div class="space-y-4 border-t border-slate-200 pt-4">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600">Additional information</h4>
        @foreach ($fields as $field)
            @php
                $fk = (string) $field->field_key;
                $selectOpts = collect(explode(',', (string) ($field->select_options ?? '')))
                    ->map(fn (string $o): string => trim($o))
                    ->filter()
                    ->values();
                $oldVal = old('form_meta.'.$fk, data_get($draftApplication?->form_meta, $fk));
            @endphp
            <div
                data-field-key="{{ $fk }}"
                data-is-core="0"
            >
                <label for="form_meta_{{ $fk }}" class="block text-xs font-semibold text-slate-600 mb-1">{{ $field->label }}</label>
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
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
@endif
