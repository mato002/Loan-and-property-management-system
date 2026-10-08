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
    use App\Support\Property\PropertyWorkspaceTabs;

    $currentPortalRole = auth()->user()?->property_portal_role ?? 'agent';
    $routeName = request()->route()?->getName();
    $resolvedWorkspaceKey = $workspace ?? PropertyWorkspaceTabs::resolveWorkspaceKey($routeName);
    $renderWorkspaceTabs = ($currentPortalRole === 'agent')
        && ($showWorkspaceTabs ?? true)
        && $resolvedWorkspaceKey
        && PropertyWorkspaceTabs::shouldShow($routeName);

    $pageClasses = $currentPortalRole === 'tenant'
        ? 'property-erp-page w-full space-y-3 md:space-y-5'
        : ($compactList
            ? 'property-erp-page property-erp-page--compact max-w-[1600px] mx-auto w-full space-y-1.5 md:space-y-2'
            : 'property-erp-page max-w-[1600px] mx-auto w-full space-y-2 md:space-y-3');

    $modalShellBag = $modalShellBag instanceof \Illuminate\View\ComponentAttributeBag
        ? $modalShellBag
        : new \Illuminate\View\ComponentAttributeBag();
@endphp

<style>
@media (max-width: 767px) {
    .property-inline-actions {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 0.5rem !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    .property-inline-actions > * {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
    }
}
</style>
<div {{ $modalShellBag->merge(['class' => $pageClasses]) }}>
    @if ($renderWorkspaceTabs)
        <x-property.workspace-tabs :workspace="$resolvedWorkspaceKey" />
    @endif

    <header @class(['property-erp-header property-print-hide print-hide', $compactList ? '' : 'space-y-2 sm:space-y-3'])>
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0 md:flex-1">
                <h1 @class([
                    'font-semibold text-slate-900 dark:text-slate-100 tracking-tight leading-tight',
                    $compactList ? 'text-base sm:text-lg' : 'text-lg sm:text-xl',
                ])>{{ $title }}</h1>
                @if ($subtitle)
                    <p @class([
                        'text-slate-600 dark:text-slate-400 leading-snug max-w-3xl',
                        $compactList ? 'text-xs mt-0.5' : 'text-sm mt-1',
                    ])>{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                @if (! $actions->isEmpty())
                    <div class="property-erp-header__actions print-hide flex w-full min-w-0 flex-nowrap items-center gap-2 overflow-x-auto md:ml-auto md:w-auto md:max-w-[70%] md:flex-wrap md:justify-end">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
    </header>

    {{ $slot }}
</div>
