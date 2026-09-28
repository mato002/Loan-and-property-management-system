@props([
    'value' => null,
    'fallback' => '—',
    'class' => '',
    'linkClass' => 'inline-flex items-center gap-1.5 text-indigo-700 hover:text-indigo-500 hover:underline dark:text-indigo-300',
])

@php
    $rawPhone = trim((string) ($value ?? ''));
    $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
    $telHref = '';
    if ($digits !== '') {
        if (str_starts_with($rawPhone, '+')) {
            $telHref = '+'.$digits;
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $telHref = '+254'.substr($digits, 1);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $telHref = '+254'.$digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '254')) {
            $telHref = '+'.$digits;
        } else {
            $telHref = $digits;
        }
    }
@endphp

@if ($rawPhone !== '')
    @if ($telHref !== '')
        <a href="tel:{{ $telHref }}" data-turbo="false" data-row-ignore-click class="{{ $linkClass }} {{ $class }}" title="Call {{ $rawPhone }}">
            <i class="fa-solid fa-phone text-[11px] shrink-0" aria-hidden="true"></i>
            <span>{{ $rawPhone }}</span>
        </a>
    @else
        <span class="{{ $class }}">{{ $rawPhone }}</span>
    @endif
@else
    <span class="{{ $class }}">{{ $fallback }}</span>
@endif
