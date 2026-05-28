@php
    $visibleActions = $visibleActions();
    $isSubmitMode = $mode === 'submit' && $action;
    $hasApply = $isSubmitMode && $visibleActions->isNotEmpty();
@endphp

<div
    data-property-bulk-bar
    data-bulk-form-id="{{ $formId }}"
    data-bulk-checkbox-selector="{{ $rowCheckboxSelector }}"
    data-bulk-mode="{{ $mode }}"
    @if ($syncForm) data-bulk-sync-form="{{ $syncForm }}" @endif
    @if ($syncInput) data-bulk-sync-input="{{ $syncInput }}" @endif
    @if ($showWhenEmpty) data-bulk-show-when-empty="1" @endif
    @unless($showWhenEmpty) hidden @endunless
    class="property-bulk-bar print-hide w-full min-w-0 rounded-xl border border-indigo-200 dark:border-indigo-500/40 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm shadow-md md:shadow-sm"
    role="region"
    aria-label="Bulk actions"
>
    @if ($isSubmitMode)
        <form
            id="{{ $formId }}"
            method="{{ strtolower($method) === 'get' ? 'get' : 'post' }}"
            action="{{ $action }}"
            data-turbo-frame="{{ $turboFrame }}"
            @if ($confirm) data-swal-confirm="{{ $confirm }}" @endif
            class="flex flex-col gap-3 p-3 md:flex-row md:flex-wrap md:items-center md:gap-2 md:py-2.5 md:px-3"
        >
            @if (strtolower($method) !== 'get')
                @csrf
            @endif
            <div class="flex flex-wrap items-center gap-2 min-w-0 flex-1">
                <label class="inline-flex min-h-[44px] md:min-h-0 items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-3 py-2 text-sm text-slate-700 dark:text-slate-200" data-row-ignore-click>
                    <input
                        type="checkbox"
                        data-bulk-select-all
                        class="h-5 w-5 md:h-4 md:w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="font-medium">Page</span>
                </label>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 tabular-nums" data-bulk-count aria-live="polite">0 selected</p>
                <button
                    type="button"
                    data-bulk-clear
                    class="min-h-[44px] md:min-h-0 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                >
                    Clear
                </button>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:items-center sm:ml-auto w-full md:w-auto">
                @if ($hasApply)
                    <select
                        name="action"
                        required
                        data-bulk-action-select
                        class="min-h-[44px] md:min-h-0 w-full sm:min-w-[12rem] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2.5"
                    >
                        <option value="">Bulk action…</option>
                        @foreach ($visibleActions as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    <button
                        type="submit"
                        data-bulk-apply
                        disabled
                        class="min-h-[44px] md:min-h-0 w-full sm:w-auto rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span data-bulk-apply-label>{{ $applyLabel }}</span>
                        <span data-bulk-apply-loading class="hidden" aria-hidden="true">Applying…</span>
                    </button>
                @endif
            </div>
        </form>
    @else
        <div class="flex flex-col gap-3 p-3 md:flex-row md:flex-wrap md:items-center md:gap-2 md:py-2.5 md:px-3">
            <div class="flex flex-wrap items-center gap-2 min-w-0 flex-1">
                <label class="inline-flex min-h-[44px] md:min-h-0 items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-3 py-2 text-sm text-slate-700 dark:text-slate-200" data-row-ignore-click>
                    <input
                        type="checkbox"
                        data-bulk-select-all
                        class="h-5 w-5 md:h-4 md:w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="font-medium">Page</span>
                </label>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 tabular-nums" data-bulk-count aria-live="polite">0 selected</p>
                <button
                    type="button"
                    data-bulk-clear
                    class="min-h-[44px] md:min-h-0 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                >
                    Clear
                </button>
            </div>
            @if (isset($slot) && ! $slot->isEmpty())
                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto md:ml-auto">
                    {{ $slot }}
                </div>
            @endif
        </div>
    @endif
</div>
