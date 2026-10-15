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
            @php
                $statClasses = ['inline-flex items-baseline gap-1', ! empty($stat['emphasis']) ? 'text-rose-700 dark:text-rose-300' : ''];
                if (! empty($stat['href'])) {
                    $statClasses[] = 'rounded-sm hover:text-blue-700 dark:hover:text-blue-300 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500';
                }
            @endphp
            @if (! empty($stat['href']))
                <a href="{{ $stat['href'] }}" @class($statClasses)>
                    <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $stat['value'] }}</span>
                    <span>{{ $stat['label'] }}</span>
                </a>
            @else
                <span @class($statClasses)>
                    <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $stat['value'] }}</span>
                    <span>{{ $stat['label'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
@endif
