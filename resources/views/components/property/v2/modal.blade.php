@props([
    /** Alpine boolean expression controlling visibility, e.g. "open" or "modal !== ''" */
    'show' => 'open',
    /** Alpine expression executed to close the modal */
    'close' => null,
    /** Optional stable id for stack tracking / debugging */
    'name' => null,
    'title' => null,
    'maxWidth' => 'lg',
    /** Bottom sheet on mobile (< md) */
    'mobileSheet' => true,
    /** z-index for overlay root (default: modal tier 7010) */
    'zIndex' => 7010,
    'closeOnBackdrop' => false,
    'closeOnEscape' => false,
    'ariaLabel' => 'Dialog',
])

@php
    $closeExpr = $close ?? "{$show} = false";
    $modalId = $name ?: ('modal-' . substr(md5((string) $show . ($title ?? '')), 0, 8));
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        'full' => 'max-w-[min(100%,64rem)]',
        default => 'max-w-lg',
    };
    $sheetEnter = $mobileSheet
        ? 'max-md:translate-y-full md:scale-95 md:opacity-0'
        : 'scale-95 opacity-0';
    $sheetEnd = $mobileSheet
        ? 'max-md:translate-y-0 md:scale-100 md:opacity-100'
        : 'scale-100 opacity-100';
@endphp

<template x-teleport="body">
    <div
        x-show="{{ $show }}"
        x-cloak
        data-property-modal
        data-property-modal-id="{{ $modalId }}"
        data-overlay-recoverable
        x-data="{
            registered: false,
            syncModal(open) {
                if (open && !this.registered) {
                    this.registered = true;
                    window.PropertyModalManager?.register({
                        id: @js($modalId),
                        element: $el,
                        closeOnEscape: @js((bool) $closeOnEscape),
                        onClose: () => { {{ $closeExpr }}; this.registered = false; }
                    });
                } else if (!open && this.registered) {
                    window.PropertyModalManager?.unregister(@js($modalId));
                    this.registered = false;
                }
            }
        }"
        x-init="$watch(() => ({{ $show }}), (value) => syncModal(Boolean(value)))"
        @property-modal:request-close="{{ $closeExpr }}"
        class="fixed inset-0 flex {{ $mobileSheet ? 'max-md:items-end md:items-center' : 'items-center' }} justify-center p-4 max-md:p-0"
        style="z-index: {{ (int) $zIndex }};"
        @click.self.stop.prevent
        role="dialog"
        aria-modal="true"
        @if ($title) aria-labelledby="{{ $modalId }}-title" @else aria-label="{{ $ariaLabel }}" @endif
    >
        {{-- Backdrop — absorb clicks; never close on outside tap --}}
        <div
            x-show="{{ $show }}"
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-0 bg-slate-950/50 backdrop-blur-[1px] data-property-modal-backdrop"
            @click.stop.prevent
            aria-hidden="true"
        ></div>

        {{-- Panel — @click.stop prevents backdrop close; safe for native selects --}}
        <div
            x-show="{{ $show }}"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="{{ $sheetEnter }}"
            x-transition:enter-end="{{ $sheetEnd }}"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="{{ $sheetEnd }}"
            x-transition:leave-end="{{ $sheetEnter }}"
            data-property-modal-panel
            data-overlay-dialog
            @click.stop
            class="relative z-10 flex w-full flex-col {{ $maxWidthClass }} max-h-[90vh] overflow-hidden
                {{ $mobileSheet ? 'max-md:max-h-[90vh] max-md:rounded-t-2xl max-md:rounded-b-none rounded-2xl' : 'rounded-2xl' }}
                border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 shadow-2xl"
        >
            @isset($header)
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 dark:border-slate-700 px-4 py-3 sm:px-5">
                    <div class="min-w-0 flex-1">{{ $header }}</div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Close"
                        @click="{{ $closeExpr }}"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            @elseif ($title)
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 dark:border-slate-700 px-4 py-3 sm:px-5">
                    <h2 id="{{ $modalId }}-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Close"
                        @click="{{ $closeExpr }}"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            @endisset

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5 [&_input]:min-h-[44px] [&_select]:min-h-[44px]">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="shrink-0 border-t border-slate-200 dark:border-slate-700 px-4 py-3 sm:px-5 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</template>
