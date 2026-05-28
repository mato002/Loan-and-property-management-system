@php
    $trend = (string) ($trend ?? 'flat');
    [$icon, $label, $class] = match ($trend) {
        'up', 'above' => ['fa-arrow-trend-up', 'Rising', 'text-red-600'],
        'down', 'below' => ['fa-arrow-trend-down', 'Falling', 'text-emerald-600'],
        default => ['fa-minus', 'Stable', 'text-slate-500'],
    };
@endphp
<span class="inline-flex items-center gap-1 text-xs font-medium {{ $class }}">
    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
    {{ $label }}
</span>
