@php
    $emailErr = $errors->first('email');
    $passErr = $errors->first('password');
    $moduleErr = $errors->first('module');
    $heroImage = 'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1800&q=80';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Sign in').' — '.config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-[#0f2f2a] text-white antialiased" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
    <x-swal-flash />
    <main class="min-h-screen grid lg:grid-cols-2">
        <section class="relative hidden lg:block">
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-br from-[#4d8d82]/90 via-[#2f4f4f]/75 to-[#102c28]/90"></div>
            <div class="relative z-10 flex h-full flex-col justify-between p-12">
                <div>
                    <h1 class="text-5xl font-extrabold leading-[0.95] tracking-tight">Welcome<br>Back</h1>
                    <p class="mt-6 max-w-md text-sm text-white/85">
                        Securely sign in to manage properties, tenants, lease activity, billing, and reporting in one place.
                    </p>
                </div>
                <div class="text-xs text-white/80">
                    {{ __('Need a portal account? Contact your administrator.') }}
                </div>
            </div>
        </section>

        <section class="relative flex items-center justify-center px-6 py-10 sm:px-10">
            <div class="w-full max-w-md" x-data="{ showPassword: false }">
                <h2 class="text-center text-3xl font-bold">Sign in</h2>

                @if ($moduleErr)
                    <div class="mt-5 rounded-lg border border-red-300/60 bg-red-950/40 px-4 py-3 text-sm text-red-100">
                        {{ $moduleErr }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Email') }}</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="username"
                            placeholder="name@company.com"
                            class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40"
                        >
                        @if ($emailErr)
                            <p class="mt-1.5 text-xs text-red-200">{{ $emailErr }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Password') }}</label>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 pr-11 text-sm text-slate-900 placeholder:text-slate-500 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 grid place-items-center px-3 text-slate-500 hover:text-slate-700"
                                @click="showPassword = !showPassword"
                                :aria-pressed="showPassword"
                                aria-label="{{ __('Toggle password visibility') }}"
                            >
                                <svg x-show="!showPassword" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-cloak x-show="showPassword" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m12.74 12.74L21 21" />
                                </svg>
                            </button>
                        </div>
                        @if ($passErr)
                            <p class="mt-1.5 text-xs text-red-200">{{ $passErr }}</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-white/85">
                            <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-white/40 text-[#4d8d82] focus:ring-[#4d8d82]">
                            <span>{{ __('Remember me') }}</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="font-semibold text-[#8cd4c8] hover:text-[#b2ece2]">{{ __('Forgot password?') }}</a>
                        @endif
                    </div>

                    <button type="submit" class="w-full rounded-md bg-[#4d8d82] py-2.5 text-sm font-semibold text-white transition hover:bg-[#3f7a70] focus:outline-none focus:ring-2 focus:ring-[#7bc4b8]">
                        {{ __('Sign in now') }}
                    </button>
                </form>

                <div class="mt-6 text-center text-xs text-white/75">
                    <a href="{{ route('property.tenant.login') }}" class="font-semibold text-[#8cd4c8] hover:underline">{{ __('Tenant sign-in') }}</a>
                    <span class="mx-2 text-white/35">|</span>
                    <a href="{{ route('property.landlord.login') }}" class="font-semibold text-[#8cd4c8] hover:underline">{{ __('Landlord sign-in') }}</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
