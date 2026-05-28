@props([
    /** @var list<array{label: string, a: float|int, b: float|int}> $series */
    'series' => [],
    'title' => null,
    'labelA' => 'Series A',
    'labelB' => 'Series B',
    'height' => 240,
])

@php
    $w = max(400, count($series) * 72);
    $h = (int) $height;
    $padT = 24;
    $padB = 36;
    $padL = 8;
    $padR = 8;
    $innerH = $h - $padT - $padB;
    $innerW = $w - $padL - $padR;
    $max = 1;
    foreach ($series as $row) {
        $max = max($max, abs((float) ($row['a'] ?? 0)), abs((float) ($row['b'] ?? 0)));
    }
    $pointsA = [];
    $pointsB = [];
    $n = max(1, count($series) - 1);
    foreach ($series as $idx => $row) {
        $x = $padL + ($n === 0 ? $innerW / 2 : ($innerW * $idx / $n));
        $ya = $padT + $innerH - ($innerH * (float) ($row['a'] ?? 0) / $max);
        $yb = $padT + $innerH - ($innerH * (float) ($row['b'] ?? 0) / $max);
        $pointsA[] = round($x, 2).','.round($ya, 2);
        $pointsB[] = round($x, 2).','.round($yb, 2);
    }
@endphp

<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm w-full min-w-0">
    @if ($title)
        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $title }}</p>
    @endif
    <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600 dark:text-slate-400">
        <span class="inline-flex items-center gap-1.5"><span class="w-3 h-0.5 bg-emerald-500 rounded"></span> {{ $labelA }}</span>
        <span class="inline-flex items-center gap-1.5"><span class="w-3 h-0.5 bg-amber-500 rounded"></span> {{ $labelB }}</span>
    </div>
    <div class="mt-4 w-full overflow-x-auto">
        @if (count($series) > 0)
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-auto min-h-[{{ $h }}px]" role="img" aria-label="{{ $title ?? 'Line chart' }}">
                <line x1="{{ $padL }}" y1="{{ $padT + $innerH }}" x2="{{ $w - $padR }}" y2="{{ $padT + $innerH }}" class="stroke-slate-200 dark:stroke-slate-600" stroke-width="1" />
                <polyline
                    fill="none"
                    stroke="rgb(16 185 129)"
                    stroke-width="2"
                    points="{{ implode(' ', $pointsA) }}"
                />
                <polyline
                    fill="none"
                    stroke="rgb(245 158 11)"
                    stroke-width="2"
                    points="{{ implode(' ', $pointsB) }}"
                />
                @foreach ($series as $idx => $row)
                    @php
                        $x = $padL + ($n === 0 ? $innerW / 2 : ($innerW * $idx / $n));
                    @endphp
                    <text
                        x="{{ $x }}"
                        y="{{ $h - 10 }}"
                        text-anchor="middle"
                        class="fill-slate-500 dark:fill-slate-400"
                        style="font-size: 10px"
                    >{{ \Illuminate\Support\Str::limit((string) ($row['label'] ?? ''), 7, '') }}</text>
                @endforeach
            </svg>
        @else
            <p class="text-sm text-slate-500 text-center py-12">No trend data yet.</p>
        @endif
    </div>
</div>
