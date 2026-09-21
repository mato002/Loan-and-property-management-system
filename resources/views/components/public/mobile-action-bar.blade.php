@props([
    'unit',
    'whatsAppDigits' => '',
    'phoneHref' => '',
    'whatsappUrl' => '',
])

@php
    $applyUrl = route('public.apply', ['property_unit' => $unit->id]);
    $waUrl = $whatsappUrl !== '' ? $whatsappUrl : ($whatsAppDigits ? 'https://wa.me/'.$whatsAppDigits : '');
    $contactUrl = route('public.contact', ['property_unit' => $unit->id, 'intent' => 'viewing']);
@endphp

<div {{ $attributes->merge(['class' => 'public-mobile-sticky-bar lg:hidden']) }}>
    @if ($waUrl)
        <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="public-btn !min-h-[2.5rem] !text-xs bg-[#25D366] text-white !rounded-lg">WhatsApp</a>
    @elseif ($phoneHref)
        <a href="tel:{{ $phoneHref }}" class="public-btn !min-h-[2.5rem] !text-xs public-btn-secondary !rounded-lg">Call</a>
    @endif
    <a href="{{ $contactUrl }}" class="public-btn public-btn-secondary !min-h-[2.5rem] !text-xs !rounded-lg">Viewing</a>
    <a href="{{ $applyUrl }}" class="public-btn public-btn-primary !min-h-[2.5rem] !text-xs !rounded-lg">Apply</a>
</div>
