@props([
    /** @var array<string, array{title: string, description?: string|null}> $panels */
    'panels' => [],
    /** @var array<string, array{label: string, default: string, panels: list<string>}> $panelGroups */
    'panelGroups' => [],
    'active' => null,
    'activeGroup' => null,
])

@php
    $active = \App\Services\Property\LandlordReportsHubService::resolvePanel($active);
    $panelGroups = $panelGroups !== [] ? $panelGroups : \App\Services\Property\LandlordReportsHubService::panelGroups();
    $activeGroup = $activeGroup ?: \App\Services\Property\LandlordReportsHubService::groupForPanel($active);
    $subPanels = $panelGroups[$activeGroup]['panels'] ?? [];
    $showSubRow = count($subPanels) > 1;
@endphp

<div {{ $attributes->merge(['class' => 'property-workspace-tabs print-hide w-full min-w-0']) }} data-property-workspace="landlord-reports">
    <div class="property-workspace-tabs-shell rounded-lg border border-slate-200/90 dark:border-slate-700 bg-white/95 dark:bg-slate-900/60 shadow-sm">
        <nav
            class="property-workspace-tabs-primary flex flex-nowrap gap-0.5 overflow-x-auto custom-scrollbar px-1.5 py-1"
            aria-label="Report categories"
            data-property-workspace-tabs-nav="primary"
        >
            @foreach ($panelGroups as $groupKey => $group)
                @php $groupActive = $groupKey === $activeGroup; @endphp
                <a
                    href="{{ route('property.landlord.reports.index', ['panel' => $group['default']]) }}"
                    data-turbo-frame="property-main"
                    data-property-nav="property.landlord.reports.index"
                    data-property-workspace-tab="{{ $groupKey }}"
                    @if ($groupActive) aria-current="page" @endif
                    class="property-workspace-tab shrink-0 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold transition-colors min-h-[32px] border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 whitespace-nowrap aria-[current=page]:bg-emerald-600 aria-[current=page]:border-emerald-600 aria-[current=page]:text-white"
                >
                    {{ $group['label'] }}
                </a>
            @endforeach
        </nav>

        @if ($showSubRow)
            <nav
                class="property-workspace-tabs-sub flex flex-nowrap items-center gap-0.5 overflow-x-auto custom-scrollbar border-t border-slate-100 dark:border-slate-800 px-1.5 py-1"
                aria-label="{{ $panelGroups[$activeGroup]['label'] ?? 'Reports' }} sections"
                data-property-workspace-tabs-nav="sub"
            >
                <span class="property-workspace-subnav-label shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 bg-slate-100/90 dark:bg-slate-800/80">
                    {{ $panelGroups[$activeGroup]['label'] ?? '' }}
                </span>
                @foreach ($subPanels as $panelKey)
                    @php
                        $panel = $panels[$panelKey] ?? ['title' => ucfirst(str_replace('_', ' ', $panelKey))];
                        $isActive = $panelKey === $active;
                    @endphp
                    <a
                        href="{{ route('property.landlord.reports.index', ['panel' => $panelKey]) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="property.landlord.reports.index"
                        data-property-workspace-subtab="{{ $panelKey }}"
                        @if ($isActive) aria-current="page" @endif
                        class="property-workspace-subtab shrink-0 inline-flex items-center rounded-md px-2 py-1 text-[11px] font-semibold transition-colors min-h-[28px] border border-slate-200/90 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 aria-[current=page]:bg-emerald-50 aria-[current=page]:border-emerald-300 aria-[current=page]:text-emerald-800 dark:aria-[current=page]:bg-emerald-950/40 dark:aria-[current=page]:text-emerald-200"
                    >
                        {{ $panel['title'] }}
                    </a>
                @endforeach
            </nav>
        @endif
    </div>
</div>
