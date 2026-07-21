<x-mail::message>
# {{ __('Welcome, :name', ['name' => $landlordName]) }}

{{ __('Your property manager has created your landlord portal account. Use the credentials below to sign in.') }}

@if ($email)
**{{ __('Email') }}:** {{ $email }}
@endif
@if ($phone)
**{{ __('Phone') }}:** {{ $phone }}
@endif

**{{ __('Temporary password') }}:** `{{ $plainPassword }}`

<x-mail::button :url="$loginUrl">
{{ __('Open sign-in page') }}
</x-mail::button>

@if ($email && $phone)
{{ __('Use your email or phone number and the temporary password above on that page, then proceed to your landlord portal dashboard.') }}
@elseif ($phone)
{{ __('Use your phone number and the temporary password above on that page, then proceed to your landlord portal dashboard.') }}
@else
{{ __('Use the email and temporary password above on that page, then proceed to your landlord portal dashboard.') }}
@endif

<x-mail::button :url="$landlordHomeUrl">
{{ __('Landlord dashboard (after sign-in)') }}
</x-mail::button>

<x-mail::panel>
{{ __('For security, change your password after your first login (Profile → Password).') }}
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>

