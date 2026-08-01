@props([
    /** @var list<array{label: string, value: string, hint?: string|null, emphasis?: bool}> $stats */
    'stats' => [],
])

@if (count($stats) > 0)
    <div {{ $attributes->merge(['class' => 'property-compact-stat-strip flex flex-wrap items-center gap-x-2 gap-y-1 text-xs sm:text-sm text-slate-600 dark:text-slate-400 py-1']) }}>
        @foreach ($stats as $index => $stat)
            @if ($index > 0)
                <span class="text-slate-300 dark:text-slate-600 select-none" aria-hidden="true">·</span>
            @endif
            <span @class(['inline-flex items-baseline gap-1', ! empty($stat['emphasis']) ? 'text-rose-700 dark:text-rose-300' : ''])>
                <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $stat['value'] }}</span>
                <span>{{ $stat['label'] }}</span>
            </span>
        @endforeach
    </div>
@endif
