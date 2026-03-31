<section id="update-profile">
    <header>
        <h2 class="text-lg font-semibold text-slate-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" class="text-slate-600 text-xs font-semibold uppercase tracking-wide" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-slate-900 focus:border-[#4d8d82] focus:ring-[#4d8d82]" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" class="text-slate-600 text-xs font-semibold uppercase tracking-wide" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-slate-900 focus:border-[#4d8d82] focus:ring-[#4d8d82]" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-slate-700">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-[#4d8d82] hover:text-[#3f7a70] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4d8d82]">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button class="rounded-full border-transparent bg-[#4d8d82] hover:bg-[#3f7a70] focus:bg-[#3f7a70] active:bg-[#386f66] focus:ring-[#4d8d82]">{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
