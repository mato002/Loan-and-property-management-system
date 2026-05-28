@props([
    'label' => 'Filters',
    'activeCount' => 0,
])

<div
    x-data="{ filterOpen: false }"
    x-on:keydown.escape.window="filterOpen = false"
    {{ $attributes->merge(['class' => 'w-full min-w-0']) }}
>
    <button
        type="button"
        @click="filterOpen = true"
        class="inline-flex w-full min-h-[44px] items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50"
    >
        <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
        </svg>
        {{ $label }}
        @if ((int) $activeCount > 0)
            <span class="inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white">{{ $activeCount }}</span>
        @endif
    </button>

    <div
        x-show="filterOpen"
        x-cloak
        class="fixed inset-0 z-[6500]"
        role="dialog"
        aria-modal="true"
        :aria-label="@js($label)"
    >
        <div
            x-show="filterOpen"
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-gray-950/60 backdrop-blur-[1px]"
            @click="filterOpen = false"
        ></div>

        <div
            x-show="filterOpen"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute inset-x-0 bottom-0 max-h-[88vh] flex flex-col rounded-t-2xl border border-gray-200 bg-white shadow-2xl"
            @click.stop
        >
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 shrink-0">
                <h2 class="text-base font-semibold text-gray-900">{{ $label }}</h2>
                <button
                    type="button"
                    @click="filterOpen = false"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100"
                    aria-label="Close filters"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
