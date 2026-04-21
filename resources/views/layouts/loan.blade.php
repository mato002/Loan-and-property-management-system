<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @php
            $appDisplayName = \App\Models\LoanSystemSetting::getValue('app_display_name', config('app.name', 'Loan Management System'));
            $faviconUrl = \App\Models\LoanSystemSetting::getValue('favicon_url', '');
            $faviconRaw = trim((string) $faviconUrl);
            $faviconHref = match (true) {
                $faviconRaw === '' => asset('favicon.ico'),
                \Illuminate\Support\Str::startsWith($faviconRaw, ['http://', 'https://', '//']) => $faviconRaw,
                default => asset(ltrim($faviconRaw, '/')),
            };
            $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $appDisplayName }}</title>
        <link rel="icon" href="{{ $faviconVersioned }}">
        <link rel="shortcut icon" href="{{ $faviconVersioned }}">
        <link rel="apple-touch-icon" href="{{ $faviconVersioned }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            [x-cloak] { display: none !important; }
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
            }
            .custom-scrollbar::-webkit-scrollbar-track {
                background: transparent;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #475569;
                border-radius: 10px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }
            /* Firefox + stable gutter */
            .custom-scrollbar {
                scrollbar-width: auto;
                scrollbar-color: #475569 transparent;
                scrollbar-gutter: stable;
            }
        </style>
    </head>
    <body
        class="font-sans antialiased h-screen overflow-hidden text-slate-900 dark:text-slate-100 selection:bg-indigo-500/30"
        x-data="{
            sidebarOpen: false,
            sidebarDesktopOpen: true,
            init() {
                const saved = window.localStorage.getItem('loan.sidebar.desktop.open');
                if (saved !== null) {
                    this.sidebarDesktopOpen = saved === '1';
                }
            },
            toggleDesktopSidebar() {
                this.sidebarDesktopOpen = !this.sidebarDesktopOpen;
                window.localStorage.setItem('loan.sidebar.desktop.open', this.sidebarDesktopOpen ? '1' : '0');
            }
        }"
    >
        <x-swal-flash />
        <div class="h-full flex bg-slate-50 dark:bg-slate-900">
            
            <!-- Loan Module Dedicated Sidebar -->
            @include('layouts.loan_sidebar')

            <!-- Main view container (Header, Content, Footer) -->
            <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden bg-[#f4f7fa]">
                
                <!-- Dedicated Clean Topbar -->
                @include('layouts.loan_topbar')

                <!-- Scrollable Content Area (Topbar/Footer remain constant) -->
                <main class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto w-full custom-scrollbar overscroll-contain">
                    <div class="p-4 sm:p-6 lg:p-8">
                        {{ $slot }}
                    </div>
                </main>

                <!-- Dedicated Footer (constant) -->
                @include('layouts.loan_footer')

            </div>
        </div>
    </body>
</html>
