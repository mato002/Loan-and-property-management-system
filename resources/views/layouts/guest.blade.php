<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name');
            $siteFaviconUrl = \App\Models\PropertyPortalSetting::getValue('site_favicon_url', '');
            $faviconHref = $siteFaviconUrl !== '' ? $siteFaviconUrl : asset('favicon.ico');
            $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
            $resolvedTitle = str_replace(config('app.name'), $companyName, $title);
            $heroImage = 'https://images.unsplash.com/photo-1460317442991-0ec209397118?auto=format&fit=crop&w=1800&q=80';
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $resolvedTitle }}</title>
        <link rel="icon" href="{{ $faviconVersioned }}" />
        <link rel="shortcut icon" href="{{ $faviconVersioned }}" />
        <link rel="apple-touch-icon" href="{{ $faviconVersioned }}" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="min-h-screen antialiased bg-[#eef5f3] text-slate-900" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
        <x-swal-flash />
        <div class="min-h-screen grid lg:grid-cols-[1.05fr_1fr]">
            {{-- Visual column --}}
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
                        <p class="text-[11px] lg:text-sm font-semibold uppercase tracking-[0.18em] lg:tracking-[0.2em] text-white/75">{{ $companyName }}</p>
                        <h2 class="mt-2 lg:mt-6 max-w-md text-lg lg:text-4xl font-extrabold leading-tight">
                            {{ __('Welcome back to your operations workspace.') }}
                        </h2>
                        <p class="mt-1.5 lg:mt-4 max-w-md text-xs lg:text-sm leading-relaxed text-white/85">
                            {{ __('Track properties, finances, tenants, and reports from one secure portal with role-based access control.') }}
                        </p>
                    </div>

                    <div class="hidden lg:block rounded-3xl border border-white/25 bg-white/10 p-5 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('Secure Access') }}</p>
                        <p class="mt-2 text-lg font-semibold">{{ __('Sign in to continue') }}</p>
                        <p class="mt-1 text-sm text-white/80">{{ __('Use your staff credentials to access property and loan modules.') }}</p>
                    </div>
                </div>
            </aside>

            {{-- Form column --}}
            <div class="relative z-10 flex items-center justify-center px-6 py-10 sm:px-10 lg:px-14 xl:px-20 lg:-ml-8">
                <div class="w-full max-w-md rounded-[2rem] bg-white px-7 py-8 shadow-[0_20px_45px_rgba(47,79,79,0.14)] ring-1 ring-[#dbe8e4] sm:px-9 sm:py-10">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
