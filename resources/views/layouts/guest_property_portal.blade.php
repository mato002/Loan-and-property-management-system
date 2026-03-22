@php
    $isTenant = $portal === 'tenant';
    $pageTitle = $isTenant ? __('Tenant portal') : __('Landlord portal');
    $pageSubtitle = $isTenant
        ? __('Sign in to pay rent, report maintenance, and view your lease.')
        : __('Sign in to portfolio, earnings, and property reports.');
    $shellClass = $isTenant
        ? 'min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-teal-950 via-slate-900 to-slate-950'
        : 'min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-amber-950 via-slate-900 to-slate-950';
    $cardRing = $isTenant ? 'ring-1 ring-teal-500/25 shadow-teal-950/40' : 'ring-1 ring-amber-500/25 shadow-amber-950/40';
    $badgeClass = $isTenant
        ? 'inline-flex items-center rounded-full bg-teal-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-teal-200 ring-1 ring-teal-400/30'
        : 'inline-flex items-center rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-200 ring-1 ring-amber-400/30';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} — {{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-100">
        <x-swal-flash />
        <div class="{{ $shellClass }}">
            <div class="w-full sm:max-w-md px-6">
                <div class="text-center mb-8">
                    <p class="{{ $badgeClass }}">{{ $isTenant ? __('Resident access') : __('Owner access') }}</p>
                    <h1 class="mt-4 text-2xl font-semibold text-white tracking-tight">{{ $pageTitle }}</h1>
                    <p class="mt-2 text-sm text-slate-400 leading-relaxed">{{ $pageSubtitle }}</p>
                </div>

                <div class="rounded-2xl bg-slate-900/80 backdrop-blur-md border border-slate-700/80 {{ $cardRing }} shadow-xl px-6 py-6 sm:px-8">
                    {{ $slot }}
                </div>

                <div class="mt-8 text-center text-xs text-slate-500 space-y-2">
                    <p>
                        @if ($isTenant)
                            <a href="{{ route('property.landlord.login') }}" class="text-slate-400 hover:text-white underline underline-offset-2">{{ __('Landlord sign-in') }}</a>
                        @else
                            <a href="{{ route('property.tenant.login') }}" class="text-slate-400 hover:text-white underline underline-offset-2">{{ __('Tenant sign-in') }}</a>
                        @endif
                        <span class="text-slate-600 mx-2" aria-hidden="true">·</span>
                        <a href="{{ route('login') }}" class="text-slate-400 hover:text-white underline underline-offset-2">{{ __('Staff / loan system') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
