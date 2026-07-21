@props([
    'label' => 'Actions',
    'width' => 'w-48',
])

<div
    {{ $attributes->merge(['class' => 'relative inline-block text-left']) }}
    data-row-ignore-click
>
    <details class="group">
        <summary
            class="list-none cursor-pointer rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 min-h-[44px] inline-flex items-center gap-2 property-touch-target"
        >
            <span>{{ $label }}</span>
            <span class="text-slate-400 hidden sm:inline" aria-hidden="true">▼</span>
            <span class="sm:hidden text-slate-400" aria-hidden="true"><i class="fa-solid fa-ellipsis-vertical"></i></span>
        </summary>
        <div
            class="{{ $width }} absolute right-0 z-[100] mt-1 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 shadow-lg py-0.5"
        >
            {{ $slot }}
        </div>
    </details>
</div>
