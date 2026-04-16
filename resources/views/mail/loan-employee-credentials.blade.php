<x-mail::message>
# {{ __('Welcome, :name', ['name' => $employeeName]) }}

{{ __('Your employee account was created and granted access to the Loan module.') }}

**{{ __('Role') }}:** {{ ucfirst($role) }}

**{{ __('Email') }}:** {{ $email }}

**{{ __('Temporary password') }}:** `{{ $plainPassword }}`

<x-mail::button :url="$loginUrl">
{{ __('Open sign-in page') }}
</x-mail::button>

{{ __('After login, go to your loan workspace dashboard:') }}

<x-mail::button :url="$loanHomeUrl">
{{ __('Loan dashboard') }}
</x-mail::button>

<x-mail::panel>
{{ __('For security, change your password immediately after first login.') }}
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>

