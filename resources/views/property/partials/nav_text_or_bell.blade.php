@php
    $navBadge = $badge ?? null;
    $navBadgeSize = $size ?? 'text-[11px]';
@endphp
@if (is_numeric($navBadge))
    @include('property.partials.maintenance_request_bell', ['count' => (int) $navBadge])
@elseif (! empty($navBadge))
    <span class="shrink-0 rounded px-1.5 py-0.5 {{ $navBadgeSize }} font-bold uppercase tracking-wide bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-400/30">{{ $navBadge }}</span>
@endif
