@php
    use App\Support\Property\PropertyNavMode;
    use App\Support\Property\PropertyNavigation;
    use App\Support\Property\LandlordPortalNavigation;

    $companyLogoUrl = \App\Models\PropertyPortalSetting::getValue('company_logo_url', '');
    $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '');
    $portalRole = $propertyPortal ?? 'agent';
    $agentNavMode = $portalRole === 'agent' ? PropertyNavMode::current() : PropertyNavMode::CLASSIC;
    $homeRoute = match ($portalRole) {
        'landlord' => 'property.landlord.portfolio',
        'tenant' => 'property.tenant.home',
        default => 'property.dashboard',
    };

    $notifyRoute = match ($portalRole) {
        'landlord' => route('property.landlord.notifications'),
        'tenant' => route('property.tenant.notifications'),
        default => route('property.notifications'),
    };

    $notifyNavPattern = match ($portalRole) {
        'landlord' => 'property.landlord.notifications',
        'tenant' => 'property.tenant.notifications',
        default => 'property.notifications',
    };

    $quickLinks = match ($portalRole) {
        'landlord' => LandlordPortalNavigation::headerQuickLinks(),
        'tenant' => [
            ['label' => 'Home', 'route' => 'property.tenant.home', 'patterns' => ['property.tenant.home']],
            ['label' => 'Pay rent', 'route' => 'property.tenant.payments.pay', 'patterns' => ['property.tenant.payments.pay']],
            ['label' => 'Payments', 'route' => 'property.tenant.payments.index', 'patterns' => ['property.tenant.payments.index', 'property.tenant.payments.history', 'property.tenant.payments.receipts']],
            ['label' => 'Maintenance', 'route' => 'property.tenant.maintenance.index', 'patterns' => ['property.tenant.maintenance.*']],
            ['label' => 'Lease', 'route' => 'property.tenant.lease', 'patterns' => ['property.tenant.lease']],
        ],
        default => match ($agentNavMode) {
            PropertyNavMode::WORKSPACE, PropertyNavMode::HYBRID => PropertyNavigation::agentHeaderWorkspaces(),
            default => [
                ['label' => 'Dashboard', 'route' => 'property.dashboard', 'patterns' => ['property.dashboard']],
                ['label' => 'Rent roll', 'route' => 'property.revenue.rent_roll', 'patterns' => ['property.revenue.rent_roll']],
                ['label' => 'Arrears', 'route' => 'property.revenue.arrears', 'patterns' => ['property.revenue.arrears', 'property.revenue.arrears.*']],
                ['label' => 'Properties', 'route' => 'property.properties.list', 'patterns' => ['property.properties.*', 'property.landlords.index', 'property.units.store']],
                ['label' => 'Tenants', 'route' => 'property.tenants.directory', 'patterns' => ['property.tenants.*', 'property.leases.store']],
                ['label' => 'Revenue', 'route' => 'property.revenue.index', 'patterns' => [
                    'property.revenue.index',
                    'property.revenue.uninvoiced_leases',
                    'property.revenue.uninvoiced_leases.*',
                    'property.revenue.invoices',
                    'property.revenue.invoices.*',
                    'property.revenue.payments',
                    'property.revenue.payments.*',
                    'property.revenue.receipts',
                    'property.revenue.tenant_credits',
                    'property.revenue.penalties',
                    'property.revenue.penalties.*',
                    'property.revenue.utilities',
                    'property.revenue.utilities.*',
                ]],
                ['label' => 'Maintenance', 'route' => 'property.maintenance.requests', 'patterns' => ['property.maintenance.*']],
                ['label' => 'Listings', 'route' => 'property.listings.create', 'patterns' => ['property.listings.*']],
                ['label' => 'Financials', 'route' => 'property.financials.index', 'patterns' => ['property.financials.*']],
                ['label' => 'Accounting', 'route' => 'property.accounting.index', 'patterns' => ['property.accounting.*']],
                (($auth = Auth::user()) && (($auth->is_super_admin ?? false) === true))
                    ? ['label' => 'Property users', 'route' => 'property.settings.roles', 'patterns' => ['property.settings.roles']]
                    : ['label' => 'Settings', 'route' => 'property.settings.index', 'patterns' => ['property.settings.*']],
            ],
        },
    };

    $activeAgentWorkspace = ($portalRole === 'agent' && in_array($agentNavMode, [PropertyNavMode::WORKSPACE, PropertyNavMode::HYBRID], true))
        ? PropertyNavigation::workspaceForRoute(Route::currentRouteName() ?? '')
        : null;

    $linkActive = function (array $patterns): bool {
        foreach ($patterns as $p) {
            if ($p && request()->routeIs($p)) {
                return true;
            }
        }

        return false;
    };

    $todayLabel = now()->format('D, j M');

    $notificationItems = collect();
    $notificationUnread = 0;
    if ($portalRole === 'agent' && Auth::check() && \Illuminate\Support\Facades\Schema::hasTable('pm_message_logs')) {
        $uid = (int) Auth::id();
        $baseNotifQuery = \App\Models\PmMessageLog::query()
            ->whereIn('channel', ['system', 'email', 'sms'])
            ->orderByDesc('id');

        $notificationItems = (clone $baseNotifQuery)
            ->limit(8)
            ->get(['id', 'channel', 'subject', 'body', 'delivery_status', 'created_at']);

        if (\Illuminate\Support\Facades\Schema::hasTable('pm_message_reads')) {
            $notificationUnread = (int) \App\Models\PmMessageLog::query()
                ->leftJoin('pm_message_reads as pmr', function ($join) use ($uid) {
                    $join->on('pm_message_logs.id', '=', 'pmr.pm_message_log_id')
                        ->where('pmr.user_id', '=', $uid);
                })
                ->whereIn('pm_message_logs.channel', ['system', 'email', 'sms'])
                ->whereNull('pmr.id')
                ->count();
        }
    }
