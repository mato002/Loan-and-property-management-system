@php
    $nameErr = $errors->first('name');
    $emailErr = $errors->first('email');
    $passErr = $errors->first('password');
    $confirmErr = $errors->first('password_confirmation');
    $sysErr = $errors->first('system');
    $roleErr = $errors->first('property_portal_role');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Sign up').' — '.config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-[#0f2f2a] text-white antialiased">
    <x-swal-flash />
    <main class="min-h-screen flex items-center justify-center px-6 py-10 sm:px-10">
        <div class="w-full max-w-lg" x-data="{ showPw: false, showPw2: false, password: '' }">
            <h2 class="text-center text-3xl font-bold">{{ __('Create account') }}</h2>
            <p class="mt-2 text-center text-sm text-white/80">{{ __('Create your workspace account for property or loan management.') }}</p>

            <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
                @csrf

                <div>
                    <label for="system" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('System') }}</label>
                    <select id="system" name="system" required autofocus class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 text-sm text-slate-900 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40">
                        <option value="property" @selected(old('system', 'property') == 'property')>{{ __('Property management') }}</option>
                        <option value="loan" @selected(old('system', 'property') == 'loan')>{{ __('Loan management') }}</option>
                    </select>
                    @if ($sysErr)
                        <p class="mt-1.5 text-xs text-red-200">{{ $sysErr }}</p>
                    @endif
                </div>

                <div id="property-portal-role-wrap">
                    <label for="property_portal_role" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Property portal role') }}</label>
                    <select id="property_portal_role" name="property_portal_role" class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 text-sm text-slate-900 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40">
                        <option value="agent" @selected(old('property_portal_role', 'agent') == 'agent')>{{ __('Agent (operations & revenue)') }}</option>
                        <option value="landlord" @selected(old('property_portal_role') == 'landlord')>{{ __('Landlord (portfolio & wallet)') }}</option>
                        <option value="tenant" @selected(old('property_portal_role') == 'tenant')>{{ __('Tenant (mobile-first)') }}</option>
                    </select>
                    @if ($roleErr)
                        <p class="mt-1.5 text-xs text-red-200">{{ $roleErr }}</p>
                    @endif
                </div>

                <div>
                    <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Full name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" placeholder="{{ __('Jane Doe') }}" class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40">
                    @if ($nameErr)
                        <p class="mt-1.5 text-xs text-red-200">{{ $nameErr }}</p>
                    @endif
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" placeholder="name@company.com" class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40">
                    @if ($emailErr)
                        <p class="mt-1.5 text-xs text-red-200">{{ $emailErr }}</p>
                    @endif
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Password') }}</label>
                    <div class="relative">
                        <input id="password" name="password" x-model="password" x-bind:type="showPw ? 'text' : 'password'" required autocomplete="new-password" class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 pr-11 text-sm text-slate-900 placeholder:text-slate-500 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40">
                        <button type="button" class="absolute inset-y-0 right-0 grid place-items-center px-3 text-slate-500 hover:text-slate-700" @click="showPw = !showPw" aria-label="{{ __('Toggle password visibility') }}">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        </button>
                    </div>
                    @if ($passErr)
                        <p class="mt-1.5 text-xs text-red-200">{{ $passErr }}</p>
                    @endif
                    <p class="mt-1.5 text-xs text-white/70">{{ __('Use at least 8 characters, including a number and mixed case.') }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Confirm password') }}</label>
                    <div class="relative">
                        <input id="password_confirmation" name="password_confirmation" x-bind:type="showPw2 ? 'text' : 'password'" required autocomplete="new-password" class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 pr-11 text-sm text-slate-900 placeholder:text-slate-500 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40">
                        <button type="button" class="absolute inset-y-0 right-0 grid place-items-center px-3 text-slate-500 hover:text-slate-700" @click="showPw2 = !showPw2" aria-label="{{ __('Toggle password visibility') }}">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        </button>
                    </div>
                    @if ($confirmErr)
                        <p class="mt-1.5 text-xs text-red-200">{{ $confirmErr }}</p>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-md bg-[#4d8d82] py-2.5 text-sm font-semibold text-white transition hover:bg-[#3f7a70] focus:outline-none focus:ring-2 focus:ring-[#7bc4b8]">
                    {{ __('Create account') }}
                </button>
            </form>

            <p class="mt-5 text-center text-xs">
                {{ __('Already a member?') }}
                <a href="{{ route('login') }}" class="font-semibold text-[#8cd4c8] hover:underline">{{ __('Sign in') }}</a>
            </p>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var system = document.getElementById('system');
            var wrap = document.getElementById('property-portal-role-wrap');
            function sync() {
                wrap.style.display = system.value === 'property' ? 'block' : 'none';
            }
            system.addEventListener('change', sync);
            sync();
        });
    </script>
</body>
</html>
