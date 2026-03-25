<x-guest-layout :title="__('Sign in').' — '.config('app.name')">
    @php
        $emailErr = $errors->first('email');
        $passErr = $errors->first('password');
        $moduleErr = $errors->first('module');
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
            {{ __('Not a member?') }}
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="font-semibold text-[#3B59FF] hover:text-[#2d47cc]">{{ __('Sign up') }}</a>
            @endif
        </p>
    </div>

    <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Sign in') }}</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-500">
        {{ __('Access property and loan staff portals with your work email.') }}
    </p>

    @if ($moduleErr)
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $moduleErr }}
        </div>
    @endif

    <p class="mt-4 text-xs leading-relaxed text-slate-500">
        {{ __('Renting or owning through us?') }}
        <a href="{{ route('property.tenant.login') }}" class="font-semibold text-[#3B59FF] hover:underline">{{ __('Tenant sign-in') }}</a>
        <span class="text-slate-300" aria-hidden="true">·</span>
        <a href="{{ route('property.landlord.login') }}" class="font-semibold text-[#3B59FF] hover:underline">{{ __('Landlord sign-in') }}</a>
    </p>

    <form method="POST" action="{{ route('login') }}" class="mt-10 space-y-6" x-data="{ showPassword: false }">
        @csrf

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
                    x-bind:type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0"
                    placeholder="••••••••"
                />
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
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
                <p class="text-xs text-red-600">{{ $passErr }}</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-[#3B59FF] focus:ring-[#3B59FF]" />
                <span>{{ __('Remember me') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-[#3B59FF] hover:text-[#2d47cc]">{{ __('Forgot password?') }}</a>
            @endif
        </div>

        <button
            type="submit"
            class="group relative flex w-full items-center justify-center gap-3 rounded-full bg-[#3B59FF] py-4 text-base font-semibold text-white shadow-lg shadow-[#3B59FF]/35 transition hover:bg-[#2f4cd4] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3B59FF] focus-visible:ring-offset-2"
        >
            {{ __('Sign in') }}
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 transition group-hover:bg-white/30" aria-hidden="true">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </span>
        </button>
    </form>
</x-guest-layout>
