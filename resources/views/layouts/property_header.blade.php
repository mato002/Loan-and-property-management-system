@php
    $companyLogoUrl = \App\Models\PropertyPortalSetting::getValue('company_logo_url', '');
    $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '');
    $portalRole = $propertyPortal ?? 'agent';
    $homeRoute = match ($portalRole) {
        'landlord' => 'property.landlord.portfolio',
        'tenant' => 'property.tenant.home',
        default => 'property.dashboard',
    };

    $notifyRoute = match ($portalRole) {
        'landlord' => route('property.landlord.notifications'),
        'tenant' => route('property.tenant.notifications'),
        default => route('property.communications.messages'),
    };

    $notifyNavPattern = match ($portalRole) {
        'landlord' => 'property.landlord.notifications',
        'tenant' => 'property.tenant.notifications',
        default => 'property.communications.messages',
    };

    $quickLinks = match ($portalRole) {
        'landlord' => [
            ['label' => 'Portfolio', 'route' => 'property.landlord.portfolio', 'patterns' => ['property.landlord.portfolio']],
            ['label' => 'Earnings', 'route' => 'property.landlord.earnings.index', 'patterns' => ['property.landlord.earnings.*']],
            ['label' => 'Reports', 'route' => 'property.landlord.reports.index', 'patterns' => ['property.landlord.reports.*']],
            ['label' => 'Maintenance', 'route' => 'property.landlord.maintenance', 'patterns' => ['property.landlord.maintenance']],
            ['label' => 'Audit trail', 'route' => 'property.landlord.audit_trail', 'patterns' => ['property.landlord.audit_trail']],
        ],
        'tenant' => [
            ['label' => 'Home', 'route' => 'property.tenant.home', 'patterns' => ['property.tenant.home']],
            ['label' => 'Pay rent', 'route' => 'property.tenant.payments.pay', 'patterns' => ['property.tenant.payments.pay']],
            ['label' => 'Payments', 'route' => 'property.tenant.payments.index', 'patterns' => ['property.tenant.payments.index', 'property.tenant.payments.history', 'property.tenant.payments.receipts']],
            ['label' => 'Maintenance', 'route' => 'property.tenant.maintenance.index', 'patterns' => ['property.tenant.maintenance.*']],
            ['label' => 'Lease', 'route' => 'property.tenant.lease', 'patterns' => ['property.tenant.lease']],
        ],
        default => [
            ['label' => 'Dashboard', 'route' => 'property.dashboard', 'patterns' => ['property.dashboard']],
            ['label' => 'Rent roll', 'route' => 'property.revenue.rent_roll', 'patterns' => ['property.revenue.rent_roll']],
            ['label' => 'Arrears', 'route' => 'property.revenue.arrears', 'patterns' => ['property.revenue.arrears']],
            ['label' => 'Properties', 'route' => 'property.properties.list', 'patterns' => ['property.properties.*', 'property.landlords.index', 'property.units.store']],
            ['label' => 'Tenants', 'route' => 'property.tenants.directory', 'patterns' => ['property.tenants.*', 'property.leases.store']],
            ['label' => 'Maintenance', 'route' => 'property.maintenance.requests', 'patterns' => ['property.maintenance.*']],
            ['label' => 'Listings', 'route' => 'property.listings.vacant', 'patterns' => ['property.listings.*']],
            ['label' => 'Financials', 'route' => 'property.financials.index', 'patterns' => ['property.financials.*']],
            ['label' => 'Accounting', 'route' => 'property.accounting.index', 'patterns' => ['property.accounting.*']],
            ['label' => 'Settings', 'route' => 'property.settings.index', 'patterns' => ['property.settings.*']],
        ],
    };

    $linkActive = function (array $patterns): bool {
        foreach ($patterns as $p) {
            if ($p && request()->routeIs($p)) {
                return true;
            }
        }

        return false;
    };

    $todayLabel = now()->format('D, j M');
@endphp

<header class="property-topbar relative z-50 flex-shrink-0 shadow-md shadow-emerald-950/10">
    <div class="bg-gradient-to-r from-emerald-700 via-emerald-600 to-emerald-700 text-white">
        <div class="flex items-center justify-between h-[60px] sm:h-[64px] px-3 sm:px-5 lg:px-6 gap-2 sm:gap-4">
            <div class="flex items-center gap-2 sm:gap-4 min-w-0 flex-1">
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
                        <span class="text-xs sm:text-sm font-medium text-white/95 truncate max-w-[140px] sm:max-w-[200px] lg:max-w-[280px] xl:max-w-md">{{ $header }}</span>
                    </div>
                @endisset
            </div>

            <div class="hidden sm:flex flex-col items-end justify-center shrink-0 text-right leading-tight pr-1">
                <span class="text-[10px] uppercase tracking-wider text-white/55 font-semibold">Today</span>
                <time class="text-xs font-semibold text-white/90 tabular-nums" datetime="{{ now()->toDateString() }}">{{ $todayLabel }}</time>
            </div>

            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                <a
                    href="{{ $notifyRoute }}"
                    class="hidden sm:flex p-2 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                    title="Messages &amp; notifications"
                >
                    <i class="fa-regular fa-bell text-lg sm:text-xl" aria-hidden="true"></i>
                </a>

                <a
                    href="{{ $notifyRoute }}"
                    data-turbo-frame="property-main"
                    data-property-nav="{{ $notifyNavPattern }}"
                    class="sm:hidden p-2 rounded-lg text-white/85 hover:text-white hover:bg-white/10 transition-colors aria-[current=page]:bg-white/20 aria-[current=page]:text-white"
                    aria-label="Notifications"
                >
                    <i class="fa-regular fa-bell text-lg" aria-hidden="true"></i>
                </a>

                <div class="hidden sm:block w-px h-8 bg-white/20 mx-0.5" aria-hidden="true"></div>

                <div class="relative z-[60]" x-data="{ userMenuOpen: false }" @click.outside="userMenuOpen = false">
                    <button
                        type="button"
                        @click="userMenuOpen = !userMenuOpen"
                        class="flex items-center gap-2 sm:gap-3 pl-1 sm:pl-2 pr-1 sm:pr-2 py-1 rounded-lg hover:bg-white/10 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                    >
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-white/20 ring-2 ring-white/35 flex items-center justify-center text-white font-semibold text-sm sm:text-base shadow-sm">
                            @if (Auth::check() && Auth::user()->name)
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

                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 transition-colors">
                            <i class="fa-regular fa-user w-4 text-center text-slate-400" aria-hidden="true"></i>
                            Profile settings
                        </a>

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

        {{-- Quick shortcuts (role-specific) --}}
        <div class="hidden md:block border-t border-white/15 bg-emerald-800/35 backdrop-blur-sm">
            <nav class="property-header-quick flex items-center gap-1 px-4 py-2 overflow-x-auto custom-scrollbar" aria-label="Quick shortcuts">
                @foreach ($quickLinks as $link)
                    @php $active = $linkActive($link['patterns']); @endphp
                    <a
                        href="{{ route($link['route']) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ implode('|', $link['patterns']) }}"
                        @if ($active) aria-current="page" @endif
                        class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors whitespace-nowrap text-white/90 hover:bg-white/10 aria-[current=page]:bg-white aria-[current=page]:text-emerald-800 aria-[current=page]:shadow-sm"
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</header>
