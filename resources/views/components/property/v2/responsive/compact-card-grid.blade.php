@props([
    /** Large panels: 1 column on mobile; this many columns from lg breakpoint upward. */
    'lgCols' => 2,
])

@php
    $lgCols = max(1, min(3, (int) $lgCols));
    $gridClass = match ($lgCols) {
        3 => 'compact-card-grid compact-card-grid--lg-3',
        1 => 'compact-card-grid compact-card-grid--lg-1',
        default => 'compact-card-grid compact-card-grid--lg-2',
    };
@endphp

<div {{ $attributes->merge(['class' => $gridClass]) }}>
    {{ $slot }}
</div>
