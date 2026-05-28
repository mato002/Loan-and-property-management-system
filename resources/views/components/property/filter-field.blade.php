@props([
    'type' => 'text',
    'name' => null,
    'label' => null,
    'value' => null,
    'placeholder' => null,
    /** @var list<array{value: string|int, label: string, selected?: bool}>|list<string, string> $options */
    'options' => [],
    'emptyOption' => null,
    'form' => null,
    'showLabel' => true,
    'wide' => false,
])

@php
    $formAttr = filled($form) ? ' form="'.$form.'"' : '';
    $fieldId = $name ? 'filter-field-'.preg_replace('/[^a-z0-9_-]+/i', '-', (string) $name) : null;
    $resolvedValue = $value ?? request()->query($name);
    $inputClass = 'property-filter-field__control w-full min-h-[44px] md:min-h-[38px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 text-slate-900 dark:text-slate-100';
    $wrapClass = 'property-filter-field min-w-0 '.($wide ? 'sm:min-w-[12rem] flex-1' : 'sm:w-auto');
    $normalizedOptions = collect($options)->map(function ($opt, $key) {
        if (is_array($opt)) {
            return [
                'value' => (string) ($opt['value'] ?? ''),
                'label' => (string) ($opt['label'] ?? ''),
                'selected' => (bool) ($opt['selected'] ?? false),
            ];
        }

        return [
            'value' => (string) $key,
            'label' => (string) $opt,
            'selected' => false,
        ];
    });
@endphp

@if ($type === 'hidden' && $name)
    <input type="hidden" name="{{ $name }}" value="{{ $resolvedValue }}"{!! $formAttr !!} {{ $attributes }} />
@elseif ($type === 'custom')
    <div {{ $attributes->merge(['class' => $wrapClass]) }}>
        @if ($label && $showLabel)
            <label class="property-filter-field__label block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ $label }}</label>
        @endif
        {{ $slot }}
    </div>
@else
    <div {{ $attributes->merge(['class' => $wrapClass]) }}>
        @if ($label && $showLabel)
            <label @if ($fieldId) for="{{ $fieldId }}" @endif class="property-filter-field__label block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1 md:sr-only">{{ $label }}</label>
        @endif

        @if ($type === 'select')
            <select
                @if ($fieldId) id="{{ $fieldId }}" @endif
                @if ($name) name="{{ $name }}" @endif
                {!! $formAttr !!}
                class="{{ $inputClass }}"
            >
                @if ($emptyOption !== null)
                    <option value="">{{ $emptyOption }}</option>
                @endif
                @foreach ($normalizedOptions as $opt)
                    <option
                        value="{{ $opt['value'] }}"
                        @selected($opt['selected'] || (string) $resolvedValue === (string) $opt['value'])
                    >{{ $opt['label'] }}</option>
                @endforeach
            </select>
        @elseif ($type === 'date-range')
            <div class="grid gap-2 sm:grid-cols-2">
                {{ $slot }}
            </div>
        @else
            <input
                @if ($fieldId) id="{{ $fieldId }}" @endif
                type="{{ match ($type) {
                    'search' => 'search',
                    'number' => 'number',
                    'date' => 'date',
                    'month' => 'month',
                    default => 'text',
                } }}"
                @if ($name) name="{{ $name }}" @endif
                value="{{ is_scalar($resolvedValue) ? $resolvedValue : '' }}"
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                {!! $formAttr !!}
                class="{{ $inputClass }} {{ $type === 'search' ? 'md:min-w-[14rem]' : '' }}"
                @if ($type === 'number') step="any" @endif
            />
        @endif
    </div>
@endif
