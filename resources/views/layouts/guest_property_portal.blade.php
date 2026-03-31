@php
    $isTenant = $portal === 'tenant';
    $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name', 'Application');
    $displayName = strtolower($companyName) === 'laravel' ? 'Property Management System' : $companyName;
    $heroImage = $isTenant
        ? 'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?auto=format&fit=crop&w=1800&q=80'
        : 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1800&q=80';
    $pageTitle = $isTenant ? __('Tenant portal') : __('Landlord portal');
    $pageSubtitle = $isTenant
        ? __('Sign in to pay rent, report maintenance, and view your lease.')
        : __('Sign in to portfolio, earnings, and property reports.');
    $badgeClass = $isTenant
        ? 'inline-flex items-center rounded-full bg-[#4d8d82]/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#3f7a70] ring-1 ring-[#4d8d82]/35'
        : 'inline-flex items-center rounded-full bg-[#6a9f97]/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#386f66] ring-1 ring-[#6a9f97]/35';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} — {{ $displayName }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-[#eef5f3]">
        <x-swal-flash />
        <div class="min-h-screen grid lg:grid-cols-[1.05fr_1fr]">
            <aside class="relative overflow-hidden flex min-h-[140px] lg:min-h-screen">
                <div class="absolute inset-0">
                    <img
                        src="{{ $heroImage }}"
                        alt=""
                        class="h-full w-full object-cover object-center"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
                <div class="absolute inset-0 bg-gradient-to-br from-[#6fa79f]/85 via-[#4d8d82]/88 to-[#2f4f4f]/90"></div>
                <div class="absolute -top-24 -left-20 h-72 w-72 rounded-full bg-white/15 blur-2xl"></div>
                <div class="absolute bottom-10 left-8 h-44 w-44 rounded-full bg-emerald-200/30 blur-2xl"></div>
                <div class="hidden lg:block absolute -right-8 top-1/2 -translate-y-1/2 h-[84%] w-24 rounded-l-[999px] bg-[#eef5f3]"></div>

                <div class="relative z-10 flex w-full flex-col justify-between px-5 py-4 lg:px-12 lg:py-12 text-white">
                    <div>
                        <p class="text-[11px] lg:text-sm font-semibold uppercase tracking-[0.18em] lg:tracking-[0.2em] text-white/75">{{ $displayName }}</p>
                        <h2 class="mt-2 lg:mt-6 max-w-md text-lg lg:text-4xl font-extrabold leading-tight">
                            {{ $isTenant ? __('Manage your tenancy with ease.') : __('Track portfolio performance with clarity.') }}
                        </h2>
                        <p class="mt-1.5 lg:mt-4 max-w-md text-xs lg:text-sm leading-relaxed text-white/85">{{ $pageSubtitle }}</p>
                    </div>

                    <div class="hidden lg:block rounded-3xl border border-white/25 bg-white/10 p-5 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('Portal Access') }}</p>
                        <p class="mt-2 text-lg font-semibold">{{ $isTenant ? __('Resident sign in') : __('Landlord sign in') }}</p>
                        <p class="mt-1 text-sm text-white/80">{{ __('Secure role-based access for your account area.') }}</p>
                    </div>
                </div>
            </aside>

            <div class="relative z-10 flex items-center justify-center px-6 py-10 sm:px-10 lg:px-14 xl:px-20 lg:-ml-8">
                <div class="w-full max-w-md rounded-[2rem] bg-white px-7 py-8 shadow-[0_20px_45px_rgba(47,79,79,0.14)] ring-1 ring-[#dbe8e4] sm:px-9 sm:py-10">
                    <div class="text-center mb-7">
                        <p class="{{ $badgeClass }}">{{ $isTenant ? __('Resident access') : __('Owner access') }}</p>
                        <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">{{ $pageTitle }}</h1>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $pageSubtitle }}</p>
                    </div>

                    {{ $slot }}

                    <div class="mt-8 text-center text-xs text-slate-500 space-y-2">
                        <p>
                            @if ($isTenant)
                                <a href="{{ route('property.landlord.login') }}" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">{{ __('Landlord sign-in') }}</a>
                            @else
                                <a href="{{ route('property.tenant.login') }}" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">{{ __('Tenant sign-in') }}</a>
                            @endif
                            <span class="text-slate-300 mx-2" aria-hidden="true">·</span>
                            <a href="{{ route('login') }}" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">{{ __('Staff / loan system') }}</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
