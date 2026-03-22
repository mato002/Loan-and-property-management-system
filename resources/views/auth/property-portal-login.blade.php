<x-guest-property-portal-layout :portal="$portalRole">
    <form method="POST" action="{{ $postRoute }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" class="text-slate-300" />
            <x-text-input
                id="email"
                class="block mt-1 w-full border-slate-600 bg-slate-950/50 text-slate-100 {{ $portalRole === 'landlord' ? 'focus:border-amber-500 focus:ring-amber-500' : 'focus:border-teal-500 focus:ring-teal-500' }}"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" class="text-slate-300" />
            <x-text-input
                id="password"
                class="block mt-1 w-full border-slate-600 bg-slate-950/50 text-slate-100 {{ $portalRole === 'landlord' ? 'focus:border-amber-500 focus:ring-amber-500' : 'focus:border-teal-500 focus:ring-teal-500' }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-slate-600 bg-slate-950/50 {{ $portalRole === 'landlord' ? 'text-amber-500 focus:ring-amber-500' : 'text-teal-500 focus:ring-teal-500' }} shadow-sm focus:ring-offset-slate-900"
                    name="remember"
                />
                <span class="ms-2 text-sm text-slate-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 mt-6">
            @if (Route::has('password.request'))
                <a
                    class="text-sm text-slate-400 hover:text-white underline underline-offset-2 text-center sm:text-left"
                    href="{{ route('password.request') }}"
                >
                    {{ __('Forgot password?') }}
                </a>
            @endif

            <x-primary-button class="w-full sm:w-auto justify-center border-transparent {{ $portalRole === 'landlord' ? 'bg-amber-600 hover:bg-amber-500 focus:ring-amber-500' : 'bg-teal-600 hover:bg-teal-500 focus:ring-teal-500' }}">
                {{ __('Sign in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-property-portal-layout>
