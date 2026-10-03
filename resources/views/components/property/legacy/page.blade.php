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

<style>
@media (max-width: 767px) {
    .property-erp-header__actions {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 0.5rem !important;
        width: 100% !important;
        overflow-x: auto !important;
    }
    .property-erp-header__actions > * {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
    }
}
</style>
<div {{ $modalShellBag->merge(['class' => $pageClasses]) }}>
    <header class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start sm:gap-x-4">
            <div class="min-w-0 sm:flex-1">
                <h1 class="text-2xl sm:text-[1.65rem] font-semibold text-slate-900 dark:text-slate-100 tracking-tight leading-tight">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed max-w-3xl">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                @if (! $actions->isEmpty())
                    <div class="property-erp-header__actions print-hide flex w-full flex-nowrap items-center gap-2 overflow-x-auto sm:ml-auto sm:w-auto sm:flex-wrap sm:justify-end">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
    </header>

    {{ $slot }}
</div>
