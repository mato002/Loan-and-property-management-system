@props([
    /** @var list<array{label: string, value: float|int}> $series */
    'series' => [],
    'title' => null,
    'valueFormat' => 'number', // number | kes
    'height' => 220,
])

@php
    $values = array_map(static fn ($r) => abs((float) ($r['value'] ?? 0)), $series);
    $max = max($values + [1]);
@endphp

<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm w-full min-w-0">
    @if ($title)
        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $title }}</p>
    @endif
    <div class="mt-4 w-full overflow-x-auto">
        <svg
            viewBox="0 0 {{ max(320, count($series) * 56) }} {{ (int) $height }}"
            class="w-full h-auto min-h-[{{ (int) $height }}px]"
            role="img"
            aria-label="{{ $title ?? 'Bar chart' }}"
        >
            @foreach ($series as $idx => $row)
                @php
                    $v = abs((float) ($row['value'] ?? 0));
                    $h = $max > 0 ? (int) round((($height - 48) * $v) / $max) : 0;
                    $x = 16 + $idx * 56;
                    $bw = 36;
                    $y = (int) $height - 28 - $h;
                    $display = $valueFormat === 'kes'
                        ? \App\Services\Property\PropertyMoney::kes($row['value'] ?? 0)
                        : number_format((float) ($row['value'] ?? 0), 0);
                @endphp
                <rect
                    x="{{ $x }}"
                    y="{{ $y }}"
                    width="{{ $bw }}"
                    height="{{ max(1, $h) }}"
                    rx="4"
                    class="fill-blue-500/90 dark:fill-blue-400/80"
                />
                <text
                    x="{{ $x + $bw / 2 }}"
                    y="{{ (int) $height - 8 }}"
                    text-anchor="middle"
                    class="fill-slate-500 dark:fill-slate-400"
                    style="font-size: 10px"
                >{{ \Illuminate\Support\Str::limit((string) ($row['label'] ?? ''), 6, '') }}</text>
            @endforeach
        </svg>
    </div>
    @if (count($series) === 0)
        <p class="text-sm text-slate-500 text-center py-8">No data for this period.</p>
    @endif
</div>
