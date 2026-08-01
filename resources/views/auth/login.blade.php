<x-guest-layout :title="__('Sign in').' — '.config('app.name')">
    @php
        $emailErr = $errors->first('email');
        $passErr = $errors->first('password');
        $moduleErr = $errors->first('module');
        $badgeClass = 'inline-flex items-center rounded-full bg-[#6a9f97]/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#386f66] ring-1 ring-[#6a9f97]/35';
    @endphp

    <div class="text-center mb-5">
        <p class="{{ $badgeClass }}">{{ __('Operations') }}</p>
        <h1 class="mt-3 text-xl sm:text-2xl font-bold tracking-tight text-slate-900">{{ __('Welcome back') }}</h1>
        <p class="mt-1.5 text-sm leading-snug text-slate-500">
            {{ __('Sign in with your staff credentials to open your workspace.') }}
        </p>
    </div>

    @if ($moduleErr)
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 text-left" role="alert">
            {{ $moduleErr }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ showPassword: false }">
        @csrf

        <div class="space-y-1">
            <label for="email" class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Email') }}</label>
            <div class="flex items-center gap-3 border-b pb-2.5 transition-colors {{ $emailErr ? 'border-red-400' : 'border-slate-200 focus-within:border-[#4d8d82]' }}">
                <span class="text-slate-400" aria-hidden="true">
                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                </span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="name@company.com"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0"
                />
            </div>
            @if ($emailErr)
                <p class="text-xs text-red-600 text-left">{{ $emailErr }}</p>
            @endif
        </div>

        <div class="space-y-1">
            <label for="password" class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Password') }}</label>
            <div class="flex items-center gap-3 border-b pb-2.5 transition-colors {{ $passErr ? 'border-red-400' : 'border-slate-200 focus-within:border-[#4d8d82]' }}">
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
                    placeholder="••••••••"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 placeholder:text-slate-400 focus:ring-0"
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
                <p class="text-xs text-red-600 text-left">{{ $passErr }}</p>
            @endif
        </div>

        <div class="block">
            <label for="remember_me" class="inline-flex items-center">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-slate-300 text-[#4d8d82] shadow-sm focus:ring-[#4d8d82]"
                    name="remember"
                />
                <span class="ms-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 pt-1">
            @if (Route::has('password.request'))
                <a
                    class="text-sm text-slate-500 hover:text-[#3f7a70] underline underline-offset-2 text-center sm:text-left"
                    href="{{ route('password.request') }}"
                >
                    {{ __('Forgot password?') }}
                </a>
            @endif

            <x-primary-button class="w-full sm:w-auto justify-center rounded-full border-transparent bg-[#4d8d82] hover:bg-[#3f7a70] focus:ring-[#4d8d82] px-6 py-2.5">
                {{ __('Sign in') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-5 text-center text-xs text-slate-500 space-y-2">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Quick access') }}</p>
        <p class="space-x-2">
            <a href="{{ route('property.tenant.login') }}" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">{{ __('Tenant portal') }}</a>
            <span class="text-slate-300" aria-hidden="true">·</span>
            <a href="{{ route('property.landlord.login') }}" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">{{ __('Landlord portal') }}</a>
            <span class="text-slate-300" aria-hidden="true">·</span>
            <a href="{{ url('/') }}" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">{{ __('Home') }}</a>
        </p>
        @if (Route::has('register'))
            <p>
                {{ __('Not a member?') }}
                <a href="{{ route('register') }}" class="font-semibold text-[#4d8d82] hover:text-[#3f7a70] underline underline-offset-2">{{ __('Create account') }}</a>
            </p>
        @endif
    </div>
</x-guest-layout>
