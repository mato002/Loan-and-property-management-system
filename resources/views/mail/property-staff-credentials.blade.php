<x-mail::message>
# {{ __('Hello, :name', ['name' => $employeeName]) }}

{{ __('Your property workspace account is ready. Use these details to sign in and complete the work assigned to you.') }}

**{{ __('Role') }}:** {{ $role }}

**{{ __('Email') }}:** {{ $email }}

**{{ __('Temporary password') }}:** `{{ $plainPassword }}`

<x-mail::button :url="$loginUrl">
{{ __('Sign in') }}
</x-mail::button>

{{ __('After sign-in, open your property workspace:') }}

<x-mail::button :url="$workspaceUrl">
{{ __('Property dashboard') }}
</x-mail::button>

<x-mail::panel>
{{ __('Change this password as soon as you sign in. Do not share it.') }}
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
