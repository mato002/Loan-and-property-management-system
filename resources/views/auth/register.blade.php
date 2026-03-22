<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- System Selection -->
        <div>
            <x-input-label for="system" :value="__('System')" />
            <select id="system" name="system" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required autofocus>
                <option value="property" {{ old('system') == 'property' ? 'selected' : '' }}>Property Management</option>
                <option value="loan" {{ old('system') == 'loan' ? 'selected' : '' }}>Loan Management</option>
            </select>
            <x-input-error :messages="$errors->get('system')" class="mt-2" />
        </div>

        <div class="mt-4" id="property-portal-role-wrap" style="display: none;">
            <x-input-label for="property_portal_role" :value="__('Property portal role')" />
            <select id="property_portal_role" name="property_portal_role" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                <option value="agent" {{ old('property_portal_role', 'agent') == 'agent' ? 'selected' : '' }}>Agent (operations &amp; revenue)</option>
                <option value="landlord" {{ old('property_portal_role') == 'landlord' ? 'selected' : '' }}>Landlord (portfolio &amp; wallet)</option>
                <option value="tenant" {{ old('property_portal_role') == 'tenant' ? 'selected' : '' }}>Tenant (mobile-first)</option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose who this account represents in the property app. You can change this later in the database if needed.</p>
            <x-input-error :messages="$errors->get('property_portal_role')" class="mt-2" />
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

        <!-- Name -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
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
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
