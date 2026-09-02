@props([
    /** @var list<string> $columns */
    'columns' => [],
    /** @var list<list<string>> $rows */
    'rows' => [],
    /** @var list<string>|null $rowFilters */
    'rowFilters' => null,
    /** @var list<string>|null $rowTones Optional per-row alert tones (same length as rows) */
    'rowTones' => null,
    /** @var list<array<string, mixed>>|null $columnConfig */
    'columnConfig' => null,
    'emptyTitle' => 'No records yet',
    'emptyHint' => 'Data will appear here once available.',
])

@php
    use App\Support\Property\ResponsiveTableColumns;

    $customRowFilters = is_array($rowFilters ?? null) && count($rowFilters) === count($rows);
    $rowToneAllowlist = \App\Support\Property\UnitListPresentation::allowedTones();
    $customRowTones = is_array($rowTones ?? null) && count($rowTones) === count($rows);
    $configs = ResponsiveTableColumns::normalize(
        is_array($columnConfig) && count($columnConfig) > 0
            ? $columnConfig
            : $columns
    );
    $colCount = count($configs);

    $indexByFlag = static function (string $flag) use ($configs): ?int {
        foreach ($configs as $idx => $cfg) {
            if (! empty($cfg[$flag])) {
                return $idx;
            }
        }

        return null;
    };

    $primaryIdx = $indexByFlag('is_primary') ?? 0;
    $subtitleIdx = $indexByFlag('is_subtitle');
    $bulkIdx = $indexByFlag('is_bulk_select');
    $actionIdx = $indexByFlag('is_action') ?? ($colCount > 0 ? $colCount - 1 : null);

    $metaIndices = collect($configs)
        ->map(fn ($cfg, $idx) => ['idx' => $idx, 'cfg' => $cfg])
        ->filter(function (array $item) use ($primaryIdx, $subtitleIdx, $bulkIdx, $actionIdx) {
            $idx = $item['idx'];
            $cfg = $item['cfg'];
            if ($idx === $primaryIdx || $idx === $subtitleIdx || $idx === $bulkIdx || $idx === $actionIdx) {
                return false;
            }
            if (! empty($cfg['hide_on_mobile']) || ! empty($cfg['is_amount']) || ! empty($cfg['is_status'])) {
                return false;
            }

            return true;
        })
        ->sortBy(fn (array $item) => (int) ($item['cfg']['priority'] ?? $item['idx'] + 1))
        ->values();

    $amountIndices = collect($configs)
        ->map(fn ($cfg, $idx) => ['idx' => $idx, 'cfg' => $cfg])
        ->filter(fn (array $item) => ! empty($item['cfg']['is_amount']) && empty($item['cfg']['hide_on_mobile']))
        ->sortBy(fn (array $item) => (int) ($item['cfg']['priority'] ?? $item['idx'] + 1))
        ->values();

    $statusIndices = collect($configs)
        ->map(fn ($cfg, $idx) => ['idx' => $idx, 'cfg' => $cfg])
        ->filter(fn (array $item) => ! empty($item['cfg']['is_status']) && empty($item['cfg']['hide_on_mobile']))
        ->values();

    $mobileLabel = static function (array $cfg): string {
        $label = trim((string) ($cfg['mobile_label'] ?? $cfg['label'] ?? ''));

        return $label;
    };

    $renderCell = static function ($cell): string {
        if ($cell instanceof \Illuminate\Support\HtmlString) {
            return (string) $cell;
        }

        return (string) $cell;
    };
@endphp

