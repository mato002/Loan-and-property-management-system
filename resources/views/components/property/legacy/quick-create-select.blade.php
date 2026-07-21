@props([
    'name',
    'label' => null,
    'required' => false,
    /** @var array<int,array{value:string|int,label:string,selected?:bool,disabled?:bool,search?:string}> */
    'options' => [],
    'placeholder' => 'Select…',
    'error' => null,
    'create' => ['mode' => 'none'],
    'selectId' => null,
    'searchable' => false,
    'searchMinOptions' => 8,
])

@php
    $id = $selectId ?: ('qcs-'.preg_replace('/[^a-zA-Z0-9\-_]/', '-', (string) $name).'-'.substr(md5((string) $name), 0, 6));
    $createMode = (string) ($create['mode'] ?? 'none');
    $useSearch = $searchable || count($options) >= $searchMinOptions;
    $qcsConfig = [
        'selectId' => $id,
        'placeholder' => $placeholder,
        'options' => $options,
        'useSearch' => $useSearch,
        'createMode' => $createMode,
        'createEndpoint' => (string) ($create['endpoint'] ?? ''),
        'createFields' => (array) ($create['fields'] ?? []),
    ];
@endphp

<div x-data="propertyQuickCreateSelect({{ \Illuminate\Support\Js::from($qcsConfig) }})">
    @if ($label)
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $label }}</label>
    @endif

    <div class="mt-1 flex items-stretch gap-2">
        @include('components.property.partials.quick-create-select-control', [
            'useSearch' => $useSearch,
            'id' => $id,
            'name' => $name,
            'required' => $required,
            'placeholder' => $placeholder,
            'options' => $options,
        ])

        @if ($createMode === 'ajax')
            <button type="button" @click="open = true" class="shrink-0 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" title="Create">
                +
            </button>
        @elseif ($createMode === 'link')
            <a href="{{ (string) ($create['link'] ?? '#') }}" target="_blank" rel="noopener" class="shrink-0 inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800" title="Create in new tab">
                +
            </a>
        @endif
    </div>

    @if ($error)
        <p class="text-xs text-red-600 mt-1">{{ $error }}</p>
    @endif

    @if ($createMode === 'ajax')
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 p-5 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ (string) ($create['title'] ?? 'Create') }}</p>
                        @if (!empty($create['subtitle']))
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $create['subtitle'] }}</p>
                        @endif
                    </div>
                    <button type="button" @click="open = false" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">✕</button>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ((array) ($create['fields'] ?? []) as $f)
                        <div class="{{ (($f['span'] ?? '') === '2' ? 'sm:col-span-2' : '') }}">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">{{ $f['label'] ?? $f['name'] }}</label>
                            @if (($f['type'] ?? 'text') === 'select')
                                <select
                                    id="{{ $id }}-f-{{ $f['name'] }}"
                                    @if(!empty($f['required'])) x-bind:required="open" @endif
                                    x-bind:disabled="!open"
                                    class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-950 text-sm px-3 py-2"
                                >
                                    <option value="">{{ $f['placeholder'] ?? 'Select…' }}</option>
                                    @foreach ((array) ($f['options'] ?? []) as $opt)
                                        <option value="{{ (string) ($opt['value'] ?? '') }}">{{ (string) ($opt['label'] ?? ($opt['value'] ?? '')) }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    id="{{ $id }}-f-{{ $f['name'] }}"
                                    type="{{ $f['type'] ?? 'text' }}"
                                    @if(!empty($f['required'])) x-bind:required="open" @endif
                                    x-bind:disabled="!open"
                                    class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-950 text-sm px-3 py-2"
                                    placeholder="{{ $f['placeholder'] ?? '' }}"
                                />
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 flex flex-col sm:flex-row gap-2 justify-end">
                    <button type="button" @click="open = false" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                        Cancel
                    </button>
                    <button type="button" :disabled="creating" @click="submit()" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed">
                        Create & select
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
