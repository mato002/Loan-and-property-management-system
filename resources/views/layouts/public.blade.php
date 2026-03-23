<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@isset($publicPageTitle){{ $publicPageTitle }} | PrimeEstate @else Property Management System - Find Your Next Home @endisset</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-900 bg-white" x-data="{ mobileMenuOpen: false, scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 20)">
    <x-swal-flash />

    <!-- Sticky Header -->
    <header :class="{'bg-white shadow-md': scrolled, 'bg-white/90 backdrop-blur-md border-b border-gray-100': !scrolled}" class="fixed top-0 inset-x-0 z-50 transition-all duration-300">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="{{ route('public.home') }}" class="text-2xl font-black tracking-tighter text-indigo-600 flex items-center gap-2">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        PrimeEstate
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <a href="{{ route('public.home') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">Home</a>
                    <a href="{{ route('public.properties') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">Properties</a>
                    <a href="{{ route('public.about') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">About Us</a>
                    <a href="{{ route('public.contact') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">Contact</a>
                </nav>

                <!-- Actions -->
                <div class="hidden md:flex items-center space-x-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-indigo-600 font-bold px-3 py-2">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-600 font-bold px-3 py-2 transition-colors">Log In</a>
                        <a href="{{ route('public.signup') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-full shadow-lg shadow-indigo-600/30 transition-all hover:-translate-y-0.5">Sign Up</a>
                    @endauth
                </div>

                <!-- Mobile menu button -->
                <div class="flex items-center md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-500 hover:text-gray-700 focus:outline-none p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" x-show="!mobileMenuOpen"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" x-show="mobileMenuOpen" x-cloak/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" x-transition.opacity class="md:hidden bg-white border-b border-gray-100 shadow-xl absolute w-full" x-cloak>
            <div class="px-4 pt-2 pb-6 space-y-2">
                <a href="{{ route('public.home') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">Home</a>
                <a href="{{ route('public.properties') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">Properties</a>
                <a href="{{ route('public.about') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">About Us</a>
                <a href="{{ route('public.contact') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">Contact</a>
                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col space-y-3 px-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="w-full text-center bg-gray-100 font-bold text-gray-900 px-5 py-3 rounded-xl">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="w-full text-center border border-gray-300 font-bold text-gray-700 px-5 py-3 rounded-xl">Log In</a>
                        <a href="{{ route('public.signup') }}" class="w-full text-center bg-indigo-600 text-white font-bold px-5 py-3 rounded-xl shadow-md">Sign Up</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="min-h-screen pt-20">
        {{ $slot }}
    </main>

    <section class="bg-gradient-to-r from-indigo-50 via-white to-emerald-50 border-y border-indigo-100/70">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 py-10">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-indigo-100 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs font-extrabold uppercase tracking-wider text-indigo-600">Live Availability</p>
                    <p class="mt-2 text-2xl font-black text-gray-900">Updated Every Hour</p>
                    <p class="mt-1 text-sm text-gray-600">Property status and vacancy signals refresh frequently so renters see what is really available.</p>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs font-extrabold uppercase tracking-wider text-indigo-600">Verified Listings</p>
                    <p class="mt-2 text-2xl font-black text-gray-900">Agent-Approved Photos</p>
                    <p class="mt-1 text-sm text-gray-600">Each listing is reviewed by our team to reduce fake inventory and increase trust at first glance.</p>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs font-extrabold uppercase tracking-wider text-indigo-600">Fast Onboarding</p>
                    <p class="mt-2 text-2xl font-black text-gray-900">3-Step Digital Signup</p>
                    <p class="mt-1 text-sm text-gray-600">Applicants can discover, apply, and receive follow-up from one connected digital workflow.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 pt-16 pb-8 border-t border-gray-800 mt-20">
        <div class="w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 lg:gap-8">
                <!-- Branding -->
                <div class="col-span-1 md:col-span-1">
                    <a href="{{ route('public.home') }}" class="text-2xl font-black tracking-tighter text-white flex items-center gap-2 mb-4">
                        <svg class="w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        PrimeEstate
                    </a>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Redefining property management with transparent, modern solutions for both landlords and highly-valued tenants.
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Properties</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li><a href="{{ route('public.properties') }}" class="hover:text-indigo-400 transition-colors">Residential</a></li>
                        <li><a href="{{ route('public.properties') }}" class="hover:text-indigo-400 transition-colors">Commercial</a></li>
                        <li><a href="{{ route('public.properties') }}" class="hover:text-indigo-400 transition-colors">New Listings</a></li>
                    </ul>
                </div>

                <!-- Company -->
                <div>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Company</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li><a href="{{ route('public.about') }}" class="hover:text-indigo-400 transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-indigo-400 transition-colors">Careers</a></li>
                        <li><a href="{{ route('public.contact') }}" class="hover:text-indigo-400 transition-colors">Contact</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Connect</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            123 Estate Blvd, Suite 400<br>Metropolis, NY 10012
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            hello@primeestate.com
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mt-12 pt-8 border-t border-gray-800 text-sm text-gray-500 flex flex-col md:flex-row justify-between items-center">
                <p>&copy; {{ date('Y') }} PrimeEstate Management. All rights reserved.</p>
                <div class="mt-4 md:mt-0 space-x-4">
                    <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
