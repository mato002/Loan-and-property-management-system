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

    $menu = [
        'Portfolio overview' => ['route' => 'property.landlord.portfolio', 'active' => 'property.landlord.portfolio', 'icon' => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z'],
        'Earnings & wallet' => ['route' => 'property.landlord.earnings.index', 'active' => 'property.landlord.earnings.*', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-1a2 2 0 00-2-2H9a2 2 0 00-2 2v1a2 2 0 002 2z'],
        'Properties' => ['route' => 'property.landlord.properties', 'active' => 'property.landlord.properties', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        'Reports' => ['route' => 'property.landlord.reports.index', 'active' => 'property.landlord.reports.*', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        'Documents' => ['route' => 'property.landlord.documents', 'active' => 'property.landlord.documents', 'icon' => 'M7 7V3h7l5 5v13a1 1 0 01-1 1H7a2 2 0 01-2-2V7a1 1 0 011-1h1zm8 1V4.5L17.5 8H15z'],
        'Maintenance' => ['route' => 'property.landlord.maintenance', 'active' => 'property.landlord.maintenance', 'icon' => 'M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z'],
        'Audit trail' => ['route' => 'property.landlord.audit_trail', 'active' => 'property.landlord.audit_trail', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        'Notifications' => ['route' => 'property.landlord.notifications', 'active' => 'property.landlord.notifications', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
        'Opportunities' => ['route' => 'property.landlord.opportunities', 'active' => 'property.landlord.opportunities', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
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
    class="property-sidebar fixed inset-y-0 left-0 z-50 w-[280px] sm:w-[288px] bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-transform duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col shadow-xl shadow-black/20 lg:shadow-none overflow-hidden flex-shrink-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full max-lg:pointer-events-none'"
>
    <div class="h-14 flex items-center justify-between px-4 border-b border-[#264040] bg-[#243d3d]/50 backdrop-blur-md lg:hidden shrink-0">
        <span class="text-sm font-semibold uppercase tracking-wide text-[#8db1af]">Menu</span>
        <button type="button" @click="sidebarOpen = false" class="p-2 rounded-lg text-[#8db1af] hover:text-white hover:bg-[#406866] transition-colors" aria-label="Close menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-2.5 space-y-1 custom-scrollbar">
        <a
            href="{{ route('property.landlord.portfolio') }}"
            data-turbo-frame="property-main"
            data-property-nav="property.landlord.portfolio"
            class="mb-3 flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-3 py-2.5"
        >
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#406866]/60 ring-1 ring-[#5a8583]/50">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-7 w-7 object-contain rounded bg-white/95 p-0.5" />
                @else
                    <svg class="h-5 w-5 text-[#c5ebe8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14M6 20V8h12v12M9 20v-4h6v4M10 12h.01M14 12h.01" /></svg>
                @endif
            </span>
            <span class="min-w-0 text-sm font-semibold text-white truncate">{{ $companyName !== '' ? $companyName : 'Landlord portal' }}</span>
        </a>

        @foreach ($menu as $itemName => $data)
            @php $active = $navActive($data['active']); @endphp
            <a
                href="{{ route($data['route']) }}"
                data-turbo-frame="property-main"
                data-property-nav="{{ $data['active'] }}"
                @if ($active) aria-current="page" @endif
                @click="if (window.innerWidth < 1024) sidebarOpen = false"
                class="group flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium border-l-[3px] transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white aria-[current=page]:font-semibold"
            >
                <svg class="w-6 h-6 shrink-0 text-[#8db1af] group-hover:text-white group-aria-[current=page]:text-emerald-200 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $data['icon'] }}" />
                </svg>
                <span class="truncate">{{ $itemName }}</span>
            </a>
        @endforeach

        <div class="pt-4 mt-4 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group">
                    <svg class="w-6 h-6 shrink-0 text-[#8db1af] group-hover:text-red-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
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
                {{ Auth::check() && Auth::user()->name ? mb_substr(Auth::user()->name, 0, 1) : 'L' }}
            </div>
            <div class="flex flex-col overflow-hidden min-w-0">
                <span class="text-base font-medium text-white truncate">{{ Auth::user()->name ?? 'Landlord' }}</span>
                <span class="text-sm text-[#8db1af] truncate">{{ Auth::user()->email ?? '' }}</span>
            </div>
        </a>
    </div>
</aside>
