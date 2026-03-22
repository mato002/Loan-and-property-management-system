@php
    $hasFile = collect($fields)->contains(fn ($f) => ($f['type'] ?? 'text') === 'file');
@endphp

<x-property-layout>
    <x-slot name="header">{{ $title }}</x-slot>

    <x-property.page :title="$title" :subtitle="$subtitle">
        <div class="flex flex-wrap items-center gap-3">
            <a
                href="{{ route($backRoute) }}"
                class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
            >
                {{ $backLabel }}
            </a>
        </div>

        <form
            method="post"
            action="{{ route($storeRoute, $formKey) }}"
            class="space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm w-full max-w-2xl min-w-0"
            @if ($hasFile) enctype="multipart/form-data" @endif
        >
            @csrf
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/40 dark:border-red-800 px-3 py-2 text-sm text-red-800 dark:text-red-200">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach ($fields as $field)
                @php
                    $name = $field['name'];
                    $label = $field['label'];
                    $type = $field['type'] ?? 'text';
                    $required = ! empty($field['required']);
                    $placeholder = $field['placeholder'] ?? null;
                    $inputClass = 'mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3';
                @endphp

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $name }}">{{ $label }}</label>

                    @if ($type === 'textarea')
                        <textarea
                            id="{{ $name }}"
                            name="{{ $name }}"
                            rows="5"
                            @if ($required) required @endif
                            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                            class="{{ $inputClass }}"
                        >{{ old($name) }}</textarea>
                    @elseif ($type === 'file')
                        <input
                            type="file"
                            id="{{ $name }}"
                            name="{{ $name }}"
                            class="mt-1 block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-slate-700"
                        />
                    @elseif ($type === 'date')
                        <input
                            type="date"
                            id="{{ $name }}"
                            name="{{ $name }}"
                            value="{{ old($name) }}"
                            @if ($required) required @endif
                            class="{{ $inputClass }} sm:max-w-xs"
                        />
                    @else
                        <input
                            type="{{ $type === 'email' ? 'email' : 'text' }}"
                            id="{{ $name }}"
                            name="{{ $name }}"
                            value="{{ old($name) }}"
                            @if ($required) required @endif
                            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                            class="{{ $inputClass }}"
                        />
                    @endif
                </div>
            @endforeach

            <div class="flex flex-col sm:flex-row gap-2 pt-2">
                <button
                    type="submit"
                    class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                >
                    {{ $submitLabel }}
                </button>
                <a
                    href="{{ route($backRoute) }}"
                    class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                >
                    Cancel
                </a>
            </div>
        </form>
    </x-property.page>
</x-property-layout>
