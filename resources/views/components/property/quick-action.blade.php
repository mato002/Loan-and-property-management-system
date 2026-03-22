@props(['action', 'portal' => 'agent'])
@php
    $routeName = match ($portal) {
        'landlord' => 'property.landlord.quick_action.store',
        'tenant' => 'property.tenant.quick_action.store',
        default => 'property.quick_action.store',
    };
@endphp
<form method="post" action="{{ route($routeName) }}" class="inline-block w-full sm:w-auto align-middle">
    @csrf
    <input type="hidden" name="action_key" value="{{ $action }}" />
    <button type="submit" {{ $attributes }}>
        {{ $slot }}
    </button>
</form>
