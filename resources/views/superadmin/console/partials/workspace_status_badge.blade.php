@php
    $tone = $status['tone'] ?? 'orange';
    $classes = match ($tone) {
        'green' => 'bg-emerald-100 text-emerald-900 ring-emerald-200',
        'red' => 'bg-rose-100 text-rose-900 ring-rose-200',
        default => 'bg-amber-100 text-amber-900 ring-amber-200',
    };
@endphp
<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset {{ $classes }}">
    {{ $status['label'] ?? 'Pending' }}
</span>
