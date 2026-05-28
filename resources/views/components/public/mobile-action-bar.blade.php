@props([
    'unit',
    'whatsAppDigits' => '',
    'phoneHref' => '',
])

@php
    $applyUrl = route('public.apply', ['property_unit' => $unit->id]);
    $waMessage = rawurlencode('Hi, I am interested in '.$unit->property->name.' — Unit '.$unit->label);
    $waUrl = $whatsAppDigits ? 'https://wa.me/'.$whatsAppDigits.'?text='.$waMessage : '#';
    $contactUrl = route('public.contact', ['property_unit' => $unit->id, 'intent' => 'viewing']);
@endphp

<div {{ $attributes->merge(['class' => 'public-mobile-sticky-bar lg:hidden']) }}>
    @if ($whatsAppDigits)
        <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="public-btn !min-h-[2.5rem] !text-xs bg-[#25D366] text-white !rounded-lg">WhatsApp</a>
    @endif
    <a href="{{ $contactUrl }}" class="public-btn public-btn-secondary !min-h-[2.5rem] !text-xs !rounded-lg">Viewing</a>
    <a href="{{ $applyUrl }}" class="public-btn public-btn-primary !min-h-[2.5rem] !text-xs !rounded-lg">Apply</a>
</div>