@endphp

<header class="property-topbar relative z-[5000] overflow-visible flex-shrink-0 shadow-md shadow-emerald-950/10">
    @if (session()->has('pm_impersonator_id'))
        <div class="bg-amber-200 text-amber-950 border-b border-amber-300">
            <div class="px-3 sm:px-5 lg:px-6 py-2 flex items-center justify-between gap-3">
                <p class="text-xs sm:text-sm font-semibold">
                    You are impersonating a user for support/testing.
                </p>
                <form method="post" action="{{ route('property.impersonation.stop') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-lg bg-amber-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-950">
                        Stop impersonating
                    </button>
                </form>
            </div>
        </div>
    @endif
    <div class="bg-gradient-to-r from-emerald-700 via-emerald-600 to-emerald-700 text-white overflow-visible">
        <div class="relative z-[120] flex items-center h-14 sm:h-[60px] lg:h-[64px] px-3 sm:px-5 lg:px-6 gap-2 sm:gap-3 min-w-0">
            <div class="flex items-center gap-2 sm:gap-4 min-w-0 shrink">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="lg:hidden shrink-0 p-2 rounded-lg text-white/90 hover:bg-white/15 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                    aria-label="Open menu"
                >
                    <i class="fa-solid fa-bars text-xl" aria-hidden="true"></i>
                </button>
                <a
                    href="{{ route($homeRoute) }}"
                    data-turbo-frame="property-main"
                    data-property-nav="{{ $homeRoute }}"
                    class="flex items-center gap-2.5 sm:gap-3 min-w-0 group shrink-0"
                >
                    <span class="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/25 shadow-inner">
                        @if ($companyLogoUrl)
                            <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-7 w-7 sm:h-8 sm:w-8 object-contain rounded-md bg-white/95 p-0.5" />
                        @else
                            @if ($portalRole === 'agent')
                                <i class="fa-solid fa-building text-lg sm:text-xl text-white" aria-hidden="true"></i>
                            @elseif ($portalRole === 'landlord')
                                <i class="fa-solid fa-hand-holding-dollar text-lg sm:text-xl text-white" aria-hidden="true"></i>
                            @else
                                <i class="fa-solid fa-house-user text-lg sm:text-xl text-white" aria-hidden="true"></i>
                            @endif
                        @endif
                    </span>
                    <span class="min-w-0 leading-tight hidden sm:block">
                        @if ($companyName !== '')
                            <span class="block text-[15px] sm:text-lg font-bold tracking-tight text-white truncate">{{ $companyName }}</span>
                        @elseif ($portalRole === 'agent')
                            <span class="block text-[15px] sm:text-lg font-bold tracking-tight text-white truncate">Agent workspace</span>
                        @elseif ($portalRole === 'landlord')
                            <span class="block text-[15px] sm:text-lg font-bold tracking-tight text-white truncate">Landlord portal</span>
                        @else
                            <span class="block text-[15px] sm:text-lg font-bold tracking-tight text-white truncate">Tenant portal</span>
                        @endif
                    </span>
                </a>

                @isset($header)
                    <div class="hidden md:flex items-center min-w-0 pl-3 sm:pl-4 ml-2 sm:ml-3 border-l border-white/25">
                        @if ($activeAgentWorkspace)
                            <span class="hidden lg:inline-flex items-center rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-100 mr-2 shrink-0">{{ $activeAgentWorkspace['label'] }}</span>
                        @endif
                        <span data-property-header-title class="text-xs sm:text-sm font-medium text-white/95 truncate max-w-[140px] sm:max-w-[200px] lg:max-w-[280px] xl:max-w-md">{{ $header }}</span>
                    </div>
                @endisset
            </div>

            @if ($portalRole === 'agent')
                <form
                    id="property-global-search-form"
                    method="get"
                    action="{{ route('property.search') }}"
                    data-turbo-frame="property-main"
                    class="hidden lg:flex items-center flex-1 min-w-0 basis-0 max-w-xs xl:max-w-sm 2xl:max-w-md mx-1 xl:mx-2"
                >
                    <label class="sr-only" for="property-global-search">Search</label>
                    <div class="relative w-full z-[150]" id="property-global-search-wrap">
                        <input
                            id="property-global-search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search…"
                            autocomplete="off"
                            title="Search tenants, units, invoices, payments"
                            class="w-full min-w-0 rounded-xl bg-white text-slate-900 placeholder:text-slate-500 border border-white/70 px-3 xl:px-4 py-2.5 pr-11 text-sm font-medium shadow-inner focus:outline-none focus:ring-2 focus:ring-white/40 focus:border-white/40"
                        />
                        <button type="button" id="property-global-search-btn" class="absolute right-1.5 top-1.5 h-8 w-8 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700">
                            <i class="fa-solid fa-magnifying-glass text-sm" aria-hidden="true"></i>
                            <span class="sr-only">Search</span>
                        </button>
                        <div id="property-search-suggest" class="hidden absolute z-[99999] mt-2 w-full rounded-xl border border-slate-200 bg-white text-slate-800 shadow-2xl overflow-hidden"></div>
                    </div>
                </form>
            @endif

            <div class="flex items-center gap-0.5 sm:gap-2 shrink-0 ml-auto">
                <div class="hidden xl:flex flex-col items-end justify-center shrink-0 text-right leading-tight pr-1">
                    <span class="text-[10px] uppercase tracking-wider text-white/55 font-semibold">Today</span>
                    <time class="text-xs font-semibold text-white/90 tabular-nums" datetime="{{ now()->toDateString() }}">{{ $todayLabel }}</time>
                </div>
                @if ($portalRole === 'agent')
                    <button
                        type="button"
                        id="property-mobile-search-open"
                        class="lg:hidden inline-flex h-10 w-10 items-center justify-center rounded-lg text-white/90 hover:bg-white/15 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                        aria-label="Open search"
                    >
                        <i class="fa-solid fa-magnifying-glass text-lg" aria-hidden="true"></i>
                    </button>
                @endif

                @if ($portalRole === 'agent')
                    <a
                        href="{{ route('public.home') }}"
                        target="_blank"
                        rel="noopener"
                        class="hidden sm:flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-white/90 hover:text-white hover:bg-white/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50 text-xs font-semibold"
                        title="Open public website"
                    >
                        <i class="fa-solid fa-globe" aria-hidden="true"></i>
                        Website
                    </a>
                @endif

                <div class="hidden sm:block relative z-[60]" x-data="{ bellOpen: false }" @click.outside="bellOpen = false">
                    <button
                        type="button"
                        @click="bellOpen = !bellOpen"
                        class="relative p-2 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                        title="Messages &amp; notifications"
                        aria-label="Notifications"
                    >
                        <i class="fa-regular fa-bell text-lg sm:text-xl" aria-hidden="true"></i>
                        @if ($notificationUnread > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[1.05rem] h-[1.05rem] px-1 rounded-full bg-rose-500 text-white text-[10px] leading-[1.05rem] text-center font-bold">{{ $notificationUnread > 99 ? '99+' : $notificationUnread }}</span>
                        @endif
                    </button>

                    <div
                        x-show="bellOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="absolute right-0 mt-2 w-[23rem] max-w-[90vw] rounded-xl bg-white text-slate-800 shadow-xl border border-slate-200/90 overflow-hidden"
                        x-cloak
                    >
                        <div class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-900">Alerts</p>
                            <a href="{{ $notifyRoute }}" data-turbo-frame="property-main" data-property-nav="{{ $notifyNavPattern }}" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">View all</a>
                        </div>
                        <div class="max-h-80 overflow-auto">
                            @forelse ($notificationItems as $item)
                                <a href="{{ $notifyRoute }}" data-turbo-frame="property-main" data-property-nav="{{ $notifyNavPattern }}" class="block px-4 py-3 border-b border-slate-100 last:border-b-0 hover:bg-slate-50">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ strtoupper((string) ($item->channel ?? 'notice')) }}</p>
                                    <p class="text-sm font-medium text-slate-900 mt-0.5">{{ \Illuminate\Support\Str::limit((string) ($item->subject ?: 'Notification'), 80) }}</p>
                                    <p class="text-xs text-slate-600 mt-0.5">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($item->body ?? '')), 110) }}</p>
                                    <p class="text-[11px] text-slate-400 mt-1">{{ optional($item->created_at)->diffForHumans() }}</p>
                                </a>
                            @empty
                                <div class="px-4 py-6 text-sm text-slate-500">No alerts yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <a
                    href="{{ $notifyRoute }}"
                    data-turbo-frame="property-main"
                    data-property-nav="{{ $notifyNavPattern }}"
                    class="sm:hidden relative p-2 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors aria-[current=page]:bg-white/20 aria-[current=page]:text-white"
                    aria-label="Notifications"
                >
                    <i class="fa-regular fa-bell text-lg" aria-hidden="true"></i>
                    @if ($notificationUnread > 0)
                        <span class="absolute -top-0.5 -right-0.5 min-w-[1rem] h-[1rem] px-1 rounded-full bg-rose-500 text-white text-[9px] leading-[1rem] text-center font-bold">{{ $notificationUnread > 99 ? '99+' : $notificationUnread }}</span>
                    @endif
                </a>

                <div class="hidden sm:block w-px h-8 bg-white/20 mx-0.5" aria-hidden="true"></div>

                <x-staff-module-switcher current="property" variant="pill" />

                <div class="relative z-[60]" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                    <button
                        type="button"
                        @click="userMenuOpen = !userMenuOpen"
                        class="flex items-center gap-2 sm:gap-3 pl-1 sm:pl-2 pr-1 sm:pr-2 py-1 rounded-lg hover:bg-white/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                    >
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-white/20 ring-2 ring-white/35 flex items-center justify-center text-white font-semibold text-sm sm:text-base shadow-sm overflow-hidden">
                            @if (Auth::check() && filled(Auth::user()->profile_photo_url))
                                <img src="{{ Auth::user()->profile_photo_url }}" alt="Profile image" class="h-full w-full object-cover">
                            @elseif (Auth::check() && Auth::user()->name)
                                {{ mb_substr(Auth::user()->name, 0, 1) }}
                            @else
                                U
                            @endif
                        </div>
                        <span class="hidden md:block text-sm font-medium text-white truncate max-w-[160px] text-left leading-tight">
                            {{ Auth::user()->name ?? 'User' }}
                        </span>
                        <i class="fa-solid fa-chevron-down text-sm text-white/70 hidden md:block transition-transform duration-200 shrink-0" :class="{ 'rotate-180': userMenuOpen }" aria-hidden="true"></i>
                    </button>

                    <div
                        x-show="userMenuOpen"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="absolute right-0 mt-2 w-56 rounded-xl bg-white text-slate-800 shadow-xl border border-slate-200/80 py-1.5 z-[100] overflow-hidden"
                        x-cloak
                    >
                        <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/80 md:hidden">
                            <p class="text-sm font-semibold text-slate-900">{{ Auth::user()->name ?? 'User' }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email ?? '' }}</p>
                        </div>

                        <a href="{{ route('profile.edit') }}" data-turbo-frame="property-main" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 transition-colors">
                            <i class="fa-regular fa-user w-4 text-center text-slate-400" aria-hidden="true"></i>
                            Profile settings
                        </a>

                        <x-staff-module-switcher current="property" variant="menu-property" />

                        <div class="border-t border-slate-100 my-1"></div>

                        <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors text-left">
                                <i class="fa-solid fa-right-from-bracket w-4 text-center" aria-hidden="true"></i>
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick shortcuts — horizontal scroll on mobile --}}
        <div class="relative z-[20] border-t border-white/15 bg-emerald-800/40 backdrop-blur-sm @if($portalRole === 'agent' && $agentNavMode === PropertyNavMode::CLASSIC) property-header-quick-classic @elseif($portalRole === 'agent') property-header-quick-workspace @endif">
            <nav class="property-header-quick flex items-center gap-1 px-3 py-1.5 sm:px-4 sm:py-2 overflow-x-auto custom-scrollbar snap-x snap-mandatory" aria-label="Quick shortcuts">
                @foreach ($quickLinks as $link)
                    @php $active = $linkActive($link['patterns']); @endphp
                    <a
                        href="{{ PropertyNavigation::workspaceHref($link) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ implode('|', $link['patterns']) }}"
                        @if ($active) aria-current="page" @endif
                        class="snap-start shrink-0 rounded-lg px-2.5 py-1.5 sm:px-3 text-[11px] sm:text-xs font-semibold transition-colors whitespace-nowrap text-white/90 hover:bg-white/10 aria-[current=page]:bg-white aria-[current=page]:text-emerald-800 aria-[current=page]:shadow-sm min-h-[36px] inline-flex items-center"
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</header>

