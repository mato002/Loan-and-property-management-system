@php
    $navActive = function ($patterns): bool {
        $patterns = is_array($patterns) ? $patterns : [$patterns];
        foreach ($patterns as $p) {
            if ($p && request()->routeIs($p)) {
                return true;
            }
        }

        return false;
    };

    $sectionAnyActive = function (array $items) use ($navActive): bool {
        foreach ($items as $it) {
            if ($navActive($it['active'])) {
                return true;
            }
        }

        return false;
    };

    $sections = [
        [
            'heading' => '',
            'icon' => 'fa-gauge-high',
            'kicker' => null,
            'items' => [
                [
                    'label' => 'Dashboard',
                    'sublabel' => 'Alerts · risks · KPIs',
                    'route' => 'property.dashboard',
                    'active' => ['property.dashboard'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Revenue',
            'icon' => 'fa-sack-dollar',
            'kicker' => 'Most used — keep high',
            'items' => [
                [
                    'label' => 'Rent roll',
                    'sublabel' => 'Who owes what',
                    'route' => 'property.revenue.rent_roll',
                    'active' => ['property.revenue.rent_roll'],
                    'badge' => 'Top',
                ],
                [
                    'label' => 'Arrears',
                    'sublabel' => 'Aging 7 / 14 / 30+',
                    'route' => 'property.revenue.arrears',
                    'active' => ['property.revenue.arrears'],
                    'badge' => 'Top',
                ],
                [
                    'label' => 'Invoices & billing',
                    'sublabel' => null,
                    'route' => 'property.revenue.invoices',
                    'active' => ['property.revenue.invoices'],
                    'badge' => null,
                ],
                [
                    'label' => 'Payments',
                    'sublabel' => 'M-Pesa · logs · reconciliation',
                    'route' => 'property.revenue.payments',
                    'active' => ['property.revenue.payments'],
                    'badge' => null,
                ],
                [
                    'label' => 'Utilities & charges',
                    'sublabel' => 'Separate from core rent',
                    'route' => 'property.revenue.utilities',
                    'active' => ['property.revenue.utilities', 'property.revenue.utilities.store', 'property.revenue.utilities.destroy'],
                    'badge' => null,
                ],
                [
                    'label' => 'Penalties & rules',
                    'sublabel' => 'Automation definitions',
                    'route' => 'property.revenue.penalties',
                    'active' => ['property.revenue.penalties', 'property.revenue.penalties.store', 'property.revenue.penalties.destroy'],
                    'badge' => null,
                ],
                [
                    'label' => 'Receipts (eTIMS)',
                    'sublabel' => null,
                    'route' => 'property.revenue.receipts',
                    'active' => ['property.revenue.receipts'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Properties',
            'icon' => 'fa-building',
            'kicker' => 'Clean · structural — no financials',
            'items' => [
                [
                    'label' => 'All properties',
                    'sublabel' => null,
                    'route' => 'property.properties.list',
                    'active' => [
                        'property.properties.list',
                        'property.properties.store',
                        'property.properties.landlords.attach',
                        'property.properties.landlords.detach',
                        'property.properties.landlords.ownership',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Landlords',
                    'sublabel' => 'Linked owners · portfolio',
                    'route' => 'property.landlords.index',
                    'active' => ['property.landlords.index'],
                    'badge' => null,
                ],
                [
                    'label' => 'Units',
                    'sublabel' => null,
                    'route' => 'property.properties.units',
                    'active' => ['property.properties.units', 'property.units.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Occupancy view',
                    'sublabel' => 'Vacant vs occupied',
                    'route' => 'property.properties.occupancy',
                    'active' => ['property.properties.occupancy'],
                    'badge' => null,
                ],
                [
                    'label' => 'Unit performance',
                    'sublabel' => 'Rent vs lease',
                    'route' => 'property.properties.performance',
                    'active' => ['property.properties.performance'],
                    'badge' => null,
                ],
                [
                    'label' => 'Amenities',
                    'sublabel' => null,
                    'route' => 'property.properties.amenities',
                    'active' => [
                        'property.properties.amenities',
                        'property.properties.amenities.store',
                        'property.properties.amenities.attach',
                        'property.properties.amenities.detach',
                        'property.properties.amenities.destroy',
                    ],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Tenants',
            'icon' => 'fa-users',
            'kicker' => 'People-focused · leases live here',
            'items' => [
                [
                    'label' => 'Tenant list',
                    'sublabel' => null,
                    'route' => 'property.tenants.directory',
                    'active' => ['property.tenants.directory', 'property.tenants.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Tenant profiles',
                    'sublabel' => null,
                    'route' => 'property.tenants.profiles',
                    'active' => ['property.tenants.profiles'],
                    'badge' => null,
                ],
                [
                    'label' => 'Lease agreements',
                    'sublabel' => null,
                    'route' => 'property.tenants.leases',
                    'active' => ['property.tenants.leases', 'property.leases.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Lease expiry',
                    'sublabel' => 'Next 90 days',
                    'route' => 'property.tenants.expiry',
                    'active' => ['property.tenants.expiry'],
                    'badge' => null,
                ],
                [
                    'label' => 'Move-ins / move-outs',
                    'sublabel' => null,
                    'route' => 'property.tenants.movements',
                    'active' => ['property.tenants.movements', 'property.tenants.movements.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Notices',
                    'sublabel' => 'Vacate · eviction',
                    'route' => 'property.tenants.notices',
                    'active' => ['property.tenants.notices', 'property.tenants.notices.store'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Maintenance',
            'icon' => 'fa-screwdriver-wrench',
            'kicker' => 'Action-heavy',
            'items' => [
                [
                    'label' => 'Requests',
                    'sublabel' => 'Tickets',
                    'route' => 'property.maintenance.requests',
                    'active' => ['property.maintenance.requests', 'property.maintenance.requests.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Active jobs',
                    'sublabel' => null,
                    'route' => 'property.maintenance.jobs',
                    'active' => ['property.maintenance.jobs', 'property.maintenance.jobs.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Maintenance history',
                    'sublabel' => null,
                    'route' => 'property.maintenance.history',
                    'active' => ['property.maintenance.history'],
                    'badge' => null,
                ],
                [
                    'label' => 'Cost tracking',
                    'sublabel' => null,
                    'route' => 'property.maintenance.costs',
                    'active' => ['property.maintenance.costs'],
                    'badge' => null,
                ],
                [
                    'label' => 'Issue frequency',
                    'sublabel' => null,
                    'route' => 'property.maintenance.frequency',
                    'active' => ['property.maintenance.frequency'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Vendors',
            'icon' => 'fa-truck-field',
            'kicker' => 'Separate module',
            'items' => [
                [
                    'label' => 'Vendor directory',
                    'sublabel' => null,
                    'route' => 'property.vendors.directory',
                    'active' => ['property.vendors.directory', 'property.vendors.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Job bidding',
                    'sublabel' => null,
                    'route' => 'property.vendors.bidding',
                    'active' => [
                        'property.vendors.bidding',
                        'property.vendors.bidding.create',
                        'property.vendors.bidding.store',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Quotes',
                    'sublabel' => null,
                    'route' => 'property.vendors.quotes',
                    'active' => ['property.vendors.quotes'],
                    'badge' => null,
                ],
                [
                    'label' => 'Vendor performance',
                    'sublabel' => null,
                    'route' => 'property.vendors.performance',
                    'active' => ['property.vendors.performance'],
                    'badge' => null,
                ],
                [
                    'label' => 'Work records',
                    'sublabel' => null,
                    'route' => 'property.vendors.work_records',
                    'active' => ['property.vendors.work_records'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Analytics',
            'icon' => 'fa-chart-line',
            'kicker' => 'Not daily ops — avoid clutter',
            'items' => [
                [
                    'label' => 'Rent collection rate',
                    'sublabel' => null,
                    'route' => 'property.performance.collection_rate',
                    'active' => ['property.performance.collection_rate'],
                    'badge' => null,
                ],
                [
                    'label' => 'Vacancy trends',
                    'sublabel' => null,
                    'route' => 'property.performance.vacancy',
                    'active' => ['property.performance.vacancy'],
                    'badge' => null,
                ],
                [
                    'label' => 'Arrears trends',
                    'sublabel' => null,
                    'route' => 'property.performance.arrears_trends',
                    'active' => ['property.performance.arrears_trends'],
                    'badge' => null,
                ],
                [
                    'label' => 'Maintenance cost trends',
                    'sublabel' => null,
                    'route' => 'property.performance.maintenance_trends',
                    'active' => ['property.performance.maintenance_trends'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Communications',
            'icon' => 'fa-comments',
            'kicker' => null,
            'items' => [
                [
                    'label' => 'SMS / email',
                    'sublabel' => null,
                    'route' => 'property.communications.messages',
                    'active' => ['property.communications.messages', 'property.communications.messages.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Bulk messaging',
                    'sublabel' => null,
                    'route' => 'property.communications.bulk',
                    'active' => ['property.communications.bulk', 'property.communications.bulk.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Templates',
                    'sublabel' => null,
                    'route' => 'property.communications.templates',
                    'active' => [
                        'property.communications.templates',
                        'property.communications.templates.store',
                        'property.communications.templates.destroy',
                    ],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Listings',
            'icon' => 'fa-sign-hanging',
            'kicker' => 'Lean',
            'items' => [
                [
                    'label' => 'Setup listing',
                    'sublabel' => 'New → photos & publish',
                    'route' => 'property.listings.create',
                    'active' => ['property.listings.create'],
                    'badge' => null,
                ],
                [
                    'label' => 'Vacant units',
                    'sublabel' => null,
                    'route' => 'property.listings.vacant',
                    'active' => [
                        'property.listings.vacant',
                        'property.listings.vacant.public.edit',
                        'property.listings.vacant.public.update',
                        'property.listings.vacant.public.photos.store',
                        'property.listings.vacant.public.photos.destroy',
                    ],
                    'badge' => null,
                ],
                [
                    'label' => 'Live on website',
                    'sublabel' => null,
                    'route' => 'property.listings.ads',
                    'active' => ['property.listings.ads'],
                    'badge' => null,
                ],
                [
                    'label' => 'Leads',
                    'sublabel' => 'Optional early',
                    'route' => 'property.listings.leads',
                    'active' => ['property.listings.leads', 'property.listings.leads.store', 'property.listings.leads.update'],
                    'badge' => null,
                ],
                [
                    'label' => 'Applications',
                    'sublabel' => 'Roadmap',
                    'route' => 'property.listings.applications',
                    'active' => [
                        'property.listings.applications',
                        'property.listings.applications.store',
                        'property.listings.applications.update',
                    ],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'AI advisor',
            'icon' => 'fa-robot',
            'kicker' => 'Also in floating button',
            'items' => [
                [
                    'label' => 'Ask anything',
                    'sublabel' => 'Suggested queries · insights',
                    'route' => 'property.advisor',
                    'active' => ['property.advisor', 'property.advisor.ask'],
                    'badge' => null,
                ],
            ],
        ],
        [
            'heading' => 'Settings',
            'icon' => 'fa-gear',
            'kicker' => null,
            'items' => [
                [
                    'label' => 'Users & roles',
                    'sublabel' => null,
                    'route' => 'property.settings.roles',
                    'active' => ['property.settings.roles'],
                    'badge' => null,
                ],
                [
                    'label' => 'Commission settings',
                    'sublabel' => null,
                    'route' => 'property.settings.commission',
                    'active' => ['property.settings.commission', 'property.settings.commission.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'Payment config (M-Pesa)',
                    'sublabel' => null,
                    'route' => 'property.settings.payments',
                    'active' => ['property.settings.payments', 'property.settings.payments.store'],
                    'badge' => null,
                ],
                [
                    'label' => 'System rules',
                    'sublabel' => null,
                    'route' => 'property.settings.rules',
                    'active' => ['property.settings.rules', 'property.settings.rules.store'],
                    'badge' => null,
                ],
            ],
        ],
    ];
@endphp

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
    class="property-sidebar fixed inset-y-0 left-0 z-50 w-[300px] sm:w-[312px] bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-transform duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col shadow-xl shadow-black/20 lg:shadow-none overflow-hidden flex-shrink-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none'"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md lg:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Menu</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="shrink-0 px-3 py-3.5 border-b border-[#264040] bg-[#243d3d]/30">
        <a
            href="{{ route('property.dashboard') }}"
            data-turbo-frame="property-main"
            data-property-nav="property.dashboard"
            @if ($navActive(['property.dashboard'])) aria-current="page" @endif
            class="flex items-center gap-3 min-w-0 group"
            @click="if (window.innerWidth < 1024) sidebarOpen = false"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#406866]/60 ring-1 ring-[#5a8583]/50 shadow-inner">
                <i class="fa-solid fa-building text-xl text-[#c5ebe8]" aria-hidden="true"></i>
            </span>
            <span class="flex flex-col min-w-0 leading-tight text-left">
                <span class="text-base font-bold tracking-tight text-white truncate">Agent workspace</span>
                <span class="text-sm font-medium text-[#8db1af] truncate">Property operations</span>
            </span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto min-h-0 py-2 px-2 custom-scrollbar">
        @foreach ($sections as $si => $section)
            @php
                $secActive = $sectionAnyActive($section['items']);
                $itemCount = count($section['items']);
                $sectionPatterns = collect($section['items'])->pluck('active')->flatten()->unique()->values()->implode('|');
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
                        class="group flex items-start gap-2.5 rounded-xl border-l-[3px] px-3 py-3 text-left transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white"
                    >
                        @if (! empty($section['icon']))
                            <i class="fa-solid {{ $section['icon'] }} text-[#c5ebe8] text-base shrink-0 mt-0.5 w-6 text-center group-aria-[current=page]:text-[#c5ebe8]" aria-hidden="true"></i>
                        @endif
                        <span class="flex flex-col gap-0.5 min-w-0 flex-1">
                            @if (trim((string) ($section['heading'] ?? '')) !== '')
                                <span class="text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-hover:text-[#c5ebe8] group-aria-[current=page]:text-[#c5ebe8]">{{ $section['heading'] }}</span>
                            @endif
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-base font-medium leading-snug text-[#d4e4e3] group-hover:text-white group-aria-[current=page]:text-white group-aria-[current=page]:font-semibold">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $item['badge'] }}</span>
                                @endif
                            </span>
                            @if (! empty($item['sublabel']))
                                <span class="text-sm leading-snug text-[#8db1af] group-hover:text-[#d4e4e3] group-aria-[current=page]:text-[#c5ddd9]">{{ $item['sublabel'] }}</span>
                            @endif
                        </span>
                    </a>
                </div>
            @else
                <div
                    class="{{ $si > 0 ? 'mt-2 pt-2 border-t border-[#406866]/40' : '' }} group"
                    data-property-nav-section
                    data-property-nav-aggregate="{{ $sectionPatterns }}"
                    @if ($secActive) data-section-active @endif
                    x-data="{ open: {{ $secActive ? 'true' : 'false' }} }"
                >
                    <button
                        type="button"
                        class="w-full flex items-start gap-2 rounded-xl px-2 py-2.5 text-left text-[#d4e4e3] hover:bg-[#406866]/40 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50"
                        @click="open = ! open"
                        :aria-expanded="open"
                        aria-controls="nav-section-{{ $si }}"
                    >
                        <span class="flex flex-col items-center justify-center shrink-0 pt-0.5 w-5" aria-hidden="true">
                            <i class="fa-solid fa-chevron-down text-sm text-[#8db1af] transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs font-semibold uppercase tracking-wide text-[#8db1af] group-data-[section-active]:text-[#c5ebe8]">
                                @if (! empty($section['icon']))
                                    <i class="fa-solid {{ $section['icon'] }} text-base text-[#a8c9c7] not-uppercase normal-case group-data-[section-active]:text-[#c5ebe8]" aria-hidden="true"></i>
                                @endif
                                <span>{{ $section['heading'] }}</span>
                            </span>
                            @if (! empty($section['kicker']))
                                <span class="mt-0.5 block text-xs leading-snug text-[#a8c9c7]/95 group-data-[section-active]:text-[#c5ebe8]/95">{{ $section['kicker'] }}</span>
                            @endif
                        </span>
                    </button>

                    <div
                        id="nav-section-{{ $si }}"
                        @unless ($secActive) x-cloak @endunless
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
                            @php $active = $navActive($item['active']); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                data-turbo-frame="property-main"
                                data-property-nav="{{ implode('|', $item['active']) }}"
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
                                @if (! empty($item['sublabel']))
                                    <span class="text-sm leading-snug text-[#8db1af] group-hover:text-[#d4e4e3] group-aria-[current=page]:text-[#c5ddd9]">{{ $item['sublabel'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="pt-4 mt-3 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group">
                    <i class="fa-solid fa-right-from-bracket w-5 shrink-0 text-center text-[#8db1af] group-hover:text-red-400 transition-colors" aria-hidden="true"></i>
                    Log out
                </button>
            </form>
        </div>
    </nav>

    <div class="p-3 border-t border-[#264040] bg-[#243d3d]/40 shrink-0">
        <a
            href="{{ route('profile.edit') }}"
            data-turbo-frame="property-main"
            data-property-nav="profile.edit"
            class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-[#406866]/50 transition-colors"
        >
            <div class="w-11 h-11 rounded-full bg-emerald-500/25 border border-emerald-400/35 flex items-center justify-center text-emerald-200 font-semibold text-base shrink-0">
                @if (Auth::check() && Auth::user()->name)
                    {{ mb_substr(Auth::user()->name, 0, 1) }}
                @else
                    U
                @endif
            </div>
            <div class="flex flex-col overflow-hidden min-w-0">
                <span class="text-base font-medium text-white truncate">{{ Auth::user()->name ?? 'User' }}</span>
                <span class="text-sm text-[#8db1af] truncate">{{ Auth::user()->email ?? '' }}</span>
            </div>
        </a>
    </div>
</aside>
