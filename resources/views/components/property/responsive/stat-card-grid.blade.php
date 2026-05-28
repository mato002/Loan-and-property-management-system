@props([
    /** @var list<array{label: string, value: string, hint?: string|null, emphasis?: bool, tone?: string}> $stats */
    'stats' => [],
    'dense' => false,
])

@if (count($stats) > 0)
    <div {{ $attributes->merge(['class' => $dense ? 'stat-grid stat-grid-dense' : 'stat-grid']) }}>
        @foreach ($stats as $s)
            @php
                $emphasis = ! empty($s['emphasis']);
                $tone = (string) ($s['tone'] ?? 'emerald');
                $emphasisBorder = match ($tone) {
                    'rose' => 'border-rose-200 bg-gradient-to-br from-rose-50 to-white',
                    'amber' => 'border-amber-200 bg-gradient-to-br from-amber-50 to-white',
                    default => 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white',
                };
                $emphasisValue = match ($tone) {
                    'rose' => 'text-rose-800',
                    'amber' => 'text-amber-800',
                    default => 'text-emerald-800',
                };
            @endphp
            <div @class([
                'stat-card',
                $emphasis ? $emphasisBorder : '',
            ])>
                <p class="stat-card-label">{{ $s['label'] }}</p>
                <p @class([
                    'stat-card-value',
                    $emphasis ? $emphasisValue : '',
                ])>{{ $s['value'] }}</p>
                @if (! empty($s['hint'] ?? null))
                    <p class="stat-card-hint max-md:line-clamp-2">{{ $s['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
