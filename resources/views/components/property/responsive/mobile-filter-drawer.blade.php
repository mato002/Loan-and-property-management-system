@props([
    'label' => 'Filters',
    'activeCount' => 0,
    'formId' => null,
    'turboFrame' => 'property-main',
])

@php
    $drawerId = 'filter-drawer-' . ($formId ?? uniqid('', false));
    $hasDesktopSlot = isset($desktop) && ! $desktop->isEmpty();
    $hasMobileSlot = isset($mobile) && ! $mobile->isEmpty();
    $desktopContent = $hasDesktopSlot ? $desktop : $slot;
    $mobileContent = $hasMobileSlot ? $mobile : $slot;
@endphp

<div
    x-data="{ filterOpen: false }"
    x-on:keydown.escape.window="filterOpen = false"
    x-on:turbo:before-visit.window="filterOpen = false"
    x-on:turbo:frame-load.window="if ($event.target?.id === @js($turboFrame)) filterOpen = false"
    {{ $attributes->merge(['class' => 'w-full min-w-0']) }}
>
    {{-- Desktop: filters inline --}}
    <div class="hidden md:block w-full min-w-0">
        {{ $desktopContent }}
    </div>

    {{-- Mobile: collapsed trigger --}}
    <div class="md:hidden w-full min-w-0 space-y-2">
        @isset($chips)
            @if (! $chips->isEmpty())
                <div class="flex flex-wrap gap-1.5">
                    {{ $chips }}
                </div>
            @endif
        @endisset

        <button
            type="button"
            @click="filterOpen = true; $nextTick(() => window.dispatchEvent(new CustomEvent('property:filter-drawer-open')))"
            class="inline-flex w-full min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            <i class="fa-solid fa-sliders text-slate-500" aria-hidden="true"></i>
            {{ $label }}
            @if ((int) $activeCount > 0)
                <span class="inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white">{{ $activeCount }}</span>
            @endif
        </button>
    </div>

    {{-- Mobile drawer --}}
    <div
        x-show="filterOpen"
        x-cloak
        class="fixed inset-0 z-[6500] md:hidden"
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
            class="absolute inset-0 bg-slate-950/60 backdrop-blur-[1px]"
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
            class="absolute inset-x-0 bottom-0 max-h-[85vh] flex flex-col rounded-t-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-2xl"
            @click.stop
        >
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-200 dark:border-slate-700 shrink-0">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $label }}</h2>
                <button
                    type="button"
                    @click="filterOpen = false"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                    aria-label="Close filters"
                >
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 py-4 space-y-3 [&_form]:space-y-3 [&_input]:w-full [&_input]:min-h-[44px] [&_select]:w-full [&_select]:min-h-[44px] [&_button]:min-h-[44px] [&_label]:text-sm">
                @isset($mobile_filters_extra)
                    @if (! $mobile_filters_extra->isEmpty())
                        <div class="space-y-3 pb-3 border-b border-slate-200 dark:border-slate-700" data-mobile-filters-extra>
                            {{ $mobile_filters_extra }}
                        </div>
                    @endif
                @endisset
                {{ $mobileContent }}
            </div>
            <div class="shrink-0 px-4 py-3 border-t border-slate-200 dark:border-slate-700 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <button
                    type="button"
                    @click="filterOpen = false"
                    class="w-full min-h-[44px] rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</div>
