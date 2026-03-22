<x-guest-layout :title="__('Sign up').' — '.config('app.name')">
    @php
        $nameErr = $errors->first('name');
        $emailErr = $errors->first('email');
        $passErr = $errors->first('password');
        $confirmErr = $errors->first('password_confirmation');
        $sysErr = $errors->first('system');
        $roleErr = $errors->first('property_portal_role');
    @endphp

    <div class="mb-8 flex items-center justify-between gap-4">
        <a
            href="{{ url('/') }}"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-[#3B59FF]/40 hover:text-[#3B59FF]"
            aria-label="{{ __('Back to home') }}"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
        </a>
        <p class="text-sm text-slate-600">
            {{ __('Already a member?') }}
            <a href="{{ route('login') }}" class="font-semibold text-[#3B59FF] hover:text-[#2d47cc]">{{ __('Sign in') }}</a>
        </p>
    </div>

    <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Sign up') }}</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-500">
        {{ __('Create your workspace account — choose property or loan, then your role for the property portal.') }}
    </p>

    <form
        method="POST"
        action="{{ route('register') }}"
        class="mt-10 space-y-6"
        x-data="{
            showPw: false,
            showPw2: false,
            password: '',
            rules: {
                len: (v) => v.length >= 8,
                num: (v) => /\d/.test(v),
                hasLower: (v) => /[a-z]/.test(v),
                hasUpper: (v) => /[A-Z]/.test(v),
            }
        }"
    >
        @csrf

        <div class="space-y-1">
            <label for="system" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('System') }}</label>
            <div class="flex items-center gap-3 border-b border-slate-200 pb-2.5 transition-colors focus-within:border-[#3B59FF] {{ $sysErr ? '!border-red-400' : '' }}">
                <span class="text-slate-400" aria-hidden="true">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5 3v18m15-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6.75H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                </span>
                <select
                    id="system"
                    name="system"
                    required
                    autofocus
                    class="min-w-0 flex-1 cursor-pointer border-0 bg-transparent p-0 text-base text-slate-900 focus:ring-0"
                >
                    <option value="property" @selected(old('system', 'property') == 'property')>{{ __('Property management') }}</option>
                    <option value="loan" @selected(old('system', 'property') == 'loan')>{{ __('Loan management') }}</option>
                </select>
            </div>
            @if ($sysErr)
                <p class="text-xs text-red-600">{{ $sysErr }}</p>
            @endif
        </div>

        <div id="property-portal-role-wrap" class="space-y-1" style="display: none;">
            <label for="property_portal_role" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Property portal role') }}</label>
            <div class="flex items-center gap-3 border-b border-slate-200 pb-2.5 transition-colors focus-within:border-[#3B59FF] {{ $roleErr ? '!border-red-400' : '' }}">
                <span class="text-slate-400" aria-hidden="true">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </span>
                <select
                    id="property_portal_role"
                    name="property_portal_role"
                    class="min-w-0 flex-1 cursor-pointer border-0 bg-transparent p-0 text-base text-slate-900 focus:ring-0"
                >
                    <option value="agent" @selected(old('property_portal_role', 'agent') == 'agent')>{{ __('Agent (operations & revenue)') }}</option>
                    <option value="landlord" @selected(old('property_portal_role') == 'landlord')>{{ __('Landlord (portfolio & wallet)') }}</option>
                    <option value="tenant" @selected(old('property_portal_role') == 'tenant')>{{ __('Tenant (mobile-first)') }}</option>
                </select>
            </div>
            <p class="text-xs text-slate-500">{{ __('Who this account represents in the property app.') }}</p>
            @if ($roleErr)
                <p class="text-xs text-red-600">{{ $roleErr }}</p>
            @endif
        </div>

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

        <x-auth.field-underline
            label="{{ __('Full name') }}"
            id="name"
            name="name"
            type="text"
            :value="old('name')"
            required
            autocomplete="name"
            placeholder="{{ __('Jane Doe') }}"
            :error="$nameErr"
        >
            <x-slot name="icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </x-slot>
        </x-auth.field-underline>

        <x-auth.field-underline
            label="{{ __('Email') }}"
            id="email"
            name="email"
            type="email"
            :value="old('email')"
            required
            autocomplete="username"
            placeholder="name@company.com"
            :error="$emailErr"
        >
            <x-slot name="icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </x-slot>
        </x-auth.field-underline>

        <div class="space-y-1">
            <label for="password" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Password') }}</label>
            <div class="flex items-center gap-3 border-b pb-2.5 transition-colors {{ $passErr ? 'border-red-400' : 'border-slate-200 focus-within:border-[#3B59FF]' }}">
                <span class="text-slate-400" aria-hidden="true">
                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </span>
                <input
                    id="password"
                    name="password"
                    x-model="password"
                    x-bind:type="showPw ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0"
                    placeholder="••••••••"
                />
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    @click="showPw = !showPw"
                    :aria-pressed="showPw"
                    aria-label="{{ __('Toggle password visibility') }}"
                >
                    <svg x-show="!showPw" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg x-cloak x-show="showPw" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m12.74 12.74L21 21" />
                    </svg>
                </button>
            </div>
            @if ($passErr)
                <p class="text-xs text-red-600">{{ $passErr }}</p>
            @endif
            <ul class="mt-3 space-y-2 text-xs text-slate-500" aria-live="polite">
                <li class="flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full" :class="rules.len(password) ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400'">
                        <svg x-show="rules.len(password)" class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    {{ __('At least 8 characters') }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full" :class="rules.num(password) ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400'">
                        <svg x-show="rules.num(password)" class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    {{ __('At least one number') }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full" :class="rules.hasLower(password) && rules.hasUpper(password) ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400'">
                        <svg x-show="rules.hasLower(password) && rules.hasUpper(password)" class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    {{ __('Lowercase and uppercase letter') }}
                </li>
            </ul>
            <p class="mt-1 text-[11px] text-slate-400">{{ __('Stronger passwords are recommended; your account must meet the server rules shown on error.') }}</p>
        </div>

        <div class="space-y-1">
            <label for="password_confirmation" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Confirm password') }}</label>
            <div class="flex items-center gap-3 border-b pb-2.5 transition-colors {{ $confirmErr ? 'border-red-400' : 'border-slate-200 focus-within:border-[#3B59FF]' }}">
                <span class="text-slate-400" aria-hidden="true">
                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                </span>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    x-bind:type="showPw2 ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0"
                    placeholder="••••••••"
                />
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    @click="showPw2 = !showPw2"
                    :aria-pressed="showPw2"
                    aria-label="{{ __('Toggle password visibility') }}"
                >
                    <svg x-show="!showPw2" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg x-cloak x-show="showPw2" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m12.74 12.74L21 21" />
                    </svg>
                </button>
            </div>
            @if ($confirmErr)
                <p class="text-xs text-red-600">{{ $confirmErr }}</p>
            @endif
        </div>

        <button
            type="submit"
            class="group relative flex w-full items-center justify-center gap-3 rounded-full bg-[#3B59FF] py-4 text-base font-semibold text-white shadow-lg shadow-[#3B59FF]/35 transition hover:bg-[#2f4cd4] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3B59FF] focus-visible:ring-offset-2"
        >
            {{ __('Create account') }}
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 transition group-hover:bg-white/30" aria-hidden="true">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </span>
        </button>

        <div class="relative py-2">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-slate-200"></div>
            </div>
            <div class="relative flex justify-center text-xs">
                <span class="bg-white px-3 text-slate-400">{{ __('or') }}</span>
            </div>
        </div>

        <div class="flex justify-center gap-4">
            <span class="flex h-12 w-12 cursor-default items-center justify-center rounded-full border border-slate-200 text-slate-400 opacity-60" title="{{ __('Not connected') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </span>
            <span class="flex h-12 w-12 cursor-default items-center justify-center rounded-full border border-slate-200 text-slate-400 opacity-60" title="{{ __('Not connected') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.3-1.5 3.8-5.5 3.8-3.3 0-6-2.7-6-6s2.7-6 6-6c1.9 0 3.2.8 3.9 1.5l2.6-2.5C16.7 3.9 14.6 3 12 3 7 3 3 7 3 12s4 9 9 9c5.2 0 8.6-3.6 8.6-8.7 0-.6-.1-1.1-.2-1.6H12z"/><path fill="#34A853" d="M3.3 7.1 6.9 9.8C7.6 7.5 9.6 6 12 6c1.9 0 3.2.8 3.9 1.5l2.6-2.5C16.7 3.9 14.6 3 12 3 8.3 3 5.1 4.6 3.3 7.1z"/><path fill="#FBBC05" d="M12 21c2.4 0 4.5-.8 6-2.2l-2.8-2.2c-.8.5-1.8.9-3.2.9-2.5 0-4.6-1.7-5.4-4l-3.6 2.8C5.1 19.4 8.3 21 12 21z"/><path fill="#4285F4" d="M21.5 12.3c0-.8-.1-1.6-.2-2.4H12v4.6h5.4c-.2 1.3-1 2.4-2.1 3.1l2.8 2.2c1.7-1.6 2.7-3.9 2.7-6.5z"/></svg>
            </span>
        </div>
        <p class="text-center text-[11px] text-slate-400">{{ __('Social sign-in is not enabled for staff portals.') }}</p>
    </form>
</x-guest-layout>
