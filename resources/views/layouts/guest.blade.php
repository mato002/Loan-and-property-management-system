<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-dvh overflow-hidden">
    <head>
        @php
            $companyName = \App\Support\Property\PropertyWorkspaceBranding::forGuestPage('company_name') ?: config('app.name');
            $companyLogoUrl = trim((string) (\App\Support\Property\PropertyWorkspaceBranding::forGuestPage('company_logo_url', '') ?? ''));
            $siteFaviconUrl = \App\Support\Property\PropertyWorkspaceBranding::forGuestPage('site_favicon_url', '') ?? '';
            $faviconHref = $siteFaviconUrl !== '' ? $siteFaviconUrl : asset('favicon.ico');
            $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
            $resolvedTitle = str_replace(config('app.name'), $companyName, $title);
            $heroImage = 'https://images.unsplash.com/photo-1460317442991-0ec209397118?auto=format&fit=crop&w=1800&q=80';
            $heroTitle = $heroTitle ?? __('Welcome back to your operations workspace.');
            $heroSubtitle = $heroSubtitle ?? __('Track properties, finances, tenants, and reports from one secure portal with role-based access control.');
            $heroCardLabel = $heroCardLabel ?? __('Secure Access');
            $heroCardTitle = $heroCardTitle ?? __('Sign in to continue');
            $heroCardBody = $heroCardBody ?? __('Use your staff credentials to access property and loan modules.');
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
    <body class="h-dvh overflow-hidden antialiased bg-[#eef5f3] text-slate-900" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
        <x-swal-flash />
        <div class="relative h-full overflow-hidden">
            <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
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
            </div>

            <div class="relative z-10 h-full grid lg:grid-cols-[1.05fr_1fr] lg:overflow-hidden">
                {{-- Visual column --}}
                <aside class="relative overflow-hidden hidden lg:flex min-h-0 h-full">
                <div class="relative z-10 flex w-full flex-col justify-between px-5 py-4 lg:px-10 lg:py-8 text-white">
                    <div>
                        <p class="text-[11px] lg:text-sm font-semibold uppercase tracking-[0.18em] lg:tracking-[0.2em] text-white/75">{{ $companyName }}</p>
                        <h2 class="mt-2 lg:mt-4 max-w-md text-lg lg:text-3xl font-extrabold leading-tight">
                            {{ $heroTitle }}
                        </h2>
                        <p class="mt-1.5 lg:mt-4 max-w-md text-xs lg:text-sm leading-relaxed text-white/85">
                            {{ $heroSubtitle }}
                        </p>
                    </div>

                    <div class="hidden lg:block rounded-2xl border border-white/25 bg-white/10 p-4 backdrop-blur-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ $heroCardLabel }}</p>
                        <p class="mt-2 text-lg font-semibold">{{ $heroCardTitle }}</p>
                        <p class="mt-1 text-sm text-white/80">{{ $heroCardBody }}</p>
                    </div>
                </div>
                </aside>

                {{-- Form column --}}
                <div class="relative z-10 flex h-full min-h-0 items-center justify-center overflow-y-auto px-4 py-4 sm:px-8 lg:px-10 xl:px-14 lg:-ml-8">
                    <div class="my-auto w-full max-w-md overflow-hidden rounded-[1.75rem] bg-white shadow-[0_20px_45px_rgba(47,79,79,0.14)] ring-1 ring-[#dbe8e4]">
                        <div class="h-16 sm:h-[4.5rem] w-full overflow-hidden rounded-t-[1.75rem] border-b border-[#dbe8e4] bg-gradient-to-r from-[#f4faf8] to-[#eaf4f1]">
                            @if ($companyLogoUrl !== '')
                                <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}" class="block h-full w-full object-fill" />
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-[#4d8d82]/10 text-base font-bold text-[#2f4f4f]">
                                    {{ $companyName }}
                                </div>
                            @endif
                        </div>
                        <div class="px-5 py-5 sm:px-7 sm:py-6">
                            @hasSection('content')
                                @yield('content')
                            @else
                                {{ $slot }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
