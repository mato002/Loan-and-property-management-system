@props([
    'title',
    'subtitle' => null,
    'workspace' => null,
    'showWorkspaceTabs' => true,
    'compactList' => true,
    /** @var \Illuminate\View\ComponentAttributeBag|null $modalShellBag */
    'modalShellBag' => null,
])

@php
    $currentPortalRole = auth()->user()?->property_portal_role ?? 'agent';

    $pageClasses = $currentPortalRole === 'tenant'
        ? 'w-full space-y-6'
        : 'max-w-[1600px] mx-auto w-full space-y-6';

    $modalShellBag = $modalShellBag instanceof \Illuminate\View\ComponentAttributeBag
        ? $modalShellBag
        : new \Illuminate\View\ComponentAttributeBag();
@endphp

<div {{ $modalShellBag->merge(['class' => $pageClasses]) }}>
    <header class="space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl sm:text-[1.65rem] font-semibold text-slate-900 dark:text-slate-100 tracking-tight leading-tight">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed max-w-3xl">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                @if (! $actions->isEmpty())
                    <div class="print-hide flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full lg:w-auto min-w-0 shrink-0 lg:pt-1 [&>button]:w-full [&>button]:sm:w-auto">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
    </header>

    {{ $slot }}
</div>
