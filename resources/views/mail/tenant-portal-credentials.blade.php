<x-mail::message>
# {{ __('Welcome, :name', ['name' => $tenantName]) }}

{{ __('Your property manager has set up access to the tenant portal. Use the details below to sign in.') }}

**{{ __('Email') }}:** {{ $email }}

**{{ __('Temporary password') }}:** `{{ $plainPassword }}`

<x-mail::button :url="$loginUrl">
{{ __('Open tenant sign-in') }}
</x-mail::button>

{{ __('Use the email and temporary password above on that page only — it is separate from staff and loan system sign-in.') }}

<x-mail::button :url="$tenantHomeUrl">
{{ __('Tenant dashboard (after sign-in)') }}
</x-mail::button>

<x-mail::panel>
{{ __('For security, change your password after your first login (Profile → Password).') }}
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
