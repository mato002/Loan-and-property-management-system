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
    $wrapClass = 'property-filter-field min-w-0 shrink-0 '.($wide
        ? 'w-full md:min-w-[11rem] md:max-w-[16rem] md:flex-[1_1_12rem]'
        : 'w-full md:w-auto md:min-w-[6.75rem] md:max-w-[11rem]');
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
    $isPageSize = is_string($name) && preg_match('/(?:^|_)per_page$/', $name) === 1;
    if ($isPageSize && $type === 'select') {
        $queryRaw = request()->query($name);
        if (is_scalar($queryRaw) && strtolower(trim((string) $queryRaw)) === \App\Support\ListPageSize::ALL) {
            $resolvedValue = \App\Support\ListPageSize::ALL;
        }
        $normalizedOptions = collect(\App\Support\ListPageSize::options(null, $resolvedValue))->map(function ($opt) {
            return [
                'value' => (string) ($opt['value'] ?? ''),
                'label' => (string) ($opt['label'] ?? ''),
                'selected' => false,
            ];
        });
        $wrapClass = 'property-filter-field min-w-0 shrink-0 w-full md:w-auto md:min-w-[14rem] md:max-w-[18rem]';
    }
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
            @if ($isPageSize)<div class="flex items-center gap-1">@endif
            <select
                @if ($fieldId) id="{{ $fieldId }}" @endif
                @if ($name) name="{{ $name }}" @endif
                @if ($isPageSize) data-page-size-select="1" data-property-searchable="false" @endif
                {!! $formAttr !!}
                @unless ($isPageSize) data-property-searchable="true" @endunless
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
            @if ($isPageSize)
                <input
                    type="number"
                    min="1"
                    step="1"
                    inputmode="numeric"
                    placeholder="No."
                    data-page-size-custom="1"
                    aria-label="Custom rows per page"
                    title="Type how many rows to show, then click Apply"
                    class="property-filter-field__control min-h-[44px] md:min-h-[38px] w-[4.25rem] shrink-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-2 text-sm text-slate-900 dark:text-slate-100"
                />
            @endif
            @if ($isPageSize)</div>@endif
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
                @if ($type === 'search') autocomplete="off" data-live-row-filter="1" data-server-search="false" @endif
                {!! $formAttr !!}
                class="{{ $inputClass }} {{ $type === 'search' ? 'md:min-w-[11rem] md:max-w-[18rem]' : '' }}"
                @if ($type === 'number') step="any" @endif
            />
        @endif
    </div>
@endif