@if ($portalRole === 'agent')
    {{-- Mobile search overlay --}}
    <div
        id="property-mobile-search-overlay"
        class="fixed inset-0 z-[7000] lg:hidden hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Search"
    >
        <div id="property-mobile-search-backdrop" class="absolute inset-0 bg-slate-950/60 backdrop-blur-[1px]"></div>
        <div class="relative mx-3 mt-3 sm:mx-4 sm:mt-4 rounded-2xl border border-white/20 bg-emerald-700 p-3 shadow-2xl">
            <div class="flex items-center gap-2">
                <form
                    id="property-mobile-search-form"
                    method="get"
                    action="{{ route('property.search') }}"
                    data-turbo-frame="property-main"
                    class="flex-1 min-w-0"
                >
                    <label class="sr-only" for="property-mobile-search-input">Search</label>
                    <input
                        id="property-mobile-search-input"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search tenants, units, invoices…"
                        autocomplete="off"
                        class="w-full rounded-xl bg-white text-slate-900 placeholder:text-slate-500 border border-white/70 px-4 py-3 text-sm font-medium shadow-inner focus:outline-none focus:ring-2 focus:ring-white/40"
                    />
                </form>
                <button
                    type="button"
                    id="property-mobile-search-close"
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white/90 hover:bg-white/15"
                    aria-label="Close search"
                >
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div id="property-mobile-search-suggest" class="hidden mt-2 max-h-[60vh] overflow-y-auto rounded-xl border border-slate-200 bg-white text-slate-800 shadow-xl"></div>
        </div>
    </div>
