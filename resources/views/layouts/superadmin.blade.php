@php
    $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name', 'Application');
    $displayName = strtolower($companyName) === 'laravel' ? 'Property Management System' : $companyName;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? ('Super Admin — '.$displayName) }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
        <x-swal-flash />

        <div class="min-h-screen flex">
            {{-- Sidebar --}}
            <aside class="w-72 hidden lg:flex flex-col border-r border-slate-200 bg-white">
                <div class="h-16 px-6 flex items-center justify-between border-b border-slate-200">
                    <div class="flex items-center gap-3">
                        <span class="h-10 w-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black">
                            SA
                        </span>
                        <div class="leading-tight">
                            <div class="text-sm font-black text-slate-900">{{ $displayName }}</div>
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">Super Admin</div>
                        </div>
                    </div>
                </div>

                <nav class="flex-1 px-4 py-5 space-y-2">
                    <a
                        href="{{ route('superadmin.users.index') }}"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition
                            {{ request()->routeIs('superadmin.users.*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Users
                    </a>

                    <a
                        href="{{ route('dashboard') }}"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 border border-transparent transition"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Back to dashboard
                    </a>
                </nav>

                <div class="border-t border-slate-200 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center font-black text-slate-700">
                            {{ auth()->check() && auth()->user()->name ? mb_substr(auth()->user()->name, 0, 1) : 'U' }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-slate-900 truncate">{{ auth()->user()->name ?? 'User' }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ auth()->user()->email ?? '' }}</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                            Log out
                        </button>
                    </form>
                </div>
            </aside>

            {{-- Main --}}
            <div class="flex-1 min-w-0 flex flex-col">
                {{-- Header --}}
                <header class="h-16 border-b border-slate-200 bg-white">
                    <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="lg:hidden">
                                <span class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-2 text-sm font-black text-white">SA</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-500 uppercase tracking-widest">Super Admin</div>
                                <div class="text-lg font-black text-slate-900 truncate">{{ $title ?? 'Console' }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if (request()->routeIs('superadmin.users.index'))
                                <a href="{{ route('superadmin.users.create') }}" class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                                    Add user
                                </a>
                            @endif
                        </div>
                    </div>
                </header>

                {{-- Content --}}
                <main class="flex-1 px-4 sm:px-6 lg:px-8 py-8">
                    @if (session('success'))
                        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @yield('content')
                </main>

                {{-- Footer --}}
                <footer class="border-t border-slate-200 bg-white">
                    <div class="px-4 sm:px-6 lg:px-8 py-4 text-xs text-slate-500 flex items-center justify-between">
                        <span>© {{ date('Y') }} {{ $displayName }}</span>
                        <span class="font-semibold text-slate-400">Super Admin Console</span>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>

