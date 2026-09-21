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

<div {{ $modalShellBag->merge(['class' => $pageClasses]) }}>
    @if ($renderWorkspaceTabs)
        <x-property.workspace-tabs :workspace="$resolvedWorkspaceKey" />
    @endif

    <header @class(['property-erp-header property-print-hide print-hide', $compactList ? 'space-y-1' : 'space-y-2 sm:space-y-3'])>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <div class="min-w-0 flex-1">
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
                    <div class="print-hide flex flex-wrap items-center justify-end gap-1.5 w-full sm:w-auto sm:ml-auto sm:max-w-[58%] [&>button]:min-h-0 [&>a]:min-h-0">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
    </header>

    {{ $slot }}
</div>
