@php
    use App\Support\Property\LandlordPortalNavigation;

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

    $menu = LandlordPortalNavigation::sidebarItems();
@endphp

<style>
    @media (min-width: 1024px) {
        .property-sidebar[data-collapsed="1"] .property-collapse-text { display: none !important; }
        .property-sidebar[data-collapsed="1"] .property-collapse-center { justify-content: center !important; }
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
    class="property-sidebar fixed inset-y-0 left-0 z-50 h-screen w-[280px] sm:w-[288px] bg-[#2f4f4f] border-r border-[#264040] text-[#d4e4e3] text-base transform transition-transform duration-300 ease-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col min-h-0 shadow-xl shadow-black/20 lg:shadow-none overflow-hidden flex-shrink-0"
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
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="hidden lg:flex justify-end px-3 pt-2">
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

    <nav class="flex-1 min-h-0 overflow-y-auto overscroll-contain py-4 px-2.5 custom-scrollbar">
        <div class="flex min-h-full flex-col space-y-1">
        @if (auth()->check() && (auth()->user()->is_super_admin ?? false))
            <a
                href="{{ route('superadmin.users.index') }}"
            class="mb-3 flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-3 py-2.5 text-sm font-semibold text-white hover:bg-[#406866]/50 transition-colors property-collapse-center"
            >
                <svg class="h-5 w-5 text-[#c5ebe8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v6c0 5-3 9-7 9s-7-4-7-9V7l7-4z" />
                </svg>
                <span class="property-collapse-text">Super Admin</span>
            </a>
        @endif

        <a
            href="{{ route('property.landlord.portfolio') }}"
            data-turbo-frame="property-main"
            data-property-nav="property.landlord.portfolio"
            class="mb-3 flex items-center gap-3 rounded-xl border border-[#406866]/60 bg-[#243d3d]/35 px-3 py-2.5 property-collapse-center"
        >
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#406866]/60 ring-1 ring-[#5a8583]/50">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName !== '' ? $companyName : 'Company logo' }}" class="h-7 w-7 object-contain rounded bg-white/95 p-0.5" />
                @else
                    <svg class="h-5 w-5 text-[#c5ebe8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 20h14M6 20V8h12v12M9 20v-4h6v4M10 12h.01M14 12h.01" /></svg>
                @endif
            </span>
            <span class="property-collapse-text min-w-0 text-sm font-semibold text-white truncate">{{ $companyName }}</span>
        </a>

        @foreach ($menu as $itemName => $data)
            @php $active = $navActive($data['active']); @endphp
            <a
                href="{{ route($data['route']) }}"
                data-turbo-frame="property-main"
                data-property-nav="{{ implode('|', (array) ($data['active'] ?? [])) }}"
                @if ($active) aria-current="page" @endif
                @click="if (window.innerWidth < 1024) sidebarOpen = false"
                class="group flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium border-l-[3px] transition-all duration-150 border-transparent text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white aria-[current=page]:border-emerald-300 aria-[current=page]:bg-[#406866]/80 aria-[current=page]:text-white aria-[current=page]:font-semibold property-collapse-center"
            >
                <svg class="w-6 h-6 shrink-0 text-[#8db1af] group-hover:text-white group-aria-[current=page]:text-emerald-200 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $data['icon'] }}" />
                </svg>
                <span class="property-collapse-text truncate">{{ $itemName }}</span>
            </a>
        @endforeach

        <div class="mt-auto pt-4 border-t border-[#406866]/40">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-base font-medium text-[#d4e4e3] hover:bg-[#406866]/50 hover:text-white border-l-[3px] border-transparent transition-all text-left group property-collapse-center">
                    <svg class="w-6 h-6 shrink-0 text-[#8db1af] group-hover:text-red-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="property-collapse-text">Log out</span>
                </button>
            </form>
        </div>
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
                {{ Auth::check() && Auth::user()->name ? mb_substr(Auth::user()->name, 0, 1) : 'L' }}
            </div>
            <div class="property-collapse-text flex flex-col overflow-hidden min-w-0">
                <span class="text-base font-medium text-white truncate">{{ Auth::user()->name ?? 'Landlord' }}</span>
            </div>
        </a>
    </div>
</aside>
