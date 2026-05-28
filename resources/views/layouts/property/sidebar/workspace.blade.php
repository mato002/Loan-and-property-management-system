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
    class="property-sidebar fixed inset-y-0 left-0 z-[5600] lg:z-50 w-[min(100vw-2.5rem,300px)] sm:w-[312px] h-full bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-[transform,width] duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col min-h-0 shadow-xl shadow-black/20 lg:shadow-none overflow-x-hidden flex-shrink-0"
    :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none', sidebarDesktopOpen ? 'lg:w-[19rem] lg:min-w-[19rem] lg:max-w-[19rem]' : 'lg:w-[5.5rem] lg:min-w-[5.5rem] lg:max-w-[5.5rem]']"
    :style="window.matchMedia('(min-width: 1024px)').matches
        ? (sidebarDesktopOpen
            ? 'width: 19rem; min-width: 19rem; max-width: 19rem;'
            : 'width: 5.5rem; min-width: 5.5rem; max-width: 5.5rem;')
        : ''"
    :data-collapsed="sidebarDesktopOpen ? '0' : '1'"
    data-property-nav-mode="workspace"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md lg:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Workspaces</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="shrink-0 px-3 py-3.5 border-b border-[#264040] bg-[#243d3d]/30">
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
            :title="sidebarDesktopOpen ? '' : '{{ $companyName !== '' ? $companyName : 'Dashboard' }}'"
            @click="if (window.innerWidth < 1024) sidebarOpen = false"
        >
            <span class="property-sidebar-brand-logo flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#406866]/60 ring-1 ring-[#5a8583]/50 shadow-inner">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-8 w-8 object-contain rounded-md bg-white/95 p-0.5" />
                @else
                    <i class="fa-solid fa-building text-xl text-[#c5ebe8]" aria-hidden="true"></i>
                @endif
            </span>
            <span class="property-collapse-text flex flex-col min-w-0 leading-tight text-left">
                <span class="text-base font-bold tracking-tight text-white truncate">{{ $companyName !== '' ? $companyName : 'Property ERP' }}</span>
            </span>
        </a>
        @if (app()->environment('local'))
            <div class="property-db-safety-expanded property-collapse-text mt-2 rounded-lg border px-2.5 py-2 text-[11px] leading-4 {{ !empty($allowDestructiveDbCommands) ? 'border-rose-300/40 bg-rose-500/15 text-rose-100' : 'border-emerald-300/40 bg-emerald-500/15 text-emerald-100' }}">
                <span class="font-semibold uppercase tracking-wide">DB safety</span>
                <span class="ml-1">{{ !empty($allowDestructiveDbCommands) ? 'ON: destructive commands allowed' : 'ON: destructive commands blocked' }}</span>
            </div>
            <div
                class="property-db-safety-collapsed mt-2 items-center justify-center rounded-lg border p-2 {{ !empty($allowDestructiveDbCommands) ? 'border-rose-300/40 bg-rose-500/15 text-rose-100' : 'border-emerald-300/40 bg-emerald-500/15 text-emerald-100' }}"
                title="{{ !empty($allowDestructiveDbCommands) ? 'DB safety: destructive commands allowed' : 'DB safety: destructive commands blocked' }}"
            >
                <i class="fa-solid fa-shield-halved text-sm" aria-hidden="true"></i>
            </div>
        @endif
    </div>

    <nav class="flex-1 min-h-0 overflow-y-auto py-2 px-2 custom-scrollbar" aria-label="Property workspaces">
        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <div class="px-2 pt-2 pb-3">
                <a
                    href="{{ route('superadmin.users.index') }}"
                    class="flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-3 py-2.5 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors property-collapse-center property-collapse-compact"
                    :title="sidebarDesktopOpen ? '' : 'Super Admin'"
                >
                    <i class="fa-solid fa-shield-halved text-[#c5ebe8]" aria-hidden="true"></i>
                    <span class="property-collapse-text font-semibold">Super Admin</span>
                </a>
            </div>
        @endif

        <div class="space-y-1">
            @foreach ($workspaces as $wi => $workspace)
                @php
                    $active = $navActive($workspace['active']);
                    $navPatterns = $patternsFor($workspace);
                    $flyoutLinks = $workspace['flyout'] ?? [];
                @endphp
                <div
                    class="property-workspace-rail-item relative"
                    data-property-nav-aggregate="{{ $navPatterns }}"
                    @if ($active) data-section-active @endif
                >
                    <a
                        href="{{ PropertyNavigation::workspaceHref($workspace) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ $navPatterns }}"
                        @if ($active) aria-current="page" @endif
                        @click="if (window.innerWidth < 1024) sidebarOpen = false"
                        class="property-nav-single-link group flex items-center gap-2.5 rounded-xl border-l-[3px] px-3 py-3 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white property-collapse-center property-collapse-compact"
                        :title="sidebarDesktopOpen ? '' : '{{ $workspace['label'] }}'"
                    >
                        <span class="property-workspace-icon-wrap flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#406866]/35 ring-1 ring-[#5a8583]/40 group-aria-[current=page]:bg-[#406866]/60">
                            <i class="fa-solid {{ $workspace['icon'] }} text-base text-[#c5ebe8] group-aria-[current=page]:text-white" aria-hidden="true"></i>
                        </span>
                        <span class="property-collapse-text flex flex-col gap-0.5 min-w-0 flex-1">
                            <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $workspace['label'] }}</span>
                            @if (! empty($workspace['sublabel']))
                                <span class="text-xs text-[#8db1af] group-hover:text-[#c5ebe8] group-aria-[current=page]:text-[#d4e4e3]">{{ $workspace['sublabel'] }}</span>
                            @endif
                        </span>
                    </a>

                    @if ($flyoutLinks !== [])
                        <div class="property-workspace-flyout" role="menu" aria-label="{{ $workspace['label'] }} shortcuts">
                            <div class="px-3 py-2 border-b border-[#406866]/50">
                                <p class="text-sm font-semibold text-white">{{ $workspace['label'] }}</p>
                                @if (! empty($workspace['sublabel']))
                                    <p class="text-[11px] text-[#8db1af] mt-0.5">{{ $workspace['sublabel'] }}</p>
                                @endif
                            </div>
                            <div class="py-1">
                                @foreach ($flyoutLinks as $link)
                                    @php $linkActive = $navActive($link['active']); @endphp
                                    <a
                                        href="{{ route($link['route']) }}"
                                        data-turbo-frame="property-main"
                                        data-property-nav="{{ implode('|', $link['active']) }}"
                                        @if ($linkActive) aria-current="page" @endif
                                        class="block px-3 py-2 text-sm text-[#d4e4e3] hover:bg-[#406866]/60 hover:text-white aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white aria-[current=page]:font-semibold"
                                        role="menuitem"
                                    >
                                        {{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="pt-4 mt-3 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group property-collapse-center property-collapse-compact" :title="sidebarDesktopOpen ? '' : 'Log out'">
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
            class="property-sidebar-footer-link flex items-center gap-3 p-2.5 rounded-xl hover:bg-[#406866]/50 transition-colors property-collapse-center property-collapse-compact"
            :title="sidebarDesktopOpen ? '' : '{{ Auth::user()->name ?? 'Profile' }}'"
        >
            <div class="property-sidebar-avatar w-11 h-11 rounded-full bg-emerald-500/25 border border-emerald-400/35 flex items-center justify-center text-emerald-200 font-semibold text-base shrink-0">
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
            class="property-sidebar-footer-link mt-2 flex items-center gap-3 p-2.5 rounded-xl border border-[#406866]/60 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors property-collapse-center property-collapse-compact"
            :title="sidebarDesktopOpen ? '' : 'Open public website'"
        >
            <i class="property-collapse-icon-only fa-solid fa-globe w-5 text-center text-[#8db1af]" aria-hidden="true"></i>
            <span class="property-collapse-text text-sm font-medium">Open public website</span>
            <i class="property-collapse-text fa-solid fa-arrow-up-right-from-square ml-auto text-xs text-[#8db1af]" aria-hidden="true"></i>
        </a>
    </div>
</aside>
