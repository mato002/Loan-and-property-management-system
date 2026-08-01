@props([
    'text' => 'Add tenant → Allocate lease → Bill rent → Collect payment',
    'workflowUrl' => null,
    'storageKey' => 'property.getting_started.dismissed',
])

<div
    x-data="{
        open: localStorage.getItem(@js($storageKey)) !== '1',
        dismiss() {
            localStorage.setItem(@js($storageKey), '1');
            this.open = false;
        },
    }"
    x-show="open"
    x-cloak
    {{ $attributes->merge(['class' => 'print-hide mb-2 rounded-lg border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50/70 dark:bg-indigo-950/30 px-3 py-2']) }}
>
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs sm:text-sm">
        <p class="text-slate-600 dark:text-slate-300 min-w-0">
            <span class="font-semibold text-slate-800 dark:text-slate-100">New here?</span>
            {{ $text }}
        </p>
        <div class="flex items-center gap-2 shrink-0">
            @if ($workflowUrl)
                <a
                    href="{{ $workflowUrl }}"
                    data-turbo-frame="property-main"
                    class="font-semibold text-indigo-700 dark:text-indigo-300 hover:underline whitespace-nowrap"
                >
                    View workflow
                </a>
            @endif
            <button
                type="button"
                class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                @click="dismiss()"
                aria-label="Dismiss getting started tip"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>
