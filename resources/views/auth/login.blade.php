<x-guest-layout>
    <p class="text-xs text-gray-500 dark:text-gray-400 text-center mb-4 leading-relaxed">
        {{ __('Renting or owning through us?') }}
        <a href="{{ route('property.tenant.login') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Tenant sign-in') }}</a>
        <span class="text-gray-400" aria-hidden="true">·</span>
        <a href="{{ route('property.landlord.login') }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Landlord sign-in') }}</a>
    </p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- System Selection -->
        <div>
            <x-input-label for="system" :value="__('System (staff and loan)')" />
            <select id="system" name="system" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required autofocus>
                <option value="property" {{ old('system') == 'property' ? 'selected' : '' }}>Property Management (agents)</option>
                <option value="loan" {{ old('system') == 'loan' ? 'selected' : '' }}>Loan Management</option>
            </select>
            <x-input-error :messages="$errors->get('system')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
