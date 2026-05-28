@props([
    'companyName' => '',
    'companyLogoUrl' => '',
])

<header
    :class="scrolled ? 'bg-white/95 shadow-md backdrop-blur-md' : 'bg-white/90 backdrop-blur-md border-b border-gray-100/80'"
    class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
>
    <div class="public-container">
        <div class="h-14 sm:h-16 flex items-center justify-between gap-4">
            <a href="{{ route('public.home') }}" class="flex items-center gap-2.5 min-w-0 shrink">
                @if ($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }} logo" class="h-9 sm:h-10 w-auto max-w-[8rem] object-contain">
                @else
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </span>
                @endif
                <span class="hidden sm:block text-base font-black tracking-tight text-gray-900 truncate">{{ $companyName }}</span>
            </a>

            <nav class="hidden lg:flex items-center gap-8">
                @foreach ([
                    ['route' => 'public.home', 'label' => 'Home'],
                    ['route' => 'public.properties', 'label' => 'Properties'],
                    ['route' => 'public.about', 'label' => 'For Landlords'],
                    ['route' => 'public.contact', 'label' => 'Contact'],
                ] as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        @class([
                            'text-sm font-bold transition-colors',
                            request()->routeIs($link['route']) ? 'text-emerald-600' : 'text-gray-600 hover:text-emerald-600',
                        ])
                    >{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="hidden md:flex items-center gap-2 shrink-0">
                <a href="{{ route('public.properties') }}" class="public-btn public-btn-secondary !min-h-[2.5rem] !px-4 !text-sm">Browse homes</a>
                <a href="{{ route('public.apply') }}" class="public-btn public-btn-primary !min-h-[2.5rem] !px-4 !text-sm">Apply now</a>
            </div>

            <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100" aria-label="Toggle menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" x-show="!mobileMenuOpen"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" x-show="mobileMenuOpen" x-cloak/>
                </svg>
            </button>
        </div>
    </div>

    <div x-show="mobileMenuOpen" x-transition.opacity class="lg:hidden bg-white border-b border-gray-100 shadow-xl absolute w-full" x-cloak>
        <div class="public-container py-3 space-y-1">
            @foreach ([
                ['route' => 'public.home', 'label' => 'Home'],
                ['route' => 'public.properties', 'label' => 'Properties'],
                ['route' => 'public.about', 'label' => 'For Landlords'],
                ['route' => 'public.contact', 'label' => 'Contact'],
                ['route' => 'public.apply', 'label' => 'Apply for rental'],
            ] as $link)
                <a href="{{ route($link['route']) }}" class="block px-3 py-2.5 rounded-lg text-sm font-bold text-gray-900 hover:bg-emerald-50 hover:text-emerald-700">{{ $link['label'] }}</a>
            @endforeach
            <div class="pt-2 grid grid-cols-2 gap-2">
                <a href="{{ route('property.tenant.login') }}" class="public-btn public-btn-secondary !text-xs !min-h-[2.5rem]">Tenant login</a>
                <a href="{{ route('property.landlord.login') }}" class="public-btn public-btn-primary !text-xs !min-h-[2.5rem]">Landlord login</a>
            </div>
        </div>
    </div>
</header>
