<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Loan Management System</title>

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
        </style>
    </head>
    <body class="font-sans antialiased h-full overflow-hidden text-slate-900 dark:text-slate-100 selection:bg-indigo-500/30" x-data="{ sidebarOpen: false }">
        <x-swal-flash />
        <div class="h-full flex bg-slate-50 dark:bg-slate-900">
            
            <!-- Loan Module Dedicated Sidebar -->
            @include('layouts.loan_sidebar')

            <!-- Main view container (Header, Content, Footer) -->
            <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-[#f4f7fa]">
                
                <!-- Dedicated Clean Topbar -->
                @include('layouts.loan_topbar')

                <!-- Main Content Area with scrollbar -->
                <main class="flex-1 overflow-x-hidden overflow-y-auto w-full custom-scrollbar flex flex-col">
                    <div class="p-4 sm:p-6 lg:p-8 flex-1">
                        {{ $slot }}
                    </div>
                    
                    <!-- Dedicated Footer -->
                    @include('layouts.loan_footer')
                </main>

            </div>
        </div>
    </body>
</html>
