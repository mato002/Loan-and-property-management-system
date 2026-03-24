<x-mail::message>
# {{ __('Welcome, :name', ['name' => $landlordName]) }}

{{ __('Your property manager has created your landlord portal account. Use the credentials below to sign in.') }}

**{{ __('Email') }}:** {{ $email }}

**{{ __('Temporary password') }}:** `{{ $plainPassword }}`

<x-mail::button :url="$loginUrl">
{{ __('Open sign-in page') }}
</x-mail::button>

{{ __('Use the email and temporary password above on that page, then proceed to your landlord portal dashboard.') }}

<x-mail::button :url="$landlordHomeUrl">
{{ __('Landlord dashboard (after sign-in)') }}
</x-mail::button>

<x-mail::panel>
{{ __('For security, change your password after your first login (Profile → Password).') }}
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>

