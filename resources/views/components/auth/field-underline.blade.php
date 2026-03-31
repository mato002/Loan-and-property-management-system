@props([
    'label' => null,
    'type' => 'text',
    'name',
    'id',
    'value' => '',
    'autocomplete' => null,
    'required' => false,
    'placeholder' => '',
    'error' => null,
])

@php
    $hasError = filled($error);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <label for="{{ $id }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</label>
    @endif
    <div
        class="flex items-stretch gap-3 border-b pb-2.5 transition-colors {{ $hasError ? 'border-red-400' : 'border-slate-200 focus-within:border-[#4d8d82]' }}"
    >
        <span class="flex shrink-0 items-center text-slate-400 [&_svg]:h-5 [&_svg]:w-5" aria-hidden="true">
            {{ $icon ?? '' }}
        </span>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $value }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($required) required @endif
            placeholder="{{ $placeholder }}"
            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0"
        />
        @isset($trailing)
            <div class="flex shrink-0 items-center">{{ $trailing }}</div>
        @endisset
    </div>
    @if ($hasError)
        <p class="text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
