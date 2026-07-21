@php
    use App\Support\LoanNavigation;

    $loanLogoRaw = trim((string) \App\Models\LoanSystemSetting::getValue('logo_url', ''));
    $loanLogoUrl = match (true) {
        $loanLogoRaw === '' => '',
        \Illuminate\Support\Str::startsWith($loanLogoRaw, ['http://', 'https://', '//']) => $loanLogoRaw,
        default => asset(ltrim($loanLogoRaw, '/')),
    };
    $loanAppName = \App\Models\LoanSystemSetting::getValue('app_display_name', 'Loan Manager');
    $workspaces = LoanNavigation::agentWorkspaces();
    $navActive = fn (array $patterns): bool => LoanNavigation::navActive($patterns);
    $patternsFor = static function (array $workspace): string {
        return implode('|', $workspace['active'] ?? []);
    };
    $sidebarUnreadNotifications = 0;
    if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
        $sidebarUnreadNotifications = (int) auth()->user()->unreadNotifications()->count();
    }
@endphp

@include('layouts.partials.loan_sidebar_collapsed_styles')

<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/80 backdrop-blur-sm md:hidden" @click="sidebarOpen = false" x-cloak></div>

<aside
    class="loan-sidebar fixed inset-y-0 left-0 z-50 w-[280px] sm:w-[300px] h-full max-h-screen bg-[#17363a] border-r border-[#1f4a4d] flex flex-col min-h-0 transition-all duration-300 md:relative md:translate-x-0 md:h-full md:max-h-screen overflow-x-hidden flex-shrink-0 text-[#d4e4e3] shadow-2xl md:shadow-none"
    :class="[
        sidebarOpen ? 'translate-x-0' : '-translate-x-full max-md:pointer-events-none',
        sidebarDesktopOpen
            ? 'md:w-[18rem] md:max-w-[18rem] md:min-w-[18rem]'
            : 'md:w-[5.5rem] md:max-w-[5.5rem] md:min-w-[5.5rem]'
    ]"
    :style="window.matchMedia('(min-width: 768px)').matches
        ? (sidebarDesktopOpen
            ? 'width: 18rem; min-width: 18rem; max-width: 18rem;'
            : 'width: 5.5rem; min-width: 5.5rem; max-width: 5.5rem;')
        : ''"
    :data-collapsed="sidebarDesktopOpen ? '0' : '1'"
    data-loan-nav-mode="workspace"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#1f4a4d] bg-[#102b2f]/70 backdrop-blur-md md:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Workspaces</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-md text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="shrink-0 px-3 py-3.5 border-b border-[#1f4a4d] bg-[#102b2f]/40">
        <div class="mb-2 hidden md:flex justify-end">
            <button
                type="button"
                @click="toggleDesktopSidebar()"
                class="inline-flex items-center justify-center rounded-lg p-2 text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors"
                :title="sidebarDesktopOpen ? 'Collapse sidebar' : 'Expand sidebar'"
                :aria-label="sidebarDesktopOpen ? 'Collapse sidebar' : 'Expand sidebar'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path x-show="sidebarDesktopOpen" stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    <path x-show="!sidebarDesktopOpen" stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
        <a
            href="{{ route('loan.dashboard') }}"
            data-turbo-frame="loan-main"
            data-loan-nav="loan.dashboard"
            @if ($navActive(['loan.dashboard', 'loan.dashboard.*'])) aria-current="page" @endif
            class="loan-sidebar-brand flex items-center gap-3 min-w-0 group loan-collapse-center"
            :title="sidebarDesktopOpen ? '' : '{{ $loanAppName }}'"
            @click="if (window.innerWidth < 768) sidebarOpen = false"
        >
            @if (trim((string) $loanLogoUrl) !== '')
                <img src="{{ $loanLogoUrl }}" alt="Logo" class="h-9 w-9 shrink-0 rounded-lg bg-white/90 p-1 shadow-lg object-contain">
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500 shadow-lg shadow-indigo-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            @endif
            <span class="loan-collapse-text flex flex-col min-w-0 leading-tight text-left">
                <span class="text-base font-bold tracking-tight text-white truncate">{{ $loanAppName }}</span>
                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#8db1af]">Loan MFI</span>
            </span>
        </a>
    </div>

    <nav id="loan-sidebar-nav" class="flex-1 min-h-0 overflow-y-auto overscroll-contain py-2 px-2 custom-scrollbar" aria-label="Loan workspaces" @click="if (window.innerWidth < 768 && $event.target.closest('a')) sidebarOpen = false">

        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <div class="px-2 pt-1 pb-3">
                <a
                    href="{{ route('superadmin.users.index') }}"
                    class="flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#102b2f]/35 px-3 py-2.5 text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white transition-colors loan-collapse-center loan-collapse-compact"
                    :title="sidebarDesktopOpen ? '' : 'Super Admin'"
                >
                    <svg class="w-5 h-5 shrink-0 text-[#8db1af]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 10H7a2 2 0 01-2-2V6a2 2 0 012-2h6l4 4v12a2 2 0 01-2 2z" />
                    </svg>
                    <span class="loan-collapse-text font-semibold">Super Admin</span>
                </a>
            </div>
        @endif

        <div class="space-y-1">
            @foreach ($workspaces as $workspace)
                @php
                    $active = $navActive($workspace['active']);
                    $navPatterns = $patternsFor($workspace);
                    $flyoutLinks = LoanNavigation::flyoutLinksForUser($workspace, auth()->user());
                    $showSettingsBadge = ($workspace['key'] ?? '') === \App\Support\LoanWorkspaces::SETTINGS && $sidebarUnreadNotifications > 0;
                @endphp
                <div
                    class="loan-workspace-rail-item relative"
                    data-loan-nav-aggregate="{{ $navPatterns }}"
                    @if ($active) data-section-active @endif
                >
                    <a
                        href="{{ LoanNavigation::workspaceHref($workspace) }}"
                        data-turbo-frame="loan-main"
                        data-loan-nav="{{ $navPatterns }}"
                        @if ($active) aria-current="page" @endif
                        class="group flex items-center gap-2.5 rounded-xl border-l-[3px] px-3 py-3 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white loan-collapse-center loan-collapse-compact"
                        :title="sidebarDesktopOpen ? '' : '{{ $workspace['label'] }}'"
                    >
                        <span class="loan-workspace-icon-wrap flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#406866]/35 ring-1 ring-[#5a8583]/40 group-aria-[current=page]:bg-[#406866]/60">
                            <svg class="w-5 h-5 text-[#c5ebe8] group-aria-[current=page]:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $workspace['icon'] }}" />
                            </svg>
                        </span>
                        <span class="loan-collapse-text flex flex-col gap-0.5 min-w-0 flex-1">
                            <span class="text-sm font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold flex items-center gap-1.5">
                                {{ $workspace['label'] }}
                                @if ($showSettingsBadge)
                                    <span class="inline-flex min-w-[1.1rem] h-[1.1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white">
                                        {{ $sidebarUnreadNotifications > 99 ? '99+' : $sidebarUnreadNotifications }}
                                    </span>
                                @endif
                            </span>
                            @if (! empty($workspace['sublabel']))
                                <span class="text-xs text-[#8db1af] group-hover:text-[#c5ebe8] group-aria-[current=page]:text-[#d4e4e3]">{{ $workspace['sublabel'] }}</span>
                            @endif
                        </span>
                    </a>

                    @if ($flyoutLinks !== [])
                        <div class="loan-workspace-flyout" role="menu" aria-label="{{ $workspace['label'] }} shortcuts">
                            <div class="px-3 py-2 border-b border-[#406866]/50">
                                <p class="text-sm font-semibold text-white">{{ $workspace['label'] }}</p>
                                @if (! empty($workspace['sublabel']))
                                    <p class="text-[11px] text-[#8db1af] mt-0.5">{{ $workspace['sublabel'] }}</p>
                                @endif
                            </div>
                            <div class="py-1">
                                @foreach ($flyoutLinks as $link)
                                    @php $linkActive = $navActive($link['active'] ?? []); @endphp
                                    @if (Route::has($link['route']))
                                        <a
                                            href="{{ LoanNavigation::flyoutHref($link) }}"
                                            data-turbo-frame="loan-main"
                                            data-loan-nav="{{ implode('|', $link['active'] ?? []) }}"
                                            @if ($linkActive) aria-current="page" @endif
                                            class="block px-3 py-2 text-sm text-[#d4e4e3] hover:bg-[#406866]/60 hover:text-white aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white aria-[current=page]:font-semibold"
                                            role="menuitem"
                                        >
                                            {{ $link['label'] }}
                                        </a>
                                    @endif
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
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group loan-collapse-center loan-collapse-compact" :title="sidebarDesktopOpen ? '' : 'Log out'">
                    <svg class="w-5 h-5 shrink-0 text-[#8db1af] group-hover:text-red-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="loan-collapse-text">Log out</span>
                </button>
            </form>
        </div>
    </nav>

    <div class="p-3 border-t border-[#1f4a4d] bg-[#102b2f]/40 shrink-0">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-[#406866]/50 transition-colors loan-collapse-center loan-collapse-compact" :title="sidebarDesktopOpen ? '' : '{{ Auth::user()->name ?? 'Profile' }}'">
            <div class="w-10 h-10 rounded-full bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-bold overflow-hidden flex-shrink-0">
                @if (Auth::check() && filled(Auth::user()->profile_photo_url))
                    <img src="{{ Auth::user()->profile_photo_url }}" alt="Profile image" class="h-full w-full object-cover">
                @elseif (Auth::check() && Auth::user()->name)
                    {{ substr(Auth::user()->name, 0, 1) }}
                @else
                    U
                @endif
            </div>
            <div class="loan-collapse-text flex flex-col overflow-hidden min-w-0">
                <span class="text-sm font-medium text-white truncate">{{ Auth::user()->name ?? 'Administrator' }}</span>
            </div>
        </a>
    </div>
</aside>
