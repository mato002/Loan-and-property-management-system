<x-guest-layout :title="__('New password').' — '.config('app.name')">
    @php
        $emailErr = $errors->first('email');
        $passErr = $errors->first('password');
        $confirmErr = $errors->first('password_confirmation');
    @endphp

    <div class="mb-8 flex items-center justify-between gap-4">
        <a
            href="{{ route('login') }}"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 transition hover:border-[#3B59FF]/40 hover:text-[#3B59FF]"
            aria-label="{{ __('Back to sign in') }}"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
        </a>
    </div>

    <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Set new password') }}</h1>
    <p class="mt-2 text-sm text-slate-500">{{ __('Choose a strong password for your account.') }}</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-10 space-y-6" x-data="{ showPw: false, showPw2: false }">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-auth.field-underline
            label="{{ __('Email') }}"
            id="email"
            name="email"
            type="email"
            :value="old('email', $request->email)"
            required
            autocomplete="username"
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
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </span>
                <input
                    id="password"
                    name="password"
                    x-bind:type="showPw ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 focus:ring-0"
                />
                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="showPw = !showPw" aria-label="{{ __('Toggle password visibility') }}">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                </button>
            </div>
            @if ($passErr)
                <p class="text-xs text-red-600">{{ $passErr }}</p>
            @endif
        </div>

        <div class="space-y-1">
            <label for="password_confirmation" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Confirm password') }}</label>
            <div class="flex items-center gap-3 border-b pb-2.5 transition-colors {{ $confirmErr ? 'border-red-400' : 'border-slate-200 focus-within:border-[#3B59FF]' }}">
                <span class="text-slate-400" aria-hidden="true">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                </span>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    x-bind:type="showPw2 ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 focus:ring-0"
                />
                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="showPw2 = !showPw2" aria-label="{{ __('Toggle password visibility') }}">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                </button>
            </div>
            @if ($confirmErr)
                <p class="text-xs text-red-600">{{ $confirmErr }}</p>
            @endif
        </div>

        <button
            type="submit"
            class="group flex w-full items-center justify-center gap-3 rounded-full bg-[#3B59FF] py-4 text-base font-semibold text-white shadow-lg shadow-[#3B59FF]/35 transition hover:bg-[#2f4cd4]"
        >
            {{ __('Reset password') }}
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20" aria-hidden="true">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </span>
        </button>
    </form>
</x-guest-layout>
