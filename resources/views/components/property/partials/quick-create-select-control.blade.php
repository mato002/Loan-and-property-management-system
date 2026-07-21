@php
    /** @var bool $useSearch */
    /** @var string $id */
    /** @var string $name */
    /** @var bool $required */
    /** @var string $placeholder */
    /** @var array<int, array<string, mixed>> $options */
@endphp

@if ($useSearch)
    <div class="relative min-w-0 flex-1" @click.outside="closePicker()">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-900 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-100"
            @click="togglePicker()"
            :aria-expanded="pickerOpen"
        >
            <span class="truncate" x-text="selectedLabel"></span>
            <i class="fa-solid fa-chevron-down shrink-0 text-xs text-slate-400" aria-hidden="true"></i>
        </button>

        <div
            x-show="pickerOpen"
            x-cloak
            x-transition
            class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-gray-900"
        >
            <div class="border-b border-slate-100 p-2 dark:border-slate-700">
                <input
                    x-ref="searchInput"
                    type="search"
                    x-model="query"
                    placeholder="{{ __('Search name, phone, or email…') }}"
                    class="w-full rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm dark:border-slate-600 dark:bg-gray-950"
                    @keydown.escape.prevent="closePicker()"
                />
            </div>
            <ul class="max-h-56 overflow-y-auto py-1 text-sm" role="listbox">
                <template x-if="filteredOptions.length === 0">
                    <li class="px-3 py-2 text-slate-500">{{ __('No matches') }}</li>
                </template>
                <template x-for="opt in filteredOptions" :key="String(opt.value)">
                    <li>
                        <button
                            type="button"
                            class="flex w-full px-3 py-2 text-left hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                            :class="String(opt.value) === selectedValue ? 'bg-emerald-50 font-semibold text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100' : 'text-slate-800 dark:text-slate-100'"
                            @click="pickOption(opt)"
                            x-text="opt.label"
                        ></button>
                    </li>
                </template>
            </ul>
        </div>

        <select
            x-ref="nativeSelect"
            id="{{ $id }}"
            name="{{ $name }}"
            @if($required) required @endif
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
        >
            <option value="">{{ $placeholder }}</option>
            @foreach ($options as $opt)
                <option
                    value="{{ $opt['value'] }}"
                    @if(!empty($opt['selected'])) selected @endif
                    @if(!empty($opt['disabled'])) disabled @endif
                    @foreach ((array) ($opt['attrs'] ?? []) as $attrKey => $attrVal)
                        {{ $attrKey }}="{{ is_scalar($attrVal) ? $attrVal : '' }}"
                    @endforeach
                >{{ $opt['label'] }}</option>
            @endforeach
        </select>
    </div>
@else
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if($required) required @endif
        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-gray-900"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $opt)
            <option
                value="{{ $opt['value'] }}"
                @if(!empty($opt['selected'])) selected @endif
                @if(!empty($opt['disabled'])) disabled @endif
                @foreach ((array) ($opt['attrs'] ?? []) as $attrKey => $attrVal)
                    {{ $attrKey }}="{{ is_scalar($attrVal) ? $attrVal : '' }}"
                @endforeach
            >{{ $opt['label'] }}</option>
        @endforeach
    </select>
@endif
