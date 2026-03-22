<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Property Management System</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

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
                background: #b8c2ce;
                border-radius: 10px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
        </style>
    </head>
    <body class="font-sans antialiased h-full overflow-hidden text-slate-900 bg-[#e8ecf1] selection:bg-emerald-200/80 @if(($propertyPortal ?? 'agent') === 'tenant') selection:bg-teal-200 @endif" x-data="{ sidebarOpen: false }">
        <div class="h-full flex">
            
            <!-- Property Module Dedicated Sidebar -->
            @include('layouts.property_sidebar')

            <!-- Main view container (Header, Content, Footer) -->
            <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
                
                <!-- Dedicated Header -->
                @include('layouts.property_header')

                <!-- Main Content Area with scrollbar -->
                <main class="relative z-0 flex-1 overflow-x-hidden overflow-y-auto w-full custom-scrollbar flex flex-col">
                    <div @class([
                        'p-4 sm:p-6 lg:p-8 flex-1 w-full',
                        'max-w-lg mx-auto' => ($propertyPortal ?? 'agent') === 'tenant',
                    ])>
                        <turbo-frame id="property-main">
                            <div id="property-main-route" data-route-name="{{ Route::currentRouteName() ?? '' }}" hidden></div>
                            <x-swal-flash />
                            {{ $slot }}
                        </turbo-frame>
                    </div>
                    
                    <!-- Dedicated Footer -->
                    @include('layouts.property_footer')
                </main>

            </div>
        </div>

        @if (($propertyPortal ?? 'agent') === 'agent')
            <a
                href="{{ route('property.advisor') }}"
                data-turbo-frame="property-main"
                class="fixed bottom-5 right-5 z-30 flex items-center gap-2 rounded-full bg-violet-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-900/40 ring-2 ring-white/20 hover:bg-violet-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 transition-colors"
                title="AI advisor"
            >
                <i class="fa-solid fa-robot text-lg" aria-hidden="true"></i>
                <span class="hidden sm:inline">Ask</span>
            </a>
        @endif

        @stack('scripts')
    </body>
</html>
