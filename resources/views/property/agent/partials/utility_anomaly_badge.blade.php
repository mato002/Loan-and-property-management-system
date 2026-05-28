@php
    $anomaly = $anomaly ?? [];
    $severity = (string) ($anomaly['severity'] ?? 'info');
    $type = (string) ($anomaly['type'] ?? 'unknown');
    $tone = match ($severity) {
        'critical' => 'bg-red-100 text-red-800 border-red-200',
        'warning' => 'bg-amber-100 text-amber-900 border-amber-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200',
    };
    $icon = match ($type) {
        'spike', 'excessive_consumption' => 'fa-arrow-trend-up',
        'abnormal_drop', 'possible_tampering' => 'fa-arrow-trend-down',
        'zero_usage' => 'fa-circle-minus',
        'possible_leak' => 'fa-droplet',
        'missing_reading' => 'fa-calendar-xmark',
        'estimated_abuse' => 'fa-eye-slash',
        default => 'fa-circle-info',
    };
@endphp
<span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $tone }}">
    <i class="fa-solid {{ $icon }} text-[10px]" aria-hidden="true"></i>
    {{ $anomaly['label'] ?? ucfirst(str_replace('_', ' ', $type)) }}
</span>
