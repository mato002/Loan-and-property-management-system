@props([
    /** @var list<array{label: string, x: float|int, y: float|int, jobs?: int}> $points */
    'points' => [],
    'title' => null,
    'xLabel' => 'Completion rate %',
    'yLabel' => 'Quoted volume (K KES)',
    'height' => 260,
])

@php
    $w = 420;
    $h = (int) $height;
    $pad = 40;
    $iw = $w - $pad * 2;
    $ih = $h - $pad * 2;
    $maxX = 100;
    $maxY = 1;
    foreach ($points as $p) {
        $maxY = max($maxY, (float) ($p['y'] ?? 0));
    }
@endphp

<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm w-full min-w-0">
    @if ($title)
        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $title }}</p>
    @endif
    <p class="mt-1 text-xs text-slate-500">{{ $xLabel }} · {{ $yLabel }}</p>
    <div class="mt-4 w-full overflow-x-auto">
        @if (count($points) > 0)
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-auto min-h-[{{ $h }}px]" role="img" aria-label="{{ $title ?? 'Scatter chart' }}">
                <line x1="{{ $pad }}" y1="{{ $h - $pad }}" x2="{{ $w - $pad }}" y2="{{ $h - $pad }}" class="stroke-slate-200 dark:stroke-slate-600" stroke-width="1" />
                <line x1="{{ $pad }}" y1="{{ $pad }}" x2="{{ $pad }}" y2="{{ $h - $pad }}" class="stroke-slate-200 dark:stroke-slate-600" stroke-width="1" />
                @foreach ($points as $p)
                    @php
                        $cx = $pad + ($iw * min(100, max(0, (float) ($p['x'] ?? 0))) / $maxX);
                        $cy = $h - $pad - ($ih * (float) ($p['y'] ?? 0) / $maxY);
                        $r = isset($p['jobs']) ? min(14, 6 + (int) $p['jobs'] / 8) : 8;
                    @endphp
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" class="fill-violet-500/85 dark:fill-violet-400/75">
                        <title>{{ ($p['label'] ?? '') }} — jobs: {{ $p['jobs'] ?? '—' }}</title>
                    </circle>
                @endforeach
            </svg>
        @else
            <p class="text-sm text-slate-500 text-center py-12">No vendor job history yet.</p>
        @endif
    </div>
</div>
