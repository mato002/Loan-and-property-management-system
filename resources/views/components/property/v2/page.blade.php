@props([
    'title',
    'subtitle' => null,
    'workspace' => null,
    'showWorkspaceTabs' => true,
    'compactList' => true,
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
@endphp

<div {{ $attributes->merge(['class' => ($currentPortalRole === 'tenant'
        ? 'property-erp-page w-full space-y-3 md:space-y-5'
        : ($compactList
            ? 'property-erp-page property-erp-page--compact max-w-[1600px] mx-auto w-full space-y-1.5 md:space-y-2'
            : 'property-erp-page max-w-[1600px] mx-auto w-full space-y-2 md:space-y-3'))]) }}>
    @if ($renderWorkspaceTabs)
        <x-property.workspace-tabs :workspace="$resolvedWorkspaceKey" />
    @endif

    <header @class(['property-erp-header', $compactList ? 'space-y-1' : 'space-y-2 sm:space-y-3'])>
        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between lg:gap-3">
            <div class="min-w-0 flex-1">
                <h1 @class([
                    'font-semibold text-slate-900 dark:text-slate-100 tracking-tight leading-tight',
                    $compactList ? 'text-base sm:text-lg' : 'text-lg sm:text-xl',
                ])>{{ $title }}</h1>
                @if ($subtitle && ! $compactList)
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1 leading-snug max-w-3xl">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                @if (! $actions->isEmpty())
                    <div class="print-hide flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full lg:w-auto min-w-0 shrink-0 lg:pt-1 [&>button]:w-full [&>button]:min-h-[44px] [&>button]:sm:w-auto [&>button]:sm:min-h-0 [&>a]:w-full [&>a]:min-h-[44px] [&>a]:sm:w-auto [&>a]:sm:min-h-0">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
    </header>

    {{ $slot }}
</div>