@endif

@if ($portalRole === 'agent')
    <script>
        function initPropertyMobileSearchOverlay() {
            const openBtn = document.getElementById('property-mobile-search-open');
            const overlay = document.getElementById('property-mobile-search-overlay');
            const backdrop = document.getElementById('property-mobile-search-backdrop');
            const closeBtn = document.getElementById('property-mobile-search-close');
            const input = document.getElementById('property-mobile-search-input');
            const form = document.getElementById('property-mobile-search-form');
            const box = document.getElementById('property-mobile-search-suggest');
            if (!openBtn || !overlay || !input || !form || !box) return;
            if (openBtn.dataset.mobileSearchInit === '1') return;
            openBtn.dataset.mobileSearchInit = '1';

            const endpoint = @json(route('property.search.suggest'));
            let timer = null;
            let ctrl = null;
            let lastQ = '';
            let firstUrl = '';

            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
            const highlight = (txt, q) => {
                const t = String(txt ?? '');
                const qq = String(q ?? '').trim();
                if (!qq) return esc(t);
                const safe = esc(t);
                const re = new RegExp(qq.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'ig');
                return safe.replace(re, (m) => `<mark class="bg-yellow-100 px-0.5 rounded">${m}</mark>`);
            };
            const labels = { pages: 'Pages', landlords: 'Landlords', tenants: 'Tenants', properties: 'Properties', units: 'Units', invoices: 'Invoices', payments: 'Payments' };

            const closeOverlay = () => {
                overlay.classList.add('hidden');
                document.documentElement.classList.remove('overflow-hidden');
                box.classList.add('hidden');
                box.innerHTML = '';
            };
            const openOverlay = () => {
                overlay.classList.remove('hidden');
                document.documentElement.classList.add('overflow-hidden');
                window.setTimeout(() => input.focus(), 50);
            };
            const render = (payload, q) => {
                const groups = payload?.groups || {};
                const keys = Object.keys(labels);
                const hasAny = keys.some((k) => Array.isArray(groups[k]) && groups[k].length > 0);
                if (!hasAny) {
                    box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">No results</div>';
                    box.classList.remove('hidden');
                    return;
                }
                const html = keys.map((k) => {
                    const rows = Array.isArray(groups[k]) ? groups[k] : [];
                    if (rows.length === 0) return '';
                    const items = rows.slice(0, 5).map((r) => `
                        <a href="${esc(r.url)}" data-turbo-frame="property-main" class="block px-4 py-3 hover:bg-slate-50">
                            <div class="text-sm font-semibold text-slate-900">${highlight(r.title, q)}</div>
                            <div class="text-xs text-slate-500">${highlight(r.subtitle, q)}</div>
                        </a>
                    `).join('');
                    return `<div class="border-b border-slate-100 last:border-b-0"><div class="px-4 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 bg-slate-50">${labels[k]}</div>${items}</div>`;
                }).join('');
                const firstGroup = keys.find((k) => Array.isArray(groups[k]) && groups[k].length > 0);
                firstUrl = firstGroup ? (groups[firstGroup][0]?.url || '') : '';
                box.innerHTML = html;
                box.classList.remove('hidden');
            };
            const load = async () => {
                const q = (input.value || '').trim();
                if (q.length < 1) { box.classList.add('hidden'); box.innerHTML = ''; return; }
                if (q === lastQ) return;
                lastQ = q;
                if (ctrl) ctrl.abort();
                ctrl = new AbortController();
                try {
                    const res = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: ctrl.signal });
                    if (!res.ok) { box.classList.add('hidden'); return; }
                    render(await res.json(), q);
                } catch (_) { box.classList.add('hidden'); }
            };

            openBtn.addEventListener('click', openOverlay);
            closeBtn?.addEventListener('click', closeOverlay);
            backdrop?.addEventListener('click', closeOverlay);
            input.addEventListener('input', () => { if (timer) clearTimeout(timer); timer = setTimeout(load, 220); });
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!box.classList.contains('hidden') && firstUrl) { window.visitPropertyMain?.(firstUrl); closeOverlay(); return; }
                load();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeOverlay();
            });
        }
        document.addEventListener('DOMContentLoaded', initPropertyMobileSearchOverlay);
        document.addEventListener('turbo:load', initPropertyMobileSearchOverlay);
    </script>
