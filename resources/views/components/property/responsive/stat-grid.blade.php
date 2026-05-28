@props([
    /** When true, tighter card padding and gaps (same column breakpoints as default). */
    'dense' => false,
])

<div {{ $attributes->merge(['class' => $dense ? 'stat-grid stat-grid-dense' : 'stat-grid']) }}>
    {{ $slot }}
</div>
