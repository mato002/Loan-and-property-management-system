<section>
    <header>
        <h2 class="text-lg font-semibold text-slate-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6" x-data="{ showCurrent:false, showNew:false, showConfirm:false }">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" class="text-slate-600 text-xs font-semibold uppercase tracking-wide" />
            <div class="mt-1 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 focus-within:border-[#4d8d82] focus-within:ring-1 focus-within:ring-[#4d8d82]">
                <x-text-input id="update_password_current_password" name="current_password" x-bind:type="showCurrent ? 'text' : 'password'" class="block w-full border-0 bg-transparent px-0 py-2.5 text-slate-900 focus:ring-0" autocomplete="current-password" />
                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="showCurrent = !showCurrent">
                    <span x-text="showCurrent ? 'Hide' : 'Show'"></span>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" class="text-slate-600 text-xs font-semibold uppercase tracking-wide" />
            <div class="mt-1 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 focus-within:border-[#4d8d82] focus-within:ring-1 focus-within:ring-[#4d8d82]">
                <x-text-input id="update_password_password" name="password" x-bind:type="showNew ? 'text' : 'password'" class="block w-full border-0 bg-transparent px-0 py-2.5 text-slate-900 focus:ring-0" autocomplete="new-password" />
                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="showNew = !showNew">
                    <span x-text="showNew ? 'Hide' : 'Show'"></span>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" class="text-slate-600 text-xs font-semibold uppercase tracking-wide" />
            <div class="mt-1 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 focus-within:border-[#4d8d82] focus-within:ring-1 focus-within:ring-[#4d8d82]">
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" x-bind:type="showConfirm ? 'text' : 'password'" class="block w-full border-0 bg-transparent px-0 py-2.5 text-slate-900 focus:ring-0" autocomplete="new-password" />
                <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="showConfirm = !showConfirm">
                    <span x-text="showConfirm ? 'Hide' : 'Show'"></span>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button class="rounded-full border-transparent bg-[#4d8d82] hover:bg-[#3f7a70] focus:bg-[#3f7a70] active:bg-[#386f66] focus:ring-[#4d8d82]">{{ __('Save') }}</x-primary-button>
        </div>
    </form>
</section>
