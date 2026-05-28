@props([
    'minWidth' => '720px',
])

@php
    $normalizedMinWidth = '720px';
    $raw = $minWidth ?? '720px';

    if (is_int($raw) || is_float($raw)) {
        $normalizedMinWidth = (int) $raw.'px';
    } else {
        $s = trim((string) $raw);
        if ($s === '') {
            $normalizedMinWidth = '720px';
        } elseif (preg_match('/^\d+(\.\d+)?$/', $s)) {
            $normalizedMinWidth = $s.'px';
        } elseif (preg_match('/^\d+(\.\d+)?(px|rem|%|em|vw|ch)$/i', $s)) {
            $normalizedMinWidth = $s;
        } else {
            $normalizedMinWidth = '720px';
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'property-table-scroll -mx-3 px-3 sm:-mx-4 sm:px-4 md:mx-0 md:px-0 overflow-x-auto w-full min-w-0']) }}>
    <div style="min-width: {{ $normalizedMinWidth }};" class="w-full">
        {{ $slot }}
    </div>
</div>
