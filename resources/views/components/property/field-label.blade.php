@props([
    'required' => false,
    'for' => null,
])

@php
    $isRequired = filter_var($required, FILTER_VALIDATE_BOOLEAN);
@endphp

<label
    @if ($for) for="{{ $for }}" @endif
    {{ $attributes->merge(['class' => 'block text-xs font-medium text-slate-600 dark:text-slate-400']) }}
>
    {{ $slot }}
    @if ($isRequired)
        <span class="property-field-required" title="Required" aria-hidden="true">*</span>
        <span class="sr-only">required</span>
    @else
        <span class="property-field-optional">(optional)</span>
    @endif
</label>
