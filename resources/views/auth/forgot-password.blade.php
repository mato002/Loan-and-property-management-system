<x-guest-layout :title="__('Reset password').' — '.config('app.name')">
    @php $emailErr = $errors->first('email'); @endphp

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

    <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Forgot password?') }}</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-500">
        {{ __('No problem. Enter your email and we will send a reset link.') }}
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-10 space-y-8">
        @csrf

        <x-auth.field-underline
            label="{{ __('Email') }}"
            id="email"
            name="email"
            type="email"
            :value="old('email')"
            required
            autofocus
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

        <button
            type="submit"
            class="group flex w-full items-center justify-center gap-3 rounded-full bg-[#3B59FF] py-4 text-base font-semibold text-white shadow-lg shadow-[#3B59FF]/35 transition hover:bg-[#2f4cd4] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3B59FF] focus-visible:ring-offset-2"
        >
            {{ __('Email reset link') }}
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20" aria-hidden="true">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </span>
        </button>
    </form>
</x-guest-layout>
