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
    <body class="h-screen overflow-hidden bg-[#e8ecf1] text-slate-900 antialiased selection:bg-emerald-200/80" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
        <x-swal-flash />

        <div class="h-screen flex overflow-hidden" x-data="{ saSidebarOpen: false, saProfileOpen: false }">
            {{-- Mobile sidebar overlay --}}
            <div
                x-show="saSidebarOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
                @click="saSidebarOpen = false"
            ></div>

            {{-- Mobile sidebar drawer --}}
            <aside
                x-show="saSidebarOpen"
                x-cloak
                class="fixed inset-y-0 left-0 z-50 w-72 lg:hidden flex flex-col border-r border-slate-200 bg-white shadow-xl"
                @keydown.escape.window="saSidebarOpen = false"
            >
                <div class="h-16 px-6 flex items-center justify-between border-b border-slate-200">
                    <div class="flex items-center gap-3">
                        <span class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black">
                            SA
                        </span>
                        <div class="leading-tight">
                            <div class="text-sm font-black text-slate-900">{{ $displayName }}</div>
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">Super Admin</div>
                        </div>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-700" @click="saSidebarOpen = false">
                        Close
                    </button>
                </div>

                <nav class="flex-1 px-4 py-5 space-y-2 overflow-y-auto">
                    <a
                        href="{{ route('superadmin.dashboard') }}"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition
                            {{ request()->routeIs('superadmin.dashboard') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" />
                        </svg>
                        Overview
                    </a>

                    <a
                        href="{{ route('superadmin.users.index') }}"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition
                            {{ request()->routeIs('superadmin.users.*') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Users
                    </a>

                    <a href="{{ route('superadmin.access_approvals') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.access_approvals') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" /></svg>
                        Access approvals
                    </a>
                    <a href="{{ route('superadmin.roles_permissions') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.roles_permissions') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" /></svg>
                        Roles & permissions
                    </a>
                    <a href="{{ route('superadmin.agent_workspaces') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.agent_workspaces') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7H4m16 0l-2 12H6L4 7m5 0V5a3 3 0 016 0v2" /></svg>
                        Agent workspaces
                    </a>
                    <a href="{{ route('superadmin.audit_trail') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.audit_trail') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Audit trail
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
            </aside>

            {{-- Sidebar --}}
            <aside class="w-72 hidden lg:flex flex-col border-r border-slate-200 bg-white sticky top-0 h-screen overflow-y-auto">
                <div class="h-16 px-6 flex items-center justify-between border-b border-slate-200">
                    <div class="flex items-center gap-3">
                        <span class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black">
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
                        href="{{ route('superadmin.dashboard') }}"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition
                            {{ request()->routeIs('superadmin.dashboard') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" />
                        </svg>
                        Overview
                    </a>

                    <a
                        href="{{ route('superadmin.users.index') }}"
                        class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition
                            {{ request()->routeIs('superadmin.users.*') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Users
                    </a>

                    <a href="{{ route('superadmin.access_approvals') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.access_approvals') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" /></svg>
                        Access approvals
                    </a>
                    <a href="{{ route('superadmin.roles_permissions') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.roles_permissions') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" /></svg>
                        Roles & permissions
                    </a>
                    <a href="{{ route('superadmin.agent_workspaces') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.agent_workspaces') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7H4m16 0l-2 12H6L4 7m5 0V5a3 3 0 016 0v2" /></svg>
                        Agent workspaces
                    </a>
                    <a href="{{ route('superadmin.audit_trail') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs('superadmin.audit_trail') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-700 hover:bg-slate-50 border border-transparent' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Audit trail
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
            <div class="flex-1 min-w-0 flex flex-col h-screen overflow-hidden">
                {{-- Header --}}
                <header class="h-16 border-b border-emerald-700/20 bg-gradient-to-r from-emerald-700 via-emerald-600 to-emerald-700 sticky top-0 z-20 shrink-0 text-white">
                    <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="lg:hidden">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-xl bg-white/15 ring-1 ring-white/30 px-3 py-2 text-sm font-black text-white"
                                    @click="saSidebarOpen = true"
                                >
                                    SA
                                </button>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-emerald-100 uppercase tracking-widest">Super Admin</div>
                                <div class="text-lg font-black text-white truncate">{{ $title ?? 'Console' }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if (request()->routeIs('superadmin.users.index'))
                                <a href="{{ route('superadmin.users.create') }}" class="inline-flex items-center rounded-xl bg-white text-emerald-700 px-4 py-2.5 text-sm font-bold hover:bg-emerald-50">
                                    Add user
                                </a>
                            @endif

                            <div class="relative">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl bg-white/15 ring-1 ring-white/30 px-3 py-2 text-sm font-semibold text-white hover:bg-white/20"
                                    @click="saProfileOpen = !saProfileOpen"
                                    aria-haspopup="menu"
                                    :aria-expanded="saProfileOpen ? 'true' : 'false'"
                                >
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-emerald-700 text-xs font-black">
                                        {{ auth()->check() && auth()->user()->name ? mb_substr(auth()->user()->name, 0, 1) : 'U' }}
                                    </span>
                                    <span class="hidden sm:inline max-w-[9rem] truncate">{{ auth()->user()->name ?? 'User' }}</span>
                                    <svg class="h-4 w-4 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div
                                    x-show="saProfileOpen"
                                    x-cloak
                                    @click.outside="saProfileOpen = false"
                                    @keydown.escape.window="saProfileOpen = false"
                                    class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white shadow-lg z-50 overflow-hidden"
                                    role="menu"
                                >
                                    <div class="px-4 py-3 border-b border-slate-100">
                                        <p class="text-sm font-semibold text-slate-900 truncate">{{ auth()->user()->name ?? 'User' }}</p>
                                        <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email ?? '' }}</p>
                                    </div>
                                    <a
                                        href="{{ route('profile.edit') }}"
                                        class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50"
                                        role="menuitem"
                                        @click="saProfileOpen = false"
                                    >
                                        My profile
                                    </a>
                                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50">
                                            Log out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                {{-- Content --}}
                <main class="flex-1 overflow-y-auto px-4 sm:px-6 lg:px-8 py-8 bg-[#f4f7fa]">
                    @if (session('success'))
                        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @yield('content')
                </main>

                {{-- Footer --}}
                <footer class="border-t border-slate-200 bg-white shrink-0">
                    <div class="px-4 sm:px-6 lg:px-8 py-4 text-xs text-slate-500 flex items-center justify-between">
                        <span>© {{ date('Y') }} {{ $displayName }}</span>
                        <span class="font-semibold text-slate-400">Super Admin Console</span>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>