<div {{ $attributes->merge(['class' => 'md:hidden space-y-2.5 w-full min-w-0']) }} data-mobile-record-list>
    @if (count($rows) === 0)
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 px-4 py-10 text-center">
            <p class="font-medium text-slate-700 dark:text-slate-200">{{ $emptyTitle }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $emptyHint }}</p>
        </div>
    @else
        @foreach ($rows as $rowIndex => $row)
            @php
                $__filterText = $customRowFilters
                    ? mb_strtolower((string) $rowFilters[$rowIndex])
                    : mb_strtolower(implode(' ', array_map(static fn ($c) => strip_tags((string) $c), $row)));
                $__rowHref = null;
                foreach ($row as $__rowCell) {
                    $__cellHtml = (string) $__rowCell;
                    if (preg_match('/<a[^>]+href=["\\\']([^"\\\']+)["\\\']/i', $__cellHtml, $__hrefMatch)) {
                        $__rowHref = $__hrefMatch[1] ?? null;
                        if (is_string($__rowHref) && trim($__rowHref) !== '') {
                            break;
                        }
                    }
                }

                $primaryCell = $row[$primaryIdx] ?? '';
                $subtitleCell = $subtitleIdx !== null ? ($row[$subtitleIdx] ?? '') : '';
                $bulkCell = $bulkIdx !== null ? ($row[$bulkIdx] ?? '') : '';
                $actionCell = $actionIdx !== null ? ($row[$actionIdx] ?? '') : '';
                $__rowTone = $customRowTones ? (string) ($rowTones[$rowIndex] ?? '') : '';
                $__rowToneClass = in_array($__rowTone, $rowToneAllowlist, true)
                    ? 'property-row-alert-'.$__rowTone
                    : '';
            @endphp
            <article
                class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/90 shadow-sm overflow-hidden {{ $__rowHref ? 'active:bg-slate-50 dark:active:bg-slate-800/60' : '' }} {{ $__rowToneClass }}"
                data-filter-text="{{ e($__filterText) }}"
                @if ($__rowHref)
                    data-row-href="{{ $__rowHref }}"
                    tabindex="0"
                    role="link"
                @endif
            >
                <div class="px-3.5 py-3 space-y-2.5">
                    <div class="flex items-start gap-3">
                        @if ($bulkIdx !== null && trim(strip_tags($renderCell($bulkCell))) !== '')
                            <div class="shrink-0 pt-0.5" data-row-ignore-click>
                                {!! $renderCell($bulkCell) !!}
                            </div>
                        @endif
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="text-sm font-semibold text-slate-900 dark:text-white leading-snug min-w-0 [&_a]:text-emerald-700 [&_a]:dark:text-emerald-400 [&_a]:font-semibold break-words">
                                    {!! $renderCell($primaryCell) !!}
                                </div>
                                <div class="shrink-0 flex flex-col items-end gap-1 text-right max-w-[45%]">
                                    @foreach ($amountIndices as $amountItem)
                                        @php
                                            $amountCell = $row[$amountItem['idx']] ?? '';
                                            $amountLabel = $mobileLabel($amountItem['cfg']);
                                        @endphp
                                        @if (trim(strip_tags($renderCell($amountCell))) !== '')
                                            <div class="text-xs font-semibold tabular-nums text-slate-900 dark:text-white">
                                                @if ($amountLabel !== '')
                                                    <span class="block text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $amountLabel }}</span>
                                                @endif
                                                <span class="[&_a]:text-emerald-700">{!! $renderCell($amountCell) !!}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                    @foreach ($statusIndices as $statusItem)
                                        @php $statusCell = $row[$statusItem['idx']] ?? ''; @endphp
                                        @if (trim(strip_tags($renderCell($statusCell))) !== '')
                                            <div class="[&_span]:whitespace-nowrap">{!! $renderCell($statusCell) !!}</div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @if ($subtitleIdx !== null && trim(strip_tags($renderCell($subtitleCell))) !== '')
                                <p class="text-xs text-slate-600 dark:text-slate-300 leading-snug break-words [&_a]:text-emerald-700 [&_a]:font-semibold">
                                    {!! $renderCell($subtitleCell) !!}
                                </p>
                            @endif
                        </div>
                    </div>

                    @if ($metaIndices->isNotEmpty())
                        <dl class="grid gap-1.5 border-t border-slate-100 dark:border-slate-700/80 pt-2.5">
                            @foreach ($metaIndices as $metaItem)
                                @php
                                    $metaIdx = $metaItem['idx'];
                                    $metaCfg = $metaItem['cfg'];
                                    $metaCell = $row[$metaIdx] ?? '';
                                    $metaLabel = $mobileLabel($metaCfg);
                                @endphp
                                @if (trim(strip_tags($renderCell($metaCell))) !== '')
                                    <div class="flex items-start justify-between gap-3 text-xs">
                                        @if ($metaLabel !== '')
                                            <dt class="shrink-0 text-slate-500 dark:text-slate-400 font-medium">{{ $metaLabel }}</dt>
                                        @endif
                                        <dd class="text-right text-slate-700 dark:text-slate-200 min-w-0 break-words [&_a]:text-emerald-700 [&_a]:font-semibold {{ $metaLabel === '' ? 'w-full text-left' : '' }}">
                                            {!! $renderCell($metaCell) !!}
                                        </dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @endif
                </div>

                @if ($actionIdx !== null && trim(strip_tags($renderCell($actionCell))) !== '')
                    <div
                        class="px-3.5 py-2.5 border-t border-slate-100 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-900/40 flex flex-wrap items-center gap-2 [&_button]:min-h-[44px] [&_a]:min-h-[44px] [&_summary]:min-h-[44px] [&_summary]:inline-flex [&_summary]:items-center [&_details]:w-full sm:[&_details]:w-auto"
                        data-row-ignore-click
                    >
                        {!! $renderCell($actionCell) !!}
                    </div>
                @endif
            </article>
        @endforeach
    @endif
</div>
