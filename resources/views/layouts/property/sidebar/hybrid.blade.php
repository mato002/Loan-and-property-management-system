@php
    use App\Support\Property\PropertyNavigation;

    $companyLogoUrl = \App\Models\PropertyPortalSetting::getValue('company_logo_url', '');
    $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '');
    $workspaces = PropertyNavigation::agentWorkspaces();
    $navActive = fn (array $patterns): bool => PropertyNavigation::navActive($patterns);
    $patternsFor = static function (array $workspace): string {
        return implode('|', $workspace['active'] ?? []);
    };
@endphp

@include('layouts.partials.property_sidebar_collapsed_styles_v2')

<div
    x-show="sidebarOpen"
    x-transition:enter="transition-opacity ease-linear duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[5500] bg-slate-950/70 backdrop-blur-[2px] lg:hidden"
    @click="sidebarOpen = false"
    x-cloak>
</div>

<aside
    class="property-sidebar property-sidebar-hybrid fixed inset-y-0 left-0 z-[5600] lg:z-50 w-[min(100vw-2.5rem,280px)] sm:w-[288px] h-full bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-[transform,width] duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col min-h-0 shadow-xl shadow-black/20 lg:shadow-none overflow-x-hidden flex-shrink-0"
    :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none', sidebarDesktopOpen ? 'lg:w-[16rem] lg:min-w-[16rem] lg:max-w-[16rem]' : 'lg:w-[4.75rem] lg:min-w-[4.75rem] lg:max-w-[4.75rem]']"
    :style="window.matchMedia('(min-width: 1024px)').matches
        ? (sidebarDesktopOpen
            ? 'width: 16rem; min-width: 16rem; max-width: 16rem;'
            : 'width: 4.75rem; min-width: 4.75rem; max-width: 4.75rem;')
        : ''"
    :data-collapsed="sidebarDesktopOpen ? '0' : '1'"
    data-property-nav-mode="hybrid"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md lg:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Navigation</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="shrink-0 px-3 py-3 border-b border-[#264040] bg-[#243d3d]/30">
        <div class="property-sidebar-collapse-toggle-wrap mb-2 hidden lg:flex justify-end">
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
            class="property-sidebar-brand flex items-center gap-3 min-w-0 group property-collapse-center"
            :title="sidebarDesktopOpen ? '' : '{{ $companyName !== '' ? $companyName : 'Home' }}'"
            @click="if (window.innerWidth < 1024) sidebarOpen = false"
        >
            <span class="property-sidebar-brand-logo flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#406866]/60 ring-1 ring-[#5a8583]/50 shadow-inner">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-7 w-7 object-contain rounded-md bg-white/95 p-0.5" />
                @else
                    <i class="fa-solid fa-building text-lg text-[#c5ebe8]" aria-hidden="true"></i>
                @endif
            </span>
            <span class="property-collapse-text flex flex-col min-w-0 leading-tight text-left">
                <span class="text-sm font-bold tracking-tight text-white truncate">{{ $companyName !== '' ? $companyName : 'Property' }}</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 min-h-0 overflow-y-auto py-2 px-2 custom-scrollbar" aria-label="Property navigation groups">
        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <div class="px-2 pt-2 pb-2">
                <a
                    href="{{ route('superadmin.users.index') }}"
                    class="flex items-center gap-2.5 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-2.5 py-2 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors property-collapse-center property-collapse-compact"
                    :title="sidebarDesktopOpen ? '' : 'Super Admin'"
                >
                    <i class="fa-solid fa-shield-halved text-[#c5ebe8]" aria-hidden="true"></i>
                    <span class="property-collapse-text text-sm font-semibold">Super Admin</span>
                </a>
            </div>
        @endif

        @foreach ($workspaces as $wi => $workspace)
            @php
                $active = $navActive($workspace['active']);
                $navPatterns = $patternsFor($workspace);
                $flyoutLinks = $workspace['flyout'] ?? [];
                $groupKey = 'property-hybrid-' . ($workspace['key'] ?? $wi);
            @endphp
            <div
                class="{{ $wi > 0 ? 'mt-1 pt-1 border-t border-[#406866]/30' : '' }}"
                data-property-nav-section
                data-property-nav-aggregate="{{ $navPatterns }}"
                @if ($active) data-section-active @endif
                x-data="propertySidebarGroup(@js($groupKey), @js($active))"
            >
                <div class="flex items-stretch gap-0.5">
                    <a
                        href="{{ PropertyNavigation::workspaceHref($workspace) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ $navPatterns }}"
                        @if ($active) aria-current="page" @endif
                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                        class="property-nav-single-link group flex flex-1 items-center gap-2 rounded-lg border-l-[3px] px-2 py-2 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white property-collapse-center property-collapse-compact min-w-0"
                        :title="sidebarDesktopOpen ? '' : '{{ $workspace['label'] }}'"
                    >
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#406866]/35 ring-1 ring-[#5a8583]/40 group-aria-[current=page]:bg-[#406866]/60">
                            <i class="fa-solid {{ $workspace['icon'] }} text-sm text-[#c5ebe8] group-aria-[current=page]:text-white" aria-hidden="true"></i>
                        </span>
                        <span class="property-collapse-text flex flex-col min-w-0 flex-1">
                            <span class="text-sm font-medium leading-snug truncate group-aria-[current=page]:font-semibold">{{ $workspace['label'] }}</span>
                        </span>
                    </a>
                    @if ($flyoutLinks !== [])
                        <button
                            type="button"
                            class="property-collapse-text property-section-expanded-only shrink-0 inline-flex items-center justify-center rounded-lg px-1.5 text-[#8db1af] hover:bg-[#406866]/40 hover:text-white transition-colors"
                            @click="if ($el.closest('aside')?.dataset?.collapsed === '1' && window.matchMedia('(min-width: 1024px)').matches) { window.dispatchEvent(new CustomEvent('property-sidebar-expand')); return; } toggleGroup()"
                            :aria-expanded="open"
                            :title="'Show {{ $workspace['label'] }} shortcuts'"
                        >
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': open }" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>

                @if ($flyoutLinks !== [])
                    <div
                        x-cloak
                        x-show="open"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-0.5"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="property-collapse-text ml-10 mr-1 space-y-0.5 pb-1"
                    >
                        @foreach ($flyoutLinks as $link)
                            @php $linkActive = $navActive($link['active']); @endphp
                            <a
                                href="{{ route($link['route']) }}"
                                data-turbo-frame="property-main"
                                data-property-nav="{{ implode('|', $link['active']) }}"
                                @if ($linkActive) aria-current="page" @endif
                                @click="if (window.innerWidth < 1024) sidebarOpen = false"
                                class="block rounded-md px-2 py-1.5 text-xs font-medium text-[#b8d4d2] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:bg-[#406866]/70 aria-[current=page]:text-white aria-[current=page]:font-semibold"
                            >
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        <div class="pt-3 mt-2 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2.5 px-2 py-2 rounded-lg text-sm font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-all text-left group property-collapse-center property-collapse-compact" :title="sidebarDesktopOpen ? '' : 'Log out'">
                    <i class="fa-solid fa-right-from-bracket w-5 shrink-0 text-center text-[#8db1af] group-hover:text-red-400 transition-colors" aria-hidden="true"></i>
                    <span class="property-collapse-text">Log out</span>
                </button>
            </form>
        </div>
    </nav>

    <div class="p-2.5 border-t border-[#264040] bg-[#243d3d]/40 shrink-0">
        <a
            href="{{ route('profile.edit') }}"
            data-turbo-frame="property-main"
            data-property-nav="profile.edit"
            class="property-sidebar-footer-link flex items-center gap-2.5 p-2 rounded-xl hover:bg-[#406866]/50 transition-colors property-collapse-center property-collapse-compact"
            :title="sidebarDesktopOpen ? '' : '{{ Auth::user()->name ?? 'Profile' }}'"
        >
            <div class="property-sidebar-avatar w-9 h-9 rounded-full bg-emerald-500/25 border border-emerald-400/35 flex items-center justify-center text-emerald-200 font-semibold text-sm shrink-0">
                @if (Auth::check() && Auth::user()->name)
                    {{ mb_substr(Auth::user()->name, 0, 1) }}
                @else
                    U
                @endif
            </div>
            <div class="property-collapse-text flex flex-col overflow-hidden min-w-0">
                <span class="text-sm font-medium text-white truncate">{{ Auth::user()->name ?? 'User' }}</span>
            </div>
        </a>
    </div>
</aside>
