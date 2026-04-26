<div class="border-t border-slate-200 pt-4">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-3">Additional setup fields</h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach ($customFormFields as $field)
            @php
                $fieldKey = (string) ($field['key'] ?? '');
                $fieldType = (string) ($field['data_type'] ?? 'alphanumeric');
                $fieldLabel = (string) ($field['label'] ?? $fieldKey);
                $fieldValue = old("form_meta.$fieldKey", data_get($draftApplication ?? null, "form_meta.$fieldKey"));
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
                        <option value="">Select...</option>
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
