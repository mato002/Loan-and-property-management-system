@props([
    'title',
    'subtitle' => null,
    'workspace' => null,
    'showWorkspaceTabs' => false,
    'showSearch' => false,
])

@php
    $hasToolbar = isset($toolbar) && ! $toolbar->isEmpty();
    $filterActiveCount = collect(request()->query())
        ->except(['export', 'page', 'per_page'])
        ->filter(static fn ($value) => ! is_null($value) && $value !== '')
        ->count();
@endphp

<div {{ $attributes->merge(['class' => 'max-w-[1600px] mx-auto w-full space-y-4']) }}>
    <header class="space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl sm:text-[1.65rem] font-semibold text-slate-900 tracking-tight leading-tight">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-sm text-slate-600 mt-2 leading-relaxed max-w-3xl">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                @if (! $actions->isEmpty())
                    <div class="flex flex-wrap items-center gap-2 shrink-0 lg:pt-1">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
        @isset($banner)
            <div class="w-full">
                {{ $banner }}
            </div>
        @endisset
    </header>

    @isset($above)
        @if (! $above->isEmpty())
            <div class="w-full min-w-0 space-y-4">
                {{ $above }}
            </div>
        @endif
    @endisset

    @if ($hasToolbar || ($showSearch ?? false))
        <div class="print-hide w-full min-w-0 space-y-2">
            @if ($showSearch ?? false)
                <input
                    type="search"
                    data-table-filter="parent"
                    autocomplete="off"
                    placeholder="Search…"
                    class="hidden md:block w-full min-w-0 min-h-[44px] sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2.5"
                />
            @endif

            @if ($hasToolbar)
                <x-loan.responsive.mobile-filter-drawer
                    :label="'Filters'"
                    :active-count="$filterActiveCount"
                    class="w-full min-w-0"
                >
                    <x-slot name="desktop">
                        @php
                            $__propertyToolbarViewport = 'desktop';
                        @endphp
                        <div class="flex flex-row flex-wrap items-end gap-2 w-full min-w-0 [&_form]:flex [&_form]:flex-row [&_form]:flex-wrap [&_form]:items-end [&_form]:gap-2 [&_form]:w-full [&_form]:min-w-0">
                            {{ $toolbar }}
                        </div>
                    </x-slot>
                    <x-slot name="mobile">
                        @php
                            $__propertyToolbarViewport = 'mobile';
                        @endphp
                        <div class="flex flex-col gap-3 w-full min-w-0 [&_form]:w-full [&_form]:space-y-3 [&_form_input]:w-full [&_form_input]:min-h-[44px] [&_form_select]:w-full [&_form_select]:min-h-[44px] [&_form_textarea]:w-full [&_form_button]:min-h-[44px] [&_form_a]:min-h-[44px]">
                            {{ $toolbar }}
                        </div>
                    </x-slot>
                </x-loan.responsive.mobile-filter-drawer>
            @endif
        </div>
    @endif

    {{ $slot }}

    @isset($footer)
        @if (! $footer->isEmpty())
            <div class="w-full min-w-0 pt-2">
                {{ $footer }}
            </div>
        @endif
    @endisset
</div>
