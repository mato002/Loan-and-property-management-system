@props([
    'title',
    'columns' => [],
    'rows' => [],
    'columnConfig' => null,
    'emptyTitle' => 'No records yet',
    'emptyHint' => 'Data will appear here once available.',
    'tableMinWidth' => '720px',
    'rowFilters' => null,
    'rowExpansions' => null,
])

@php
    use App\Support\Property\ResponsiveTableColumns;

    $resolvedColumnConfig = ResponsiveTableColumns::normalize(
        is_array($columnConfig) && count($columnConfig) > 0
            ? $columnConfig
            : $columns
    );
@endphp

<div {{ $attributes->merge(['class' => 'property-erp-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0 overflow-visible']) }}>
    <div class="px-3 sm:px-4 py-3 border-b border-slate-100 dark:border-slate-700/80">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    </div>

    <div class="w-full min-w-0">
        <x-property.responsive.table-wrapper :min-width="$tableMinWidth">
            <table class="property-erp-table w-full table-auto border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_th]:dark:border-slate-700 [&_td]:border [&_td]:border-slate-200 [&_td]:dark:border-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        @foreach ($columns as $col)
                            <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                @forelse ($rows as $rowIndex => $row)
                    @php
                        $filterText = is_array($rowFilters ?? null) && isset($rowFilters[$rowIndex])
                            ? mb_strtolower((string) $rowFilters[$rowIndex])
                            : mb_strtolower(implode(' ', array_map(static fn ($c) => strip_tags((string) $c), $row)));
                        $rowHref = null;
                        foreach ($row as $cell) {
                            $cellHtml = (string) $cell;
                            if (preg_match('/<a[^>]+href=["\\\']([^"\\\']+)["\\\']/i', $cellHtml, $hrefMatch)) {
                                $rowHref = $hrefMatch[1] ?? null;
                                if (is_string($rowHref) && trim($rowHref) !== '') {
                                    break;
                                }
                            }
                        }
                        $rowTone = \App\Support\Property\WorkspaceRowAlert::inferFromRow(is_array($row) ? $row : []);
                        $rowToneClass = \App\Support\Property\WorkspaceRowAlert::trClass($rowTone);
                        $rowToneStyle = \App\Support\Property\WorkspaceRowAlert::cellStyle($rowTone);
                        $rowExpansion = is_array($rowExpansions ?? null) ? ($rowExpansions[$rowIndex] ?? null) : null;
                    @endphp
                    <tbody @if ($rowExpansion) x-data="{ open: false }" @endif>
                        <tr
                            @class([
                                'border-t border-slate-100 dark:border-slate-700/80',
                                'hover:bg-slate-50/80 dark:hover:bg-slate-800/40' => $rowToneStyle === '',
                                'cursor-pointer' => (bool) $rowHref,
                                $rowToneClass => $rowToneClass !== '',
                            ])
                            data-filter-text="{{ e($filterText) }}"
                            @if ($rowToneStyle !== '')
                                data-row-tone="{{ $rowTone }}"
                            @endif
                            @if ($rowHref)
                                data-row-href="{{ $rowHref }}"
                                tabindex="0"
                                role="link"
                            @endif
                        >
                            @foreach ($row as $cell)
                                @php
                                    $cellHtml = (string) $cell;
                                    $containsDropdown = str_contains(strtolower($cellHtml), '<details');
                                @endphp
                                <td
                                    @class([
                                        'px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 align-top text-xs sm:text-sm',
                                        'whitespace-normal overflow-visible' => $containsDropdown,
                                        'whitespace-normal break-words min-w-[5rem]' => ! $containsDropdown,
                                    ])
                                    @if ($rowToneStyle !== '')
                                        style="{{ $rowToneStyle }}"
                                    @endif
                                >
                                    @if ($cell instanceof \Illuminate\Support\HtmlString)
                                        {!! $cell !!}
                                    @else
                                        {{ $cell }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @if ($rowExpansion)
                            <tr x-show="open" x-cloak class="border-t border-slate-100 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900/40">
                                <td colspan="{{ max(count($columns), 1) }}" class="px-3 sm:px-4 py-3" data-row-ignore-click>
                                    @if ($rowExpansion instanceof \Illuminate\Support\HtmlString)
                                        {!! $rowExpansion !!}
                                    @else
                                        {!! $rowExpansion !!}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="{{ max(count($columns), 1) }}" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                <p class="font-medium text-slate-700 dark:text-slate-200">{{ $emptyTitle }}</p>
                                <p class="text-sm mt-1">{{ $emptyHint }}</p>
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </x-property.responsive.table-wrapper>
    </div>
</div>
