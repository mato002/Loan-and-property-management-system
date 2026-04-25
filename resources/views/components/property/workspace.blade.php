@props([
    'title',
    'subtitle' => null,
    'backRoute' => null,
    'backLabel' => '← Back',
    /** @var list<array{label: string, value: string, hint?: string|null}> $stats */
    'stats' => [],
    /** @var list<string> $columns */
    'columns' => [],
    /** @var list<list<string>> $tableRows */
    'tableRows' => [],
    /** @var list<string>|null $tableRowFilters Optional per-row filter text (same length as tableRows) for client-side search */
    'tableRowFilters' => null,
    'showSearch' => true,
    'emptyTitle' => 'No records yet',
    'emptyHint' => 'Data will load here once this module is connected to your database.',
])

@php
    $hasToolbar = isset($toolbar) && ! $toolbar->isEmpty();
    $hasTable = count($columns) > 0;
    $slotHasContent = isset($slot) && ! $slot->isEmpty();
    $customRowFilters = is_array($tableRowFilters ?? null)
        && count($tableRowFilters) === count($tableRows);
    $canShowDefaultSearch = (bool) $showSearch && $hasTable;
    $printableFilters = collect(request()->query())
        ->except(['export', 'page'])
        ->filter(static fn ($value) => ! is_null($value) && $value !== '');
    $printBrandName = \App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name', 'Property Management System');
    $printBrandLogo = trim((string) \App\Models\PropertyPortalSetting::getValue('company_logo_url', ''));
    $printContactParts = collect([
        \App\Models\PropertyPortalSetting::getValue('contact_phone', ''),
        \App\Models\PropertyPortalSetting::getValue('contact_email_primary', ''),
        \App\Models\PropertyPortalSetting::getValue('contact_address', ''),
        \App\Models\PropertyPortalSetting::getValue('contact_reg_no', ''),
    ])->filter(static fn ($value) => ! is_null($value) && trim((string) $value) !== '');
@endphp

<x-property-layout>
    <x-slot name="header">{{ $title }}</x-slot>

    <x-property.page :title="$title" :subtitle="$subtitle">
        <div class="property-print-only mb-4 border-b border-slate-300 pb-3">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-lg font-semibold text-slate-900">{{ $printBrandName }}</div>
                    @if ($printContactParts->isNotEmpty())
                        <div class="mt-1 text-xs text-slate-600">{{ $printContactParts->implode(' | ') }}</div>
                    @endif
                </div>
                @if ($printBrandLogo !== '')
                    <img src="{{ $printBrandLogo }}" alt="{{ $printBrandName }} logo" class="max-h-12 w-auto object-contain" />
                @endif
            </div>
            <div class="mt-3 text-xl font-semibold text-slate-900">{{ $title }}</div>
            @if (! empty($subtitle))
                <div class="mt-1 text-sm text-slate-700">{{ $subtitle }}</div>
            @endif
            <div class="mt-2 text-xs text-slate-600">
                Generated on {{ now()->format('d M Y, h:i A') }}
                @if ($printableFilters->isNotEmpty())
                    <span class="mx-1">|</span>
                    Filters:
                    {{ $printableFilters->map(static fn ($value, $key) => \Illuminate\Support\Str::headline((string) $key).': '.(is_scalar($value) ? (string) $value : json_encode($value)))->implode(' ; ') }}
                @endif
            </div>
        </div>

        @isset($above)
            @if (! $above->isEmpty())
                <div class="mb-4 space-y-4 w-full min-w-0">
                    {{ $above }}
                </div>
            @endif
        @endisset

        <div class="print-hide flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between w-full min-w-0">
            <div class="print-hide flex flex-wrap items-center gap-3 min-w-0">
                @if ($backRoute)
                    <a
                        href="{{ route($backRoute, absolute: false) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ $backRoute }}"
                        class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
                    >
                        {{ $backLabel }}
                    </a>
                @endif
            </div>
            <div class="print-hide flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full sm:w-auto min-w-0 justify-start sm:justify-end [&>button]:w-full [&>button]:sm:w-auto">
                {{ $actions ?? '' }}
            </div>
        </div>

        @if (count($stats) > 0)
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 w-full min-w-0">
                @foreach ($stats as $s)
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $s['label'] }}</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white tabular-nums break-words">{{ $s['value'] }}</p>
                        @if (! empty($s['hint'] ?? null))
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $s['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($hasToolbar || $hasTable || ($slotHasContent && ! $hasTable))
            <div class="property-ws-wrap w-full min-w-0 space-y-3">
                @if ($hasToolbar || $canShowDefaultSearch)
                    <div
                        class="print-hide flex flex-col sm:flex-row flex-wrap gap-3 items-stretch sm:items-center w-full min-w-0 [&_input[type=search]]:w-full [&_input[type=search]]:min-w-0 [&_input[type=search]]:sm:max-w-md [&_input[type=month]]:w-full [&_input[type=month]]:min-w-0 [&_input[type=month]]:sm:w-auto [&_select]:w-full [&_select]:min-w-0 [&_select]:sm:w-auto [&_.flex-1]:min-w-0"
                    >
                        @if ($canShowDefaultSearch)
                            <input
                                type="search"
                                data-table-filter="parent"
                                autocomplete="off"
                                placeholder="Search…"
                                class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
                            />
                        @endif
                        {{ $toolbar ?? '' }}
                    </div>
                @endif

                @if ($hasTable)
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0">
                        <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
                            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_th]:dark:border-slate-700 [&_td]:border [&_td]:border-slate-200 [&_td]:dark:border-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        @foreach ($columns as $col)
                                            <th class="px-3 sm:px-4 py-3 whitespace-nowrap">{{ $col }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @if (count($tableRows) > 0)
                                        @foreach ($tableRows as $rowIndex => $row)
                                            @php
                                                $__filterText = $customRowFilters
                                                    ? mb_strtolower((string) $tableRowFilters[$rowIndex])
                                                    : mb_strtolower(
                                                        implode(' ', array_map(static fn ($c) => strip_tags((string) $c), $row))
                                                    );
                                            @endphp
                                            <tr
                                                class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                                                data-filter-text="{{ e($__filterText) }}"
                                            >
                                                @foreach ($row as $cell)
                                                    <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-normal sm:whitespace-nowrap sm:max-w-xs sm:truncate align-top">
                                                        @if ($cell instanceof \Illuminate\Support\HtmlString)
                                                            {!! $cell !!}
                                                        @else
                                                            {{ $cell }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td
                                                colspan="{{ count($columns) }}"
                                                class="px-4 py-14 text-center align-middle"
                                            >
                                                <p class="font-medium text-slate-700 dark:text-slate-200">{{ $emptyTitle }}</p>
                                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">{{ $emptyHint }}</p>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($slotHasContent)
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        @endif

        @isset($footer)
            @if (! $footer->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-900/30 p-5 text-sm text-slate-600 dark:text-slate-400 w-full min-w-0">
                    {{ $footer }}
                </div>
            @endif
        @endisset

        @if ($hasTable && $slotHasContent)
            <div class="w-full min-w-0 mt-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm p-4 sm:p-6 overflow-hidden">
                {{ $slot }}
            </div>
        @endif
    </x-property.page>
</x-property-layout>
