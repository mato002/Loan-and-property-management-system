<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden" data-pwa-context="portal">
    <head>
        @php
            $siteFaviconUrl = \App\Models\PropertyPortalSetting::getValue('site_favicon_url', '');
            $faviconHref = $siteFaviconUrl !== '' ? $siteFaviconUrl : asset('favicon.ico');
            $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Property Management System</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

        <!-- Scripts -->
        @php
            use App\Support\Property\PropertyNavMode;

            $propertyNavMode = ($propertyPortal ?? 'agent') === 'agent'
                ? PropertyNavMode::current()
                : PropertyNavMode::CLASSIC;
            $propertySidebarExpanded = match ($propertyNavMode) {
                PropertyNavMode::HYBRID => '16rem',
                default => '19rem',
            };
            $propertySidebarCollapsed = match ($propertyNavMode) {
                PropertyNavMode::HYBRID => '4.75rem',
                default => '5.5rem',
            };
        @endphp
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="icon" href="{{ $faviconVersioned }}" />
        <link rel="shortcut icon" href="{{ $faviconVersioned }}" />
        <link rel="apple-touch-icon" href="{{ $faviconVersioned }}" />
        <link rel="manifest" href="{{ route('pwa.manifest.portal') }}" />
        <meta name="theme-color" content="#059669" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-title" content="{{ \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name', 'Property Portal') }}" />
        <script src="{{ asset('js/pwa-install.js') }}?v=2" defer></script>
        
        <style>
            /* Portal shell — flush layout without body position:fixed (that + scroll-lock top offset clips the header) */
            html[data-pwa-context='portal'] {
                margin: 0 !important;
                padding: 0 !important;
                height: 100% !important;
                overflow: hidden !important;
                background: #047857;
            }
            html[data-pwa-context='portal'] body {
                margin: 0 !important;
                padding: 0 !important;
                position: relative !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                min-height: 100dvh;
                overflow: hidden !important;
                background: #e8ecf1 !important;
            }
            html[data-pwa-context='portal'] .property-print-root {
                display: flex !important;
                flex-direction: row !important;
                width: 100% !important;
                height: 100% !important;
                min-height: 100dvh;
                margin: 0 !important;
                padding: 0 !important;
            }
            html[data-pwa-context='portal'] #property-shell-header {
                display: flex;
                flex-direction: column;
                gap: 0;
                overflow: visible !important;
                flex-shrink: 0;
                margin: 0 !important;
                padding: 0 !important;
            }
            html[data-pwa-context='portal'] #property-shell-header > .property-topbar {
                margin: 0 !important;
            }
            @media (min-width: 1024px) {
                html[data-pwa-context='portal'] #property-mobile-search-overlay {
                    display: none !important;
                }
            }

            [x-cloak] { display: none !important; }
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #b8c2ce;
                border-radius: 10px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
            /* Firefox */
            .custom-scrollbar {
                scrollbar-width: auto;
                scrollbar-color: #b8c2ce transparent;
                scrollbar-gutter: stable;
            }
            @media print {
                @page { size: auto; margin: 12mm; }
                html, body {
                    background: #fff !important;
                    color: #000 !important;
                    height: auto !important;
                    overflow: visible !important;
                }
                .property-print-hide,
                .print-hide {
                    display: none !important;
                }
                .property-print-only {
                    display: block !important;
                }
                .property-print-root {
                    display: block !important;
                    width: 100% !important;
                    min-height: auto !important;
                }
                .property-print-main {
                    overflow: visible !important;
                    padding: 0 !important;
                    margin: 0 !important;
                }
                #property-main {
                    display: block !important;
                    width: 100% !important;
                }
                a {
                    text-decoration: none !important;
                    color: #000 !important;
                }
                .shadow-sm, .shadow, .shadow-lg, .rounded-2xl, .rounded-xl, .rounded-lg {
                    box-shadow: none !important;
                }
            }
            .property-print-only {
                display: none;
            }
            /* Reusable high-attention blocks for first-time user guidance */
            .property-attention-card {
                border-width: 2px;
                border-color: #bfdbfe;
                background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
            }
            .property-attention-title {
                font-size: 1.125rem;
                line-height: 1.5rem;
                font-weight: 700;
                color: #0f172a;
                letter-spacing: -0.01em;
            }
            .property-attention-hint {
                font-size: 0.8rem;
                line-height: 1.15rem;
                color: #475569;
            }
            /* Property module table grid lines (global) */
            #property-main table {
                border-collapse: collapse;
            }
            #property-main table th,
            #property-main table td {
                border: 1px solid #cbd5e1;
            }
            .dark #property-main table th,
            .dark #property-main table td {
                border-color: #334155;
            }
            /* Property workspace shell — fixed sidebar/header, scrollable workspace only */
            .property-print-root {
                isolation: isolate;
                height: 100%;
                min-height: 0;
            }
            #property-shell-sidebar {
                position: relative;
                z-index: 40;
            }
            #property-shell-header {
                position: relative;
                z-index: 50;
            }
            #property-workspace-main {
                position: relative;
                z-index: 0;
                isolation: isolate;
            }
            #property-global-nav-progress {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                z-index: 45;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.15s ease;
            }
            #property-global-nav-progress[data-active] {
                opacity: 1;
            }
            #property-global-nav-progress > span {
                display: block;
                height: 100%;
                width: 35%;
                border-radius: 9999px;
                background: linear-gradient(90deg, #059669 0%, #6ee7b7 45%, #059669 90%);
                background-size: 200% 100%;
                animation: property-frame-progress 0.9s ease-in-out infinite;
            }
            #property-workspace-loading {
                position: absolute;
                inset: 0;
                z-index: 25;
                display: none;
                padding: 0.75rem;
                background: rgb(232 236 241 / 0.72);
                backdrop-filter: blur(1px);
            }
            #property-workspace-loading[data-active] {
                display: block;
                pointer-events: none;
            }
            #property-workspace-loading .property-workspace-loading {
                pointer-events: none;
            }
            #property-workspace-error {
                display: none;
            }
            #property-workspace-error[data-active] {
                display: flex;
                align-items: flex-start;
                justify-content: center;
                position: absolute;
                inset: 0;
                z-index: 50;
                padding: 1rem 1.25rem;
                background: rgb(232 236 241 / 0.92);
                backdrop-filter: blur(2px);
                pointer-events: auto;
            }
            #property-workspace-main[data-workspace-error-active] turbo-frame#property-main {
                visibility: hidden;
            }
            /* Turbo frame loading — progress bar + stable containment */
            turbo-frame#property-main {
                display: block;
                width: 100%;
                max-width: 100%;
                min-height: 12rem;
                position: relative;
                overflow: visible;
            }
            turbo-frame#property-main[data-property-loading] {
                pointer-events: none;
            }
            turbo-frame#property-main[data-property-loading]::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                z-index: 30;
                border-radius: 9999px;
                background: linear-gradient(90deg, #059669 0%, #6ee7b7 45%, #059669 90%);
                background-size: 200% 100%;
                animation: property-frame-progress 0.9s ease-in-out infinite;
            }
            turbo-frame#property-main > * {
                max-width: 100%;
            }
            @keyframes property-frame-progress {
                0% { background-position: 100% 0; }
                100% { background-position: -100% 0; }
            }
            .property-skeleton-line,
            .property-skeleton-block {
                background: rgb(226 232 240 / 0.92);
                border-radius: 0.375rem;
            }
        </style>
    </head>
    <body
        class="font-sans antialiased h-full min-h-0 overflow-hidden text-slate-900 bg-[#e8ecf1] selection:bg-emerald-200/80 @if(($propertyPortal ?? 'agent') === 'tenant') selection:bg-teal-200 @endif"
        data-property-nav-mode="{{ $propertyNavMode }}"
        x-data="{
            sidebarOpen: false,
            sidebarDesktopOpen: true,
            init() {
                const portal = @js($propertyPortal ?? 'agent');
                const saved = window.localStorage.getItem(`property.sidebar.desktop.open.${portal}`);
                if (saved !== null) {
                    this.sidebarDesktopOpen = saved === '1';
                }
            },
            toggleDesktopSidebar() {
                this.sidebarDesktopOpen = !this.sidebarDesktopOpen;
                const portal = @js($propertyPortal ?? 'agent');
                window.localStorage.setItem(`property.sidebar.desktop.open.${portal}`, this.sidebarDesktopOpen ? '1' : '0');
            },
            expandDesktopSidebar() {
                if (!this.sidebarDesktopOpen) {
                    this.sidebarDesktopOpen = true;
                    const portal = @js($propertyPortal ?? 'agent');
                    window.localStorage.setItem(`property.sidebar.desktop.open.${portal}`, '1');
                }
            }
        }"
        @property-sidebar-expand.window="expandDesktopSidebar()"
        x-effect="(() => { document.documentElement.classList.toggle('property-sidebar-collapsed', !sidebarDesktopOpen && window.matchMedia('(min-width: 1024px)').matches); document.documentElement.classList.toggle('overflow-hidden', sidebarOpen && window.innerWidth < 1024); })()"
    >
        <div class="flex h-full min-h-0 w-full property-print-root">
            
            <!-- Property Module Dedicated Sidebar (persists across Turbo navigations) -->
            <div
                id="property-shell-sidebar"
                data-turbo-permanent
                class="property-print-hide h-full w-0 min-w-0 max-w-0 overflow-hidden lg:flex-shrink-0 lg:w-[{{ $propertySidebarExpanded }}] lg:max-w-[{{ $propertySidebarExpanded }}] lg:min-w-[{{ $propertySidebarExpanded }}] transition-all duration-300"
                :class="sidebarDesktopOpen ? 'lg:w-[{{ $propertySidebarExpanded }}] lg:max-w-[{{ $propertySidebarExpanded }}] lg:min-w-[{{ $propertySidebarExpanded }}] lg:opacity-100' : 'lg:w-[{{ $propertySidebarCollapsed }}] lg:max-w-[{{ $propertySidebarCollapsed }}] lg:min-w-[{{ $propertySidebarCollapsed }}] lg:opacity-100'"
                :style="window.matchMedia('(min-width: 1024px)').matches
                    ? (sidebarDesktopOpen
                        ? 'width: {{ $propertySidebarExpanded }}; min-width: {{ $propertySidebarExpanded }}; max-width: {{ $propertySidebarExpanded }};'
                        : 'width: {{ $propertySidebarCollapsed }}; min-width: {{ $propertySidebarCollapsed }}; max-width: {{ $propertySidebarCollapsed }};')
                    : 'width: 0; min-width: 0; max-width: 0;'"
            >
                @include('layouts.property_sidebar')
            </div>

            <!-- Main view container (Header, Content, Footer) -->
            <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden">
                
                <!-- Dedicated Header (persists across Turbo navigations) -->
                <div id="property-shell-header" data-turbo-permanent class="property-print-hide shrink-0 overflow-visible">
                    @include('layouts.property_header')
                </div>

                <!-- Scrollable Content Area (Header/Footer remain constant) -->
                <main
                    id="property-workspace-main"
                    class="property-print-main relative z-0 flex-1 min-h-0 overflow-x-hidden overflow-y-auto w-full custom-scrollbar"
                    :class="{ 'overflow-hidden': sidebarOpen && window.innerWidth < 1024 }"
                >
                    <div id="property-global-nav-progress" aria-hidden="true"><span></span></div>
                    <div id="property-workspace-loading" aria-hidden="true">
                        <x-property.workspace-loading />
                    </div>
                    <div id="property-workspace-error" class="absolute inset-0 z-50 pointer-events-none p-4 sm:p-6" aria-live="polite"></div>
                    <div class="p-3 sm:p-4 lg:p-8 w-full max-w-full min-w-0 property-mobile-safe-bottom">
                        <turbo-frame id="property-main" data-turbo-action="advance" data-turbo-cache="false">
                            <div id="property-main-route" data-route-name="{{ Route::currentRouteName() ?? '' }}" data-page-title="{{ trim((string) ($header ?? '')) }}" hidden></div>
                            <x-property.next-steps-modal />
                            <x-swal-flash />
                            @php
                                use App\Support\Property\PropertyUiVersion;
                                use App\Support\Property\PropertyWorkspaceTabs;

                                $shellRouteName = Route::currentRouteName();
                            @endphp
                            @if (! PropertyUiVersion::isV2() && PropertyWorkspaceTabs::shouldShow($shellRouteName))
                                <x-property.workspace-tabs :workspace="PropertyWorkspaceTabs::resolveWorkspaceKey($shellRouteName)" />
                            @endif
                            {{ $slot }}
                        </turbo-frame>
                    </div>
                </main>

                <!-- Dedicated Footer (persists across Turbo navigations) -->
                <div id="property-shell-footer" data-turbo-permanent class="property-print-hide">
                    @include('layouts.property_footer')
                </div>

            </div>
        </div>

        @if (($propertyPortal ?? 'agent') === 'agent')
            <x-property.form-modal-host />
            <a
                href="{{ route('property.advisor') }}"
                data-turbo-frame="property-main"
                class="property-print-hide fixed z-30 flex items-center justify-center gap-2 rounded-full bg-violet-600 text-sm font-semibold text-white shadow-lg shadow-violet-900/40 ring-2 ring-white/20 hover:bg-violet-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 transition-colors bottom-[max(4.5rem,calc(env(safe-area-inset-bottom)+3.5rem))] right-3 h-11 w-11 sm:bottom-5 sm:right-5 sm:h-auto sm:w-auto sm:px-4 sm:py-3"
                title="AI advisor"
                aria-label="Ask AI advisor"
            >
                <i class="fa-solid fa-robot text-base sm:text-lg" aria-hidden="true"></i>
                <span class="hidden sm:inline">Ask</span>
            </a>
        @endif

        {{-- Phase 2C: property-auto-filter.js (search-only debounce + Apply for other controls) --}}
        @stack('scripts')

        <template id="property-frame-skeleton-template">
            <x-property.frame-skeleton />
        </template>

        <x-public.pwa-install-prompt context="portal" position="left" />
    </body>
</html>
