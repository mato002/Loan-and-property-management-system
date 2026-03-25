<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $companyName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name');
            $siteFaviconUrl = \App\Models\PropertyPortalSetting::getValue('site_favicon_url', '');
            $faviconHref = $siteFaviconUrl !== '' ? $siteFaviconUrl : asset('favicon.ico');
            $faviconVersioned = $faviconHref.'?v='.rawurlencode(substr(md5($faviconHref), 0, 12));
            $resolvedTitle = str_replace(config('app.name'), $companyName, $title);
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
    <body class="min-h-screen antialiased bg-white text-slate-900" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
        <x-swal-flash />
        <div class="min-h-screen flex flex-col lg:flex-row">
            <div class="h-1.5 w-full shrink-0 bg-gradient-to-r from-[#1e3a8a] via-[#3B59FF] to-[#6d28d9] lg:hidden" aria-hidden="true"></div>
            {{-- Form column --}}
            <div class="relative z-10 flex w-full flex-1 flex-col px-6 py-8 sm:px-10 sm:py-12 lg:w-1/2 lg:max-w-none lg:flex-none lg:px-14 lg:py-16 xl:px-20">
                <div class="mx-auto w-full max-w-md flex-1">
                    {{ $slot }}
                </div>
            </div>

            {{-- Visual column --}}
            <aside class="relative hidden min-h-[280px] flex-1 overflow-hidden lg:flex lg:min-h-screen">
                <div class="absolute inset-0 bg-gradient-to-br from-[#1e3a8a] via-[#3B59FF] to-[#6d28d9]"></div>
                <div class="absolute -right-20 top-1/4 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -left-16 bottom-1/4 h-80 w-80 rounded-full bg-violet-400/20 blur-3xl"></div>
                <div class="absolute right-1/4 top-10 h-64 w-64 rounded-full border border-white/10 bg-white/5"></div>
                <div class="absolute bottom-32 left-12 h-40 w-40 rounded-full bg-indigo-300/20 blur-2xl"></div>

                <div class="relative z-10 flex w-full flex-col justify-center px-10 py-16 xl:px-16">
                    <div class="mb-8">
                        <p class="text-sm font-medium uppercase tracking-widest text-white/70">{{ $companyName }}</p>
                        <h2 class="mt-2 max-w-sm text-3xl font-bold leading-tight text-white xl:text-4xl">
                            {{ __('Operations, revenue, and trust — in one workspace.') }}
                        </h2>
                    </div>

                    <div class="space-y-5">
                        <div class="rounded-2xl bg-white/95 p-5 shadow-xl shadow-black/10 ring-1 ring-white/20 backdrop-blur-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Portfolio pulse') }}</p>
                                    <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">—</p>
                                </div>
                                <span class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-bold text-white">Live</span>
                            </div>
                            <div class="mt-4 flex h-12 items-end gap-1">
                                @foreach ([40, 65, 45, 80, 55, 90, 70] as $h)
                                    <div class="flex-1 rounded-t bg-gradient-to-t from-amber-400 to-amber-300" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white/95 p-5 shadow-xl shadow-black/10 ring-1 ring-white/20 backdrop-blur-sm">
                            <div class="flex gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-2xl" aria-hidden="true">🔐</div>
                                <div>
                                    <h3 class="font-semibold text-slate-900">{{ __('Your data, your rules') }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                        {{ __('Role-based access keeps landlord, tenant, and agent views separated. Sign in with your staff credentials to continue.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 flex gap-3 text-white/50">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 text-xs font-semibold">in</span>
                        <span class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 text-xs font-semibold">TT</span>
                    </div>
                </div>
            </aside>
        </div>
    </body>
</html>
