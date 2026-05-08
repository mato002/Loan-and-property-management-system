@props([
    'fieldKey',
    'mapped',
])

@if (! isset($mapped[$fieldKey]))
@else
    @switch($fieldKey)
        @case('product_name')
            <div data-field-key="{{ $mapped['product_name']['field_key'] }}" data-is-core="{{ ($mapped['product_name']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="product_name" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['product_name']['label'] ?? 'Product' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <div class="flex gap-2">
                    <select id="product_name" name="product_name" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select product...</option>
                        @foreach (($productOptions ?? []) as $productName)
                            <option value="{{ $productName }}" @selected(old('product_name', $draftApplication?->product_name ?? $defaultProductName ?? '') === $productName)>{{ $productName }}</option>
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
            @break

        @case('amount_requested')
            <div data-field-key="{{ $mapped['amount_requested']['field_key'] }}" data-is-core="{{ ($mapped['amount_requested']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="amount_requested" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['amount_requested']['label'] ?? 'Amount requested' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <input id="amount_requested" name="amount_requested" type="number" step="0.01" min="0" value="{{ old('amount_requested', $draftApplication?->amount_requested) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                @error('amount_requested')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break

        @case('term_unit')
            <div data-field-key="{{ $mapped['term_unit']['field_key'] }}" data-is-core="{{ ($mapped['term_unit']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="term_unit" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['term_unit']['label'] ?? 'Term unit' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <select id="term_unit" name="term_unit" class="w-full rounded-lg border-slate-200 text-sm" x-model="termUnit" @change="onTermUnitChange()">
                    <option value="" @selected(old('term_unit', $draftApplication?->term_unit ?? '') === '')>Select term unit…</option>
                    @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $v => $lab)
                        <option value="{{ $v }}" @selected(old('term_unit', $draftApplication?->term_unit ?? '') === $v)>{{ $lab }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-500" x-show="termUnit === ''">Choose how the loan term is measured (days, weeks, or months).</p>
                @error('term_unit')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break

        @case('term_value')
            <div data-field-key="{{ $mapped['term_value']['field_key'] }}" data-is-core="{{ ($mapped['term_value']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="term_value" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['term_value']['label'] ?? 'Term length' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <input id="term_value" name="term_value" type="number" min="1" value="{{ old('term_value', $draftApplication?->term_value) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" placeholder="e.g. 6" />
                @error('term_value')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break

        @case('interest_rate')
            <div data-field-key="{{ $mapped['interest_rate']['field_key'] }}" data-is-core="{{ ($mapped['interest_rate']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="interest_rate" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['interest_rate']['label'] ?? 'Interest rate (%)' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <input id="interest_rate" name="interest_rate" type="number" step="0.0001" min="0" max="1000" value="{{ old('interest_rate', $draftApplication?->interest_rate) }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                @error('interest_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break

        @case('interest_rate_period')
            <div data-field-key="{{ $mapped['interest_rate_period']['field_key'] }}" data-is-core="{{ ($mapped['interest_rate_period']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="interest_rate_period" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['interest_rate_period']['label'] ?? 'Interest period' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <select id="interest_rate_period" name="interest_rate_period" class="w-full rounded-lg border-slate-200 text-sm">
                    @foreach (['daily' => 'Per day', 'weekly' => 'Per week', 'monthly' => 'Per month', 'annual' => 'Per year'] as $v => $lab)
                        <option value="{{ $v }}" @selected(old('interest_rate_period', $draftApplication?->interest_rate_period ?? 'annual') === $v)>{{ $lab }}</option>
                    @endforeach
                </select>
                @error('interest_rate_period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break

        @case('stage')
            <div data-field-key="{{ $mapped['stage']['field_key'] }}" data-is-core="{{ ($mapped['stage']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="stage" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['stage']['label'] ?? 'Stage' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <select id="stage" name="stage" class="w-full rounded-lg border-slate-200 text-sm">
                    @foreach ($stages as $value => $label)
                        @continue($value === \App\Models\LoanBookApplication::STAGE_DISBURSED)
                        <option value="{{ $value }}" @selected(old('stage', $draftApplication?->stage ?? \App\Models\LoanBookApplication::STAGE_SUBMITTED) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-500">Disbursed is set automatically after a completed disbursement record.</p>
                @error('stage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break

        @case('branch')
            <div data-field-key="{{ $mapped['branch']['field_key'] }}" data-is-core="{{ ($mapped['branch']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="branch" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['branch']['label'] ?? 'Branch (optional)' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <div class="flex gap-2">
                    <select id="branch" name="branch" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select branch...</option>
                        @foreach (($branchOptions ?? []) as $branchName)
                            <option value="{{ $branchName }}" @selected(old('branch', $draftApplication?->branch) === $branchName)>{{ $branchName }}</option>
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
            @break

        @case('purpose')
            <div data-field-key="{{ $mapped['purpose']['field_key'] }}" data-is-core="{{ ($mapped['purpose']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="purpose" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['purpose']['label'] ?? 'Purpose' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <textarea id="purpose" name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose', $draftApplication?->purpose ?? $defaultPurpose ?? '') }}</textarea>
                @error('purpose')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break

        @case('notes')
            <div data-field-key="{{ $mapped['notes']['field_key'] }}" data-is-core="{{ ($mapped['notes']['is_core'] ?? false) ? '1' : '0' }}">
                <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">{{ $mapped['notes']['label'] ?? 'Internal notes' }}<span data-required-star class="ml-0.5 text-red-600 font-semibold hidden" aria-hidden="true">*</span></label>
                <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $draftApplication?->notes) }}</textarea>
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            @break
    @endswitch
@endif
