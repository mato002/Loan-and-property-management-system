@php
    use App\Support\Property\PropertyNavMode;

    $navMode = PropertyNavMode::current();
@endphp

@if (! PropertyNavMode::isClassic())
    @includeFirst([
        "layouts.property.sidebar.{$navMode}",
        'layouts.property.sidebar.classic',
    ])
@else
@php
    $companyLogoUrl = \App\Models\PropertyPortalSetting::getValue('company_logo_url', '');
    $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '');
    $navActive = function ($patterns): bool {
        $patterns = is_array($patterns) ? $patterns : [$patterns];
        foreach ($patterns as $p) {
            if ($p && request()->routeIs($p)) {
                return true;
            }
        }

        return false;
    };

    $itemAnyActive = null;
    $itemAnyActive = function (array $item) use (&$itemAnyActive, $navActive): bool {
        if (! empty($item['active']) && $navActive($item['active'])) {
            return true;
        }
        foreach (($item['children'] ?? []) as $child) {
            if ($itemAnyActive($child)) {
                return true;
            }
        }

        return false;
    };

    $sectionAnyActive = function (array $items) use ($itemAnyActive): bool {
        foreach ($items as $it) {
            if ($itemAnyActive($it)) {
                return true;
            }
        }

        return false;
    };

    $collectActivePatterns = null;
    $collectActivePatterns = function (array $items) use (&$collectActivePatterns): array {
        $patterns = [];
        foreach ($items as $item) {
            foreach ((array) ($item['active'] ?? []) as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $patterns[] = $p;
                }
            }
            if (! empty($item['children']) && is_array($item['children'])) {
                $patterns = array_merge($patterns, $collectActivePatterns($item['children']));
            }
        }

        return array_values(array_unique($patterns));
    };

    $sections = \App\Support\Property\PropertyClassicSidebar::sections();

    $propertyAgentIsSuperAdmin = auth()->check() && (auth()->user()->is_super_admin ?? false);
    $filterItemsByAccess = null;
    $filterItemsByAccess = static function (array $items) use (&$filterItemsByAccess, $propertyAgentIsSuperAdmin): array {
        $out = [];
        foreach ($items as $item) {
            if (! $propertyAgentIsSuperAdmin && ! empty($item['requires_superadmin'])) {
                continue;
            }
            if (! empty($item['requires_pm_permission'] ?? null)) {
                $u = auth()->user();
                if (! $u || ! $u->hasPmPermission($item['requires_pm_permission'])) {
                    continue;
                }
            }
            $children = $item['children'] ?? null;
            if (is_array($children) && $children !== []) {
                $children = $filterItemsByAccess($children);
                if ($children === []) {
                    continue;
                }
                $item['children'] = $children;
            }
            $out[] = $item;
        }

        return $out;
    };
    if (! $propertyAgentIsSuperAdmin) {
        $sections = array_values(array_map(static function (array $section): array {
            $section['items'] = array_values(array_filter(
                $section['items'],
                static function (array $item): bool {
                    if (! empty($item['requires_superadmin'])) {
                        return false;
                    }
                    if (! empty($item['requires_pm_permission'] ?? null)) {
                        $u = auth()->user();
                        if (! $u || ! $u->hasPmPermission($item['requires_pm_permission'])) {
                            return false;
                        }
                    }

                    return true;
                }
            ));

            return $section;
        }, $sections));
        $sections = array_values(array_filter($sections, static fn (array $section): bool => count($section['items']) > 0));
    }
    $sections = array_values(array_map(function (array $section) use ($filterItemsByAccess): array {
        $section['items'] = $filterItemsByAccess($section['items']);

        return $section;
    }, $sections));
    $sections = array_values(array_filter($sections, static fn (array $section): bool => count($section['items']) > 0));

    // Keep related modules adjacent for faster navigation.
    $preferredSectionOrder = [
        '',
        'Properties',
        'Listings',
        'Tenants',
        'Revenue',
        'Accounting',
        'Financials',
        'Maintenance',
        'Vendors',
        'Communications',
        'Analytics',
        'Reports',
        'AI advisor',
        'Settings',
    ];
    $sectionOrderMap = array_flip($preferredSectionOrder);
    usort($sections, static function (array $a, array $b) use ($sectionOrderMap): int {
        $aOrder = $sectionOrderMap[(string) ($a['heading'] ?? '')] ?? PHP_INT_MAX;
        $bOrder = $sectionOrderMap[(string) ($b['heading'] ?? '')] ?? PHP_INT_MAX;
        if ($aOrder === $bOrder) {
            return 0;
        }

        return $aOrder <=> $bOrder;
    });
