@props([
    'value' => null,
    'fallback' => '—',
    'class' => '',
    'linkClass' => 'text-indigo-600 hover:text-indigo-500 hover:underline',
])

@php
    $rawPhone = trim((string) ($value ?? ''));
    $telDigits = preg_replace('/\D+/', '', $rawPhone) ?? '';
    if ($telDigits !== '' && str_starts_with($rawPhone, '+')) {
        $telDigits = '+'.$telDigits;
    }
@endphp

@if ($rawPhone !== '')
    @if ($telDigits !== '')
        <a href="tel:{{ $telDigits }}" class="{{ $linkClass }} {{ $class }}" title="Call {{ $rawPhone }}">
            {{ $rawPhone }}
        </a>
    @else
        <span class="{{ $class }}">{{ $rawPhone }}</span>
    @endif
@else
    <span class="{{ $class }}">{{ $fallback }}</span>
@endif
