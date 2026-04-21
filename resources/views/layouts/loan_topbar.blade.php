@php
    use App\Support\LoanNavigation;

    $hour = (int) now()->format('H');
    $greeting = 'morning';
    if ($hour >= 12 && $hour < 17) {
        $greeting = 'afternoon';
    } elseif ($hour >= 17) {
        $greeting = 'evening';
    }
    $firstName = Auth::check() ? explode(' ', Auth::user()->name ?? 'User')[0] : 'User';
    $quickLinks = LoanNavigation::quickLinksForUser(Auth::user());
    $loanNotificationItems = collect();
    $loanNotificationUnread = 0;
    if (Auth::check() && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
        $loanNotificationItems = Auth::user()->notifications()->latest()->limit(8)->get();
        $loanNotificationUnread = (int) Auth::user()->unreadNotifications()->count();
    }
@endphp

<header class="relative md:sticky md:top-0 z-30 md:z-50 flex-shrink-0 border-b border-slate-200/90 bg-gradient-to-b from-white via-slate-50 to-white shadow-sm backdrop-blur-md supports-[backdrop-filter]:bg-white/90">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Primary row --}}
        <div class="flex items-center justify-between gap-3 py-3 sm:py-3.5 min-h-[4.25rem]">
            <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
                <button type="button" @click="sidebarOpen = true" class="md:hidden shrink-0 p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-[#2f4f4f]/30" aria-label="Open menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="toggleDesktopSidebar()"
                    x-show="!sidebarDesktopOpen"
                    x-cloak
                    class="hidden md:inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                    aria-label="Show sidebar"
                    title="Show sidebar"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    Menu
                </button>
                <div class="min-w-0 hidden sm:block">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <h1 class="text-lg sm:text-xl font-semibold text-slate-900 tracking-tight truncate">
                            Good {{ $greeting }}, <span class="text-[#2f4f4f]">{{ $firstName }}</span>
                        </h1>
                        <span class="inline-flex items-center rounded-md bg-[#2f4f4f]/10 text-[#264040] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider shrink-0">Loan</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <span class="tabular-nums font-medium text-slate-600">{{ now()->format('l, F j, Y') }}</span>
                        <span class="text-slate-300 hidden sm:inline" aria-hidden="true">·</span>
                        <span class="hidden sm:inline">Portfolio, collections &amp; books in one place</span>
                    </p>
                </div>

                {{-- Compact mobile title --}}
                <div class="sm:hidden min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900 truncate">Loan workspace</p>
                    <p class="text-[11px] text-slate-500 tabular-nums">{{ now()->format('M j, Y') }}</p>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-2 sm:gap-3 shrink-0 justify-end">
                {{-- Quick nav (tablet+) --}}
                <nav class="hidden lg:flex items-center gap-0.5 rounded-xl bg-white/80 border border-slate-200/80 p-1 shadow-sm" aria-label="Quick navigation">
                    @foreach ($quickLinks as $link)
                        @if (Route::has($link['route']))
                            <a
                                href="{{ route($link['route']) }}"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-colors whitespace-nowrap {{ $link['active'] ? 'bg-[#2f4f4f] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                            >
                                {{ $link['label'] }}
                            </a>
                        @endif
                    @endforeach
                </nav>

                <div class="flex items-center gap-2 sm:gap-2">
                    @if (LoanNavigation::canOpenLoanSystemSetup(Auth::user()))
                        <a href="{{ route('loan.system.setup') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-800 hover:bg-violet-100 transition-colors" title="System setup">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Setup
                        </a>
                    @endif

                    <a href="{{ route('loan.system.tickets.create') }}" class="hidden md:inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors" title="Support">
                        <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        Help
                    </a>

                    <div class="hidden sm:block w-px h-8 bg-slate-200" aria-hidden="true"></div>

                    <div class="relative z-[60]" x-data="{ bellOpen: false }" @click.outside="bellOpen = false">
                        <button
                            type="button"
                            @click="bellOpen = !bellOpen"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors"
                            title="Notifications"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if ($loanNotificationUnread > 0)
                                <span class="absolute -top-0.5 -right-0.5 min-w-[1.05rem] h-[1.05rem] px-1 rounded-full bg-rose-500 text-white text-[10px] leading-[1.05rem] text-center font-bold">{{ $loanNotificationUnread > 99 ? '99+' : $loanNotificationUnread }}</span>
                            @endif
                        </button>

                        <div
                            x-show="bellOpen"
                            x-transition.opacity
                            class="absolute right-0 mt-2 w-96 max-w-[92vw] rounded-xl bg-white shadow-xl border border-slate-200 py-1.5 z-[100] overflow-hidden"
                            x-cloak
                        >
                            <div class="px-4 py-2 border-b border-slate-100 flex items-center justify-between">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Previous Notifications</p>
                                <a href="{{ route('loan.notifications.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">View all</a>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                @forelse ($loanNotificationItems as $item)
                                    @php
                                        $data = is_array($item->data ?? null) ? $item->data : [];
                                        $message = trim((string) ($data['message'] ?? $data['text'] ?? $item->type));
                                        $url = trim((string) ($data['url'] ?? route('loan.notifications.index')));
                                    @endphp
                                    <a href="{{ $url }}" class="block px-4 py-3 border-b border-slate-100 last:border-b-0 {{ $item->read_at ? 'bg-white' : 'bg-indigo-50/40' }}">
                                        <p class="text-[11px] font-semibold text-slate-500">{{ optional($item->created_at)->format('M j, g:i a') }}</p>
                                        <p class="mt-1 text-sm text-slate-700 line-clamp-2">{{ $message }}</p>
                                    </a>
                                @empty
                                    <p class="px-4 py-6 text-sm text-slate-500 text-center">No notifications yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="relative z-[60]" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                        <button type="button" @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2 p-1 pr-2 sm:pr-3 rounded-full bg-white border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-bold text-sm shadow-inner border border-white/20 overflow-hidden">
                                @if (Auth::check() && filled(Auth::user()->profile_photo_url))
                                    <img src="{{ Auth::user()->profile_photo_url }}" alt="Profile image" class="h-full w-full object-cover">
                                @elseif (Auth::check() && Auth::user()->name)
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                @else
                                    U
                                @endif
                            </div>
                            <span class="hidden sm:block text-sm font-semibold text-slate-800 max-w-[9rem] truncate text-left leading-tight">
                                {{ Auth::user()->name ?? 'User' }}
                            </span>
                            <svg class="w-4 h-4 text-slate-500 hidden sm:block shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            x-show="userMenuOpen"
                            x-transition.opacity
                            class="absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-xl border border-slate-200 py-1.5 z-[100] overflow-hidden"
                            x-cloak
                        >
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Signed in</p>
                                <p class="text-sm font-medium text-slate-900 truncate">{{ Auth::user()->email ?? '' }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 hover:text-indigo-600 transition-colors">Profile settings</a>
                            <a href="{{ route('loan.dashboard') }}" class="lg:hidden block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Dashboard</a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors font-medium">
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Secondary strip: mobile quick links + hint --}}
        <div class="lg:hidden flex flex-wrap items-center gap-2 pb-3 -mt-1 border-t border-slate-100/80 pt-2.5">
            @foreach (array_slice($quickLinks, 0, 4) as $link)
                @if (Route::has($link['route']))
                    <a
                        href="{{ route($link['route']) }}"
                        class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide border transition-colors {{ $link['active'] ? 'bg-[#2f4f4f] text-white border-[#2f4f4f]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}"
                    >
                        {{ $link['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</header>
