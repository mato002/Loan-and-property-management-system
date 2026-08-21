@php
    /** @var string|null $selected */
    /** @var string|null $selectedLabel */
    $selected = (string) old('invoice_type', $selected ?? 'rent');
    $options = \App\Models\PmInvoice::createTypeOptions();
    if ($selected !== '' && ! isset($options[$selected])) {
        $fallbackLabel = $selectedLabel
            ?? (in_array($selected, ['other', 'mixed'], true) ? 'Charge (set a real type)' : ucfirst(str_replace('_', ' ', $selected)));
        $options = [$selected => $fallbackLabel] + $options;
    }
@endphp
<div>
    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $label ?? 'Charge type' }}</label>
    <div class="mt-1 flex items-stretch gap-2">
        <select
            name="invoice_type"
            data-invoice-type-select
            class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
        >
            @foreach ($options as $typeValue => $typeLabel)
                <option value="{{ $typeValue }}" @selected($selected === (string) $typeValue)>{{ $typeLabel }}</option>
            @endforeach
        </select>
        <button
            type="button"
            class="inline-flex shrink-0 items-center justify-center gap-1 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/40 px-3 text-sm font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50"
            title="Create a new charge type"
            aria-label="Create a new charge type"
            data-invoice-type-add
            data-endpoint="{{ route('property.invoices.types.store') }}"
        >
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>New</span>
        </button>
    </div>
    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Not in the list? Click <span class="font-semibold">New</span> to create one (e.g. Security, Parking, Internet).</p>
</div>
