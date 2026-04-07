@php $emailErr = $errors->first('email'); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reset password').' — '.config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0f2f2a] text-white antialiased">
    <x-swal-flash />
    <main class="min-h-screen flex items-center justify-center px-6 py-10 sm:px-10">
        <div class="w-full max-w-md">
            <h2 class="text-center text-3xl font-bold">{{ __('Forgot password?') }}</h2>
            <p class="mt-2 text-center text-sm text-white/80">{{ __('No problem. Enter your email and we will send a reset link.') }}</p>

            <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-white/85">{{ __('Email') }}</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="name@company.com"
                        class="w-full rounded-md border border-white/30 bg-white/95 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-[#4d8d82] focus:outline-none focus:ring-2 focus:ring-[#4d8d82]/40"
                    >
                    @if ($emailErr)
                        <p class="mt-1.5 text-xs text-red-200">{{ $emailErr }}</p>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-md bg-[#4d8d82] py-2.5 text-sm font-semibold text-white transition hover:bg-[#3f7a70] focus:outline-none focus:ring-2 focus:ring-[#7bc4b8]">
                    {{ __('Email reset link') }}
                </button>
            </form>

            <p class="mt-5 text-center text-xs">
                <a href="{{ route('login') }}" class="font-semibold text-[#8cd4c8] hover:underline">{{ __('Back to sign in') }}</a>
            </p>
        </div>
    </main>
</body>
</html>
