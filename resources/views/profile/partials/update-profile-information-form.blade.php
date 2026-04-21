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

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="profile_photo" :value="__('Profile image')" class="text-slate-600 text-xs font-semibold uppercase tracking-wide" />
            <div class="mt-3 flex items-center gap-4">
                <div class="h-16 w-16 rounded-full border border-slate-200 bg-slate-100 overflow-hidden flex items-center justify-center text-slate-500 text-xl font-semibold">
                    @if (!empty($user?->profile_photo_url))
                        <img src="{{ $user->profile_photo_url }}" alt="Profile image" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(substr((string) ($user->name ?? 'U'), 0, 1)) }}
                    @endif
                </div>
                <div class="flex-1">
                    <input id="profile_photo" name="profile_photo" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200" />
                    <p class="mt-1 text-xs text-slate-500">Accepted: JPG, PNG, WEBP. Max size 2MB.</p>
                </div>
            </div>
            <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remove_profile_photo" value="1" class="rounded border-slate-300 text-[#4d8d82] focus:ring-[#4d8d82]" />
                Remove current image
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />
        </div>

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
                    <p class="text-sm mt-2 text-amber-700">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-[#4d8d82] hover:text-[#3f7a70] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4d8d82]">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                            {{ __('Verification code sent. Check your inbox and follow the verification link.') }}
                        </p>
                    @endif
                </div>
            @elseif (request()->boolean('verified'))
                <p class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    {{ __('Email verified successfully.') }}
                </p>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button class="rounded-full border-transparent bg-[#4d8d82] hover:bg-[#3f7a70] focus:bg-[#3f7a70] active:bg-[#386f66] focus:ring-[#4d8d82]">{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
