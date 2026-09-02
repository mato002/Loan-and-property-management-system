@props([
    /** @var list<array{label: string, value: string, hint?: string|null}> $stats */
    'stats' => [],
    /** @var list<string> $columns */
    'columns' => [],
    /** @var list<list<string>> $tableRows */
    'tableRows' => [],
    'emptyTitle' => 'No records yet',
    'emptyHint' => '',
    'showSearch' => true,
])

@if (count($stats) > 0)
    <x-property.responsive.stat-card-grid :stats="$stats" class="mb-3" />
@endif

@if (isset($toolbar) && ! $toolbar->isEmpty())
    <div class="print-hide mb-3 w-full min-w-0 [&_form]:flex [&_form]:flex-wrap [&_form]:items-end [&_form]:gap-2">
        {{ $toolbar }}
    </div>
@endif

@if (isset($actions) && ! $actions->isEmpty())
    <div class="print-hide flex flex-wrap gap-2 mb-3 w-full min-w-0 [&>a]:inline-flex [&>a]:items-center [&>a]:justify-center [&>a]:rounded-xl [&>a]:px-3 [&>a]:py-2 [&>a]:text-sm">
        {{ $actions }}
    </div>
@endif

@if (count($columns) > 0)
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0 overflow-x-auto">
        @if ($showSearch)
            <div class="p-3 border-b border-slate-100 dark:border-slate-700 print-hide">
                <input
                    type="search"
                    data-table-filter="parent"
                    autocomplete="off"
                    placeholder="Search…"
                    class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
                />
            </div>
        @endif
        <x-property.responsive.table-wrapper min-width="720px">
            <table class="property-erp-table w-full table-auto border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        @foreach ($columns as $col)
                            <th class="px-3 py-3 whitespace-normal break-words">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tableRows as $row)
                        @php
                            $__filterText = mb_strtolower(implode(' ', array_map(static fn ($c) => strip_tags((string) $c), $row)));
                            $__rowToneClass = \App\Support\Property\WorkspaceRowAlert::trClass(
                                \App\Support\Property\WorkspaceRowAlert::inferFromRow(is_array($row) ? $row : [])
                            );
                        @endphp
                        <tr class="border-t border-slate-100 dark:border-slate-700/80 {{ $__rowToneClass }}" data-filter-text="{{ e($__filterText) }}">
                            @foreach ($row as $cell)
                                <td class="px-3 py-3 text-slate-700 dark:text-slate-200 align-top whitespace-normal break-words">
                                    @if ($cell instanceof \Illuminate\Support\HtmlString)
                                        {!! $cell !!}
                                    @else
                                        {{ $cell }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-4 py-10 text-center">
                                <p class="font-medium text-slate-700 dark:text-slate-200">{{ $emptyTitle }}</p>
                                @if ($emptyHint)
                                    <p class="text-sm text-slate-500 mt-1">{{ $emptyHint }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-property.responsive.table-wrapper>
    </div>
@endif

@if (isset($slot) && ! $slot->isEmpty())
    <div class="mt-3 w-full min-w-0">
        {{ $slot }}
    </div>
@endif