@endphp

<style>
    @media (min-width: 1024px) {
        .property-sidebar[data-collapsed="1"] .property-collapse-text { display: none !important; }
        .property-sidebar[data-collapsed="1"] .property-collapse-center { justify-content: center !important; }
        .property-sidebar[data-collapsed="1"] [data-property-nav-section] > div { display: none !important; }
    }
</style>

<div
    x-show="sidebarOpen"
    x-transition:enter="transition-opacity ease-linear duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-[2px] lg:hidden"
    @click="sidebarOpen = false"
    x-cloak>
</div>

<aside
    class="property-sidebar fixed inset-y-0 left-0 z-50 w-[300px] sm:w-[312px] h-full bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-transform duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col min-h-0 shadow-xl shadow-black/20 lg:shadow-none overflow-hidden flex-shrink-0"
    :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none', sidebarDesktopOpen ? 'lg:w-[18rem] lg:min-w-[18rem] lg:max-w-[18rem]' : 'lg:w-[5.5rem] lg:min-w-[5.5rem] lg:max-w-[5.5rem]']"
    :style="window.matchMedia('(min-width: 1024px)').matches
        ? (sidebarDesktopOpen
            ? 'width: 18rem; min-width: 18rem; max-width: 18rem;'
            : 'width: 5.5rem; min-width: 5.5rem; max-width: 5.5rem;')
        : ''"
    :data-collapsed="sidebarDesktopOpen ? '0' : '1'"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md lg:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Menu</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="shrink-0 px-3 py-3.5 border-b border-[#264040] bg-[#243d3d]/30">
        <div class="mb-2 hidden lg:flex justify-end">
            <button
                type="button"
                @click="toggleDesktopSidebar()"
                class="inline-flex items-center justify-center rounded-lg p-2 text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors"
                :title="sidebarDesktopOpen ? 'Collapse sidebar' : 'Expand sidebar'"
                :aria-label="sidebarDesktopOpen ? 'Collapse sidebar' : 'Expand sidebar'"
            >
                <i class="fa-solid" :class="sidebarDesktopOpen ? 'fa-angles-left' : 'fa-angles-right'" aria-hidden="true"></i>
            </button>
        </div>
        <a
            href="{{ route('property.dashboard') }}"
            data-turbo-frame="property-main"
            data-property-nav="property.dashboard"
            @if ($navActive(['property.dashboard'])) aria-current="page" @endif
            class="flex items-center gap-3 min-w-0 group property-collapse-center"
            @click="if (window.innerWidth < 1024) sidebarOpen = false"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#406866]/60 ring-1 ring-[#5a8583]/50 shadow-inner">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-8 w-8 object-contain rounded-md bg-white/95 p-0.5" />
                @else
                    <i class="fa-solid fa-building text-xl text-[#c5ebe8]" aria-hidden="true"></i>
                @endif
            </span>
            <span class="property-collapse-text flex flex-col min-w-0 leading-tight text-left">
                <span class="text-base font-bold tracking-tight text-white truncate">{{ $companyName }}</span>
            </span>
        </a>
        @if (app()->environment('local'))
            <div class="property-collapse-text mt-2 rounded-lg border px-2.5 py-2 text-[11px] leading-4 {{ !empty($allowDestructiveDbCommands) ? 'border-rose-300/40 bg-rose-500/15 text-rose-100' : 'border-emerald-300/40 bg-emerald-500/15 text-emerald-100' }}">
                <span class="font-semibold uppercase tracking-wide">DB safety</span>
                <span class="ml-1">{{ !empty($allowDestructiveDbCommands) ? 'ON: destructive commands allowed' : 'ON: destructive commands blocked' }}</span>
            </div>
        @endif
    </div>

    <nav class="flex-1 min-h-0 overflow-y-auto py-2 px-2 custom-scrollbar">
        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <div class="px-2 pt-2 pb-3">
                <a
                    href="{{ route('superadmin.users.index') }}"
                    class="flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-3 py-2.5 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors property-collapse-center"
                >
                    <i class="fa-solid fa-shield-halved text-[#c5ebe8]" aria-hidden="true"></i>
                    <span class="property-collapse-text font-semibold">Super Admin</span>
                </a>
            </div>
        @endif

        @foreach ($sections as $si => $section)
            @php
                $secActive = $sectionAnyActive($section['items']);
                $itemCount = count($section['items']);
                $sectionPatterns = implode('|', $collectActivePatterns($section['items']));
            @endphp

            @if ($itemCount === 1)
                @php $item = $section['items'][0]; $active = $navActive($item['active']); @endphp
                <div class="{{ $si > 0 ? 'mt-2 pt-2 border-t border-[#406866]/40' : '' }}">
                    <a
                        href="{{ route($item['route']) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ implode('|', $item['active']) }}"
                        @if ($active) aria-current="page" @endif
                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                        class="group flex items-start gap-2.5 rounded-xl border-l-[3px] px-3 py-3 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white property-collapse-center"
                    >
                        @if (! empty($section['icon']))
                            <i class="fa-solid {{ $section['icon'] }} text-[#c5ebe8] text-base shrink-0 mt-0.5 w-6 text-center group-aria-[current=page]:text-[#c5ebe8]" aria-hidden="true"></i>
                        @endif
                        <span class="property-collapse-text flex flex-col gap-0.5 min-w-0 flex-1">
                            @if (trim((string) ($section['heading'] ?? '')) !== '')
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-hover:text-[#c5ebe8] group-aria-[current=page]:text-[#c5ebe8]">{{ $section['heading'] }}</span>
                            @endif
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                @endif
                            </span>
                        </span>
                    </a>
                </div>
            @else
                @php
                    $propertySidebarGroupKey = trim((string) ($section['heading'] ?? ''));
                    if ($propertySidebarGroupKey === '') {
                        $propertySidebarGroupKey = 'property-nav-' . $si;
                    }
                @endphp
                <div
                    class="{{ $si > 0 ? 'mt-2 pt-2 border-t border-[#406866]/40' : '' }} group"
                    data-property-nav-section
                    data-property-nav-aggregate="{{ $sectionPatterns }}"
                    @if ($secActive) data-section-active @endif
                    x-data="propertySidebarGroup(@js($propertySidebarGroupKey), false)"
                >
                    <button
                        type="button"
                        class="w-full flex items-start gap-2 rounded-xl px-2 py-2.5 text-left text-[#d4e4e3] hover:bg-[#406866]/40 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 property-collapse-center"
                        @click="toggleGroup()"
                        :aria-expanded="open"
                        aria-controls="nav-section-{{ $si }}"
                    >
                        <span class="property-collapse-text flex flex-col items-center justify-center shrink-0 pt-0.5 w-5" aria-hidden="true">
                            <i class="fa-solid fa-chevron-down text-sm text-[#8db1af] transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </span>
                        <span class="property-collapse-text flex-1 min-w-0">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-data-[section-active]:text-[#c5ebe8]">
                                @if (! empty($section['icon']))
                                    <i class="fa-solid {{ $section['icon'] }} text-base text-[#a8c9c7] not-uppercase normal-case group-data-[section-active]:text-[#c5ebe8]" aria-hidden="true"></i>
                                @endif
                                <span>{{ $section['heading'] }}</span>
                            </span>
                        </span>
                    </button>

                    <div
                        id="nav-section-{{ $si }}"
                        x-cloak
                        x-show="open"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-0.5"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="space-y-0.5 pb-1 pl-1"
                    >
                        @foreach ($section['items'] as $item)
                            @php
                                $active = ! empty($item['active']) ? $navActive($item['active']) : false;
                                $hasChildren = ! empty($item['children']) && is_array($item['children']);
                                $itemPatterns = implode('|', $collectActivePatterns([$item]));
                            @endphp
                            @if ($hasChildren)
                                @php
                                    $groupKey = 'property-nav-sub-'.$si.'-'.\Illuminate\Support\Str::slug((string) $item['label']);
                                    $childActive = $sectionAnyActive($item['children']);
                                @endphp
                                <div
                                    class="ml-6"
                                    data-property-nav-section
                                    data-property-nav-aggregate="{{ $itemPatterns }}"
                                    @if ($childActive) data-section-active @endif
                                    x-data="propertySidebarGroup(@js($groupKey), false)"
                                >
                                    <button
                                        type="button"
                                        class="w-full group flex items-center gap-2 rounded-xl border-l-[3px] px-3 py-2.5 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white"
                                        @click="toggleGroup()"
                                        :aria-expanded="open"
                                    >
                                        <span class="text-base font-medium leading-snug">{{ $item['label'] }}</span>
                                        <span class="ml-auto flex items-center gap-2">
                                            @if (! empty($item['badge']))
                                                <span class="shrink-0 mt-0.5 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                            @endif
                                            <i class="fa-solid fa-chevron-down text-xs text-[#8db1af] transition-transform duration-200" :class="{ 'rotate-180': open }" aria-hidden="true"></i>
                                        </span>
                                    </button>
                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-0.5"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-100"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                        class="space-y-0.5 pb-1 pl-1"
                                    >
                                        @foreach ($item['children'] as $child)
                                            @php $childActiveState = $navActive($child['active'] ?? []); @endphp
                                            <a
                                                href="{{ route($child['route'], $child['route_params'] ?? []) }}"
                                                data-turbo-frame="property-main"
                                                data-property-nav="{{ implode('|', (array) ($child['active'] ?? [])) }}"
                                                @if ($childActiveState) aria-current="page" @endif
                                                @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                                class="group flex items-center justify-between gap-2 rounded-xl border-l-[3px] px-3 py-2.5 ml-5 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white"
                                            >
                                                <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $child['label'] }}</span>
                                                @if (! empty($child['badge']))
                                                    <span class="shrink-0 mt-0.5 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $child['badge'] }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <a
                                    href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                    data-turbo-frame="property-main"
                                    data-property-nav="{{ implode('|', (array) ($item['active'] ?? [])) }}"
                                    @if ($active) aria-current="page" @endif
                                    @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                    class="group flex flex-col gap-0.5 rounded-xl border-l-[3px] px-3 py-2.5 ml-6 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white"
                                >
                                    <span class="flex items-start justify-between gap-2 w-full">
                                        <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $item['label'] }}</span>
                                        @if (! empty($item['badge']))
                                            <span class="shrink-0 mt-0.5 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                        @endif
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="pt-4 mt-3 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group property-collapse-center">
                    <i class="fa-solid fa-right-from-bracket w-5 shrink-0 text-center text-[#8db1af] group-hover:text-red-400 transition-colors" aria-hidden="true"></i>
                    <span class="property-collapse-text">Log out</span>
                </button>
            </form>
        </div>
    </nav>

    <div class="p-3 border-t border-[#264040] bg-[#243d3d]/40 shrink-0">
        <a
            href="{{ route('profile.edit') }}"
            data-turbo-frame="property-main"
            data-property-nav="profile.edit"
            class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-[#406866]/50 transition-colors property-collapse-center"
        >
            <div class="w-11 h-11 rounded-full bg-emerald-500/25 border border-emerald-400/35 flex items-center justify-center text-emerald-200 font-semibold text-base shrink-0">
                @if (Auth::check() && Auth::user()->name)
                    {{ mb_substr(Auth::user()->name, 0, 1) }}
                @else
                    U
                @endif
            </div>
            <div class="property-collapse-text flex flex-col overflow-hidden min-w-0">
                <span class="text-base font-medium text-white truncate">{{ Auth::user()->name ?? 'User' }}</span>
            </div>
        </a>

        <a
            href="{{ route('public.home') }}"
            target="_blank"
            rel="noopener"
            class="mt-2 flex items-center gap-3 p-2.5 rounded-xl border border-[#406866]/60 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors property-collapse-center"
        >
            <i class="fa-solid fa-globe w-5 text-center text-[#8db1af]" aria-hidden="true"></i>
            <span class="property-collapse-text text-sm font-medium">Open public website</span>
            <i class="property-collapse-text fa-solid fa-arrow-up-right-from-square ml-auto text-xs text-[#8db1af]" aria-hidden="true"></i>
        </a>
    </div>
</aside>
@endif
