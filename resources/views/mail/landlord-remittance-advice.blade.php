<x-mail::message>
# {{ __('Remittance advice') }}

{{ __('Hello :name,', ['name' => $landlordName]) }}

{{ __('Your rent remittance has been paid.') }}

**{{ __('Payout') }}:** #{{ $advice['payout_id'] }}  
**{{ __('Amount') }}:** {{ $advice['amount'] }}  
**{{ __('Property') }}:** {{ $advice['property'] }}  
**{{ __('Period') }}:** {{ $advice['period'] }}  
**{{ __('Paid at') }}:** {{ $advice['paid_at'] }}  
@if (! empty($advice['mpesa_txn']))
**{{ __('M-Pesa reference') }}:** {{ $advice['mpesa_txn'] }}  
@endif
@if (! empty($advice['phone']))
**{{ __('Disbursed to') }}:** {{ $advice['phone'] }}  
@endif

{{ __('Thank you.') }}

</x-mail::message>
