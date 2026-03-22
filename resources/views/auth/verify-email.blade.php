<x-guest-layout :title="__('Verify email').' — '.config('app.name')">

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
    </div>

    <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ __('Verify your email') }}</h1>
    <p class="mt-2 text-sm leading-relaxed text-slate-500">
        {{ __('Thanks for signing up! Before getting started, verify your email using the link we sent. If you did not receive it, we can send another.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ __('A new verification link has been sent to your email address.') }}
        </p>
    @endif

    <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button
                type="submit"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-[#3B59FF] px-8 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#3B59FF]/30 transition hover:bg-[#2f4cd4] sm:w-auto"
            >
                {{ __('Resend verification email') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="w-full rounded-full border border-slate-200 py-3.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 sm:w-auto sm:px-6"
            >
                {{ __('Log out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
