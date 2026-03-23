<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@isset($publicPageTitle){{ $publicPageTitle }} | PrimeEstate @else Property Management System - Find Your Next Home @endisset</title>
    <meta name="description" content="PrimeEstate helps you discover verified rental properties, schedule site visits, and manage applications online with trusted property professionals.">
    <meta name="keywords" content="property management, rentals, apartments, houses, real estate, verified listings">
    <meta property="og:title" content="@isset($publicPageTitle){{ $publicPageTitle }} | PrimeEstate @else PrimeEstate - Verified Property Listings @endisset">
    <meta property="og:description" content="Browse verified listings, connect with agents, and apply online with PrimeEstate.">
    <meta property="og:type" content="website">
    <meta name="robots" content="index,follow">
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
            <div class="h-20 flex items-center justify-between md:grid md:grid-cols-[1fr_auto_1fr] md:items-center md:gap-8">
                <!-- Logo -->
                <div class="flex items-center md:justify-self-start">
                    <a href="{{ route('public.home') }}" class="text-2xl font-black tracking-tighter text-indigo-600 flex items-center gap-2">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        PrimeEstate
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex md:justify-self-center gap-10">
                    <a href="{{ route('public.home') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">Home</a>
                    <a href="{{ route('public.properties') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">Properties</a>
                    <a href="{{ route('public.about') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">About Us</a>
                    <a href="{{ route('public.contact') }}" class="text-base font-semibold text-gray-600 hover:text-indigo-600 transition-colors">Contact</a>
                </nav>

                <!-- Mobile menu button -->
                <div class="flex items-center md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-500 hover:text-gray-700 focus:outline-none p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" x-show="!mobileMenuOpen"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" x-show="mobileMenuOpen" x-cloak/>
                        </svg>
                    </button>
                </div>

                <div class="hidden md:block md:justify-self-end"></div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" x-transition.opacity class="md:hidden bg-white border-b border-gray-100 shadow-xl absolute w-full" x-cloak>
            <div class="px-4 pt-2 pb-6 space-y-2">
                <a href="{{ route('public.home') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">Home</a>
                <a href="{{ route('public.properties') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">Properties</a>
                <a href="{{ route('public.about') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">About Us</a>
                <a href="{{ route('public.contact') }}" class="block px-3 py-3 rounded-md text-base font-bold text-gray-900 hover:bg-gray-50 hover:text-indigo-600">Contact</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="min-h-screen pt-20">
        {{ $slot }}
    </main>

    <div class="fixed z-50 right-4 bottom-4 flex flex-col gap-3">
        <a href="https://wa.me/18005550199" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white shadow-lg shadow-emerald-500/30" aria-label="Chat on WhatsApp">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.52 3.48A11.86 11.86 0 0 0 12.06 0C5.5 0 .16 5.34.16 11.9c0 2.1.55 4.14 1.6 5.95L0 24l6.32-1.66a11.84 11.84 0 0 0 5.73 1.47h.01c6.56 0 11.9-5.34 11.9-11.9 0-3.18-1.24-6.16-3.44-8.43ZM12.06 21.8h-.01a9.8 9.8 0 0 1-4.99-1.37l-.36-.21-3.75.98 1-3.65-.24-.37a9.82 9.82 0 0 1-1.52-5.28c0-5.4 4.39-9.8 9.8-9.8 2.62 0 5.08 1.02 6.92 2.87a9.7 9.7 0 0 1 2.87 6.93c0 5.4-4.4 9.8-9.81 9.8Zm5.38-7.36c-.3-.15-1.77-.88-2.05-.98-.27-.1-.46-.15-.66.15-.2.3-.76.98-.93 1.18-.17.2-.35.23-.65.08-.3-.15-1.26-.46-2.4-1.47a9 9 0 0 1-1.67-2.07c-.18-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.66-1.58-.9-2.17-.24-.57-.49-.5-.66-.5h-.57c-.2 0-.53.08-.8.38-.28.3-1.05 1.03-1.05 2.5 0 1.48 1.08 2.9 1.23 3.1.15.2 2.12 3.23 5.13 4.53.72.31 1.29.5 1.73.64.73.23 1.4.2 1.92.12.58-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.08-.13-.27-.2-.57-.35Z"/></svg>
        </a>
        <a href="tel:+18005550199" class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-600/30" aria-label="Call agent">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        </a>
    </div>

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
                        <li><a href="{{ route('public.contact') }}" class="hover:text-indigo-400 transition-colors">Book Site Visit</a></li>
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
                        <li class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            +1 (800) 555-0199
                        </li>
                        <li class="text-xs text-gray-500">Reg No: PRE-2026-00412</li>
                    </ul>
                </div>
            </div>
            <div class="mt-12 pt-8 border-t border-gray-800 text-sm text-gray-500 flex flex-col md:flex-row justify-between items-center">
                <p>&copy; {{ date('Y') }} PrimeEstate Management. All rights reserved.</p>
                <div class="mt-4 md:mt-0 space-x-4">
                    <a href="{{ route('public.privacy') }}" class="hover:text-white transition-colors">Privacy Policy</a>
                    <a href="{{ route('public.terms') }}" class="hover:text-white transition-colors">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