@endif

@if ($portalRole === 'agent')
    <script>
        function initPropertyHeaderLiveSearch() {
            const input = document.getElementById('property-global-search');
            const form = document.getElementById('property-global-search-form');
            const btn = document.getElementById('property-global-search-btn');
            const box = document.getElementById('property-search-suggest');
            const wrap = document.getElementById('property-global-search-wrap');
            if (!input || !box || !wrap || !form) return;
            if (input.dataset.liveSearchInit === '1') return;
            input.dataset.liveSearchInit = '1';
            const endpoint = @json(route('property.search.suggest'));
            let timer = null;
            let ctrl = null;
            let lastQ = '';
            let firstUrl = '';

            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
            const highlight = (txt, q) => {
                const t = String(txt ?? '');
                const qq = String(q ?? '').trim();
                if (!qq) return esc(t);
                const safe = esc(t);
                const re = new RegExp(qq.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'ig');
                return safe.replace(re, (m) => `<mark class="bg-yellow-100 px-0.5 rounded">${m}</mark>`);
            };
            const labels = {
                pages: 'Pages',
                landlords: 'Landlords',
                tenants: 'Tenants',
                properties: 'Properties',
                units: 'Units',
                invoices: 'Invoices',
                payments: 'Payments',
            };

            const closeBox = () => {
                box.classList.add('hidden');
                box.innerHTML = '';
                firstUrl = '';
            };
            const render = (payload, q) => {
                const groups = payload?.groups || {};
                const keys = Object.keys(labels);
                const hasAny = keys.some((k) => Array.isArray(groups[k]) && groups[k].length > 0);
                if (!hasAny) {
                    box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">No results</div>';
                    box.classList.remove('hidden');
                    return;
                }
                const html = keys.map((k) => {
                    const rows = Array.isArray(groups[k]) ? groups[k] : [];
                    if (rows.length === 0) return '';
                    const items = rows.slice(0, 5).map((r) => `
                        <a href="${esc(r.url)}" data-turbo-frame="property-main" class="block px-4 py-2 hover:bg-slate-50">
                            <div class="text-sm font-semibold text-slate-900">${highlight(r.title, q)}</div>
                            <div class="text-xs text-slate-500">${highlight(r.subtitle, q)}</div>
                        </a>
                    `).join('');
                    return `
                        <div class="border-b border-slate-100 last:border-b-0">
                            <div class="px-4 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 bg-slate-50">${labels[k]}</div>
                            ${items}
                        </div>
                    `;
                }).join('');
                const firstGroup = keys.find((k) => Array.isArray(groups[k]) && groups[k].length > 0);
                firstUrl = firstGroup ? (groups[firstGroup][0]?.url || '') : '';
                box.innerHTML = html;
                box.classList.remove('hidden');
            };

            const load = async () => {
                const q = (input.value || '').trim();
                if (q.length < 1) {
                    closeBox();
                    return;
                }
                if (q === lastQ) return;
                lastQ = q;
                if (ctrl) ctrl.abort();
                ctrl = new AbortController();
                try {
                    const res = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: ctrl.signal,
                    });
                    if (!res.ok) {
                        closeBox();
                        return;
                    }
                    const payload = await res.json();
                    render(payload, q);
                } catch (_) {
                    closeBox();
                }
            };

            input.addEventListener('input', () => {
                if (timer) clearTimeout(timer);
                timer = setTimeout(load, 220);
            });
            input.addEventListener('focus', () => {
                if ((input.value || '').trim().length >= 1) load();
            });
            if (btn) {
                btn.addEventListener('click', () => {
                    load();
                });
            }
            // Do not redirect to full search page from header input.
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!box.classList.contains('hidden') && firstUrl) {
                    window.visitPropertyMain?.(firstUrl);
                    return;
                }
                load();
            });
            document.addEventListener('click', (e) => {
                if (!wrap.contains(e.target)) closeBox();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeBox();
            });
        }
        document.addEventListener('DOMContentLoaded', initPropertyHeaderLiveSearch);
        document.addEventListener('turbo:load', initPropertyHeaderLiveSearch);
    </script>
@endif
