<x-property-layout>
    <x-slot name="header">{{ __('Add team member') }}</x-slot>

    <x-property.page
        :title="__('Add team member')"
        :subtitle="__('Creates a property agent login with the roles you select. Loan module access stays off unless a super admin changes it later. Login still requires an email address.')"
    >
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('property.settings.roles') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">← {{ __('Property users') }}</a>
        </div>

        @if (! $rolesReady)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/30 dark:text-amber-200">
                {{ __('Create at least one staff role under Settings → System setup → Access control (portal scope Agent or Any), then return here.') }}
            </div>
        @else
            <form method="post" action="{{ route('property.settings.team_users.store') }}" class="max-w-xl space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                @csrf
                @if ($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/40 px-3 py-2 text-sm text-red-800 dark:text-red-200">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Full name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 px-4 py-3 text-sm" />
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Work email (login)') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 px-4 py-3 text-sm" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Used as the username for sign-in. They can change password after first login.') }}</p>
                </div>

                @if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone'))
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Phone (optional)') }}</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="32" class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 px-4 py-3 text-sm" placeholder="+254…" />
                    </div>
                @endif

                <fieldset>
                    <legend class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Property roles') }}</legend>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Permissions come from these roles. You can adjust them later under Access control.') }}</p>
                    <div class="mt-2 max-h-56 space-y-2 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-600 p-3">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, array_map('intval', (array) old('role_ids', [])), true)) />
                                <span>{{ $role->name }} <span class="text-xs text-slate-400">({{ $role->slug }})</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">{{ __('Create user') }}</button>
                    <a href="{{ route('property.settings.roles') }}" class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">{{ __('Cancel') }}</a>
                </div>
            </form>
        @endif
    </x-property.page>
</x-property-layout>
