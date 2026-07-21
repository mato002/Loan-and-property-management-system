@props([
    /** Max columns on large screens: 2, 3, or 4 (always 2 on phone). */
    'cols' => 4,
])

@php
    $gridClasses = match ((int) $cols) {
        2 => 'grid grid-cols-2 gap-2 sm:gap-3 w-full min-w-0',
        3 => 'grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-3 w-full min-w-0',
        default => 'grid grid-cols-2 xl:grid-cols-4 gap-2 sm:gap-3 w-full min-w-0',
    };
@endphp

<div {{ $attributes->merge(['class' => $gridClasses]) }}>
    {{ $slot }}
</div>
