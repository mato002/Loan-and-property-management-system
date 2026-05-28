@props([
    'title',
    'subtitle' => null,
    'backRoute' => null,
    'backRouteParams' => [],
    'backLabel' => '← Back',
    /** @var list<array{label: string, value: string, hint?: string|null}> $stats */
    'stats' => [],
    /** @var list<string> $columns */
    'columns' => [],
    /** @var list<list<string>> $tableRows */
    'tableRows' => [],
    /** @var list<mixed>|null $tableFooterRow Optional footer cells (same length as columns) */
    'tableFooterRow' => null,
    /** @var list<string>|null $tableRowFilters Optional per-row filter text (same length as tableRows) for client-side search */
    'tableRowFilters' => null,
    'showSearch' => true,
    /** When false, toolbar slot renders as-is (e.g. x-property.filter-toolbar) without legacy mobile drawer wrapper */
    'legacyToolbar' => true,
    'tableMinWidth' => '720px',
    /** Show mobile-record-list below md; table hidden on small screens */
    'responsiveCards' => false,
    /** @var list<array<string, mixed>>|null $columnConfig */
    'columnConfig' => null,
    'emptyTitle' => 'No records yet',
    'emptyHint' => 'Data will load here once this module is connected to your database.',
    'workspace' => null,
    'showWorkspaceTabs' => true,
])

@php
    use App\Support\Property\ResponsiveTableColumns;
    use App\Support\Property\PropertyWorkspaceTabs;

    $routeName = request()->route()?->getName();
    $resolvedWorkspaceKey = $workspace ?? PropertyWorkspaceTabs::resolveWorkspaceKey($routeName);
    $renderWorkspaceTabs = ($showWorkspaceTabs ?? true)
        && $resolvedWorkspaceKey
        && PropertyWorkspaceTabs::shouldShow($routeName);

    $hasToolbar = isset($toolbar) && ! $toolbar->isEmpty();
    $hasMobileFiltersExtra = ($legacyToolbar ?? true) && isset($mobile_filters_extra) && ! $mobile_filters_extra->isEmpty();
    $useLegacyToolbar = (bool) ($legacyToolbar ?? true);
    $hasTableActions = isset($table_actions) && ! $table_actions->isEmpty();
    $hasTable = count($columns) > 0;
    $slotHasContent = isset($slot) && ! $slot->isEmpty();
    $customRowFilters = is_array($tableRowFilters ?? null)
        && count($tableRowFilters) === count($tableRows);
    $canShowDefaultSearch = (bool) $showSearch && $hasTable;
    $useResponsiveCards = (bool) ($responsiveCards ?? false) && $hasTable;
    $resolvedColumnConfig = $useResponsiveCards
        ? ResponsiveTableColumns::normalize(
            is_array($columnConfig) && count($columnConfig) > 0
                ? $columnConfig
                : $columns
        )
        : [];
    $filterActiveCount = collect(request()->query())
        ->except(['export', 'page', 'per_page'])
        ->filter(static fn ($value) => ! is_null($value) && $value !== '')
        ->count();
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
    <x-property.page :title="$title" :subtitle="$subtitle" :workspace="$resolvedWorkspaceKey" :show-workspace-tabs="$showWorkspaceTabs">
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
                <div class="mb-4 space-y-4 w-full min-w-0" data-workspace-above>
                    {{ $above }}
                </div>
            @endif
        @endisset

        <div class="print-hide flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between w-full min-w-0">
            <div class="print-hide flex flex-wrap items-center gap-3 min-w-0">
                @if ($backRoute && ! $renderWorkspaceTabs)
                    <a
                        href="{{ route($backRoute, $backRouteParams ?? [], false) }}"
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
            <x-property.responsive.stat-card-grid :stats="$stats" />
        @endif

        @if ($hasToolbar || $hasTable || ($slotHasContent && ! $hasTable))
            <div class="property-ws-wrap property-erp-surface w-full min-w-0 space-y-2.5 md:space-y-3">
                @if ($hasToolbar || ($useLegacyToolbar && $canShowDefaultSearch) || $hasMobileFiltersExtra)
                    <div @class([
                        'print-hide w-full min-w-0',
                        'space-y-2' => $useLegacyToolbar,
                    ])>
                        @if ($useLegacyToolbar && $canShowDefaultSearch)
                            <input
                                type="search"
                                data-table-filter="parent"
                                autocomplete="off"
                                placeholder="Search…"
                                class="w-full min-w-0 min-h-[44px] sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2.5"
                            />
                        @endif

                        @if ($hasToolbar && ! $useLegacyToolbar)
                            {{ $toolbar }}
                        @elseif ($hasToolbar || $hasMobileFiltersExtra)
                            <div class="hidden md:block w-full min-w-0 property-workspace-toolbar-host">
                                @if ($hasToolbar)
                                    <div class="flex flex-row flex-wrap items-end gap-2 w-full min-w-0 [&_form]:flex [&_form]:flex-row [&_form]:flex-wrap [&_form]:items-end [&_form]:gap-2 [&_form]:w-full [&_form]:min-w-0">
                                        {{ $toolbar }}
                                    </div>
                                @endif
                            </div>
                            <div class="md:hidden w-full min-w-0">
                                <x-property.responsive.mobile-filter-drawer :label="'Filters & options'" :active-count="$filterActiveCount">
                                    @if ($hasMobileFiltersExtra)
                                        <x-slot name="mobile_filters_extra">
                                            {{ $mobile_filters_extra }}
                                        </x-slot>
                                    @endif
                                    <div class="flex flex-col gap-3 w-full min-w-0 [&_form]:w-full [&_form]:space-y-3 [&_input]:w-full [&_select]:w-full [&_button]:w-full [&_a]:w-full">
                                        @if ($hasToolbar)
                                            {{ $toolbar }}
                                        @endif
                                    </div>
                                </x-property.responsive.mobile-filter-drawer>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($hasTableActions)
                    <div
                        class="print-hide w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0 property-workspace-bulk-host"
                        data-workspace-table-actions-host
                    >
                        {{ $table_actions }}
                    </div>
                @endif

                @if ($hasTable)
                    <div class="w-full min-w-0 space-y-2.5">
                    <div @class([
                        'property-erp-panel rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0 overflow-visible',
                        'hidden md:block' => $useResponsiveCards,
                    ])>
                        <x-property.responsive.table-wrapper :min-width="$tableMinWidth">
                            <table class="property-erp-table w-full table-auto border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_th]:dark:border-slate-700 [&_td]:border [&_td]:border-slate-200 [&_td]:dark:border-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        @foreach ($columns as $col)
                                            <th class="px-2 sm:px-4 py-2 sm:py-3 whitespace-nowrap">{{ $col }}</th>
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
                                            @endphp
                                            <tr
                                                class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 {{ $__rowHref ? 'cursor-pointer' : '' }}"
                                                data-filter-text="{{ e($__filterText) }}"
                                                @if ($__rowHref)
                                                    data-row-href="{{ $__rowHref }}"
                                                    tabindex="0"
                                                    role="link"
                                                    aria-label="Open row details"
                                                @endif
                                            >
                                                @foreach ($row as $cell)
                                                    @php
                                                        $cellHtml = (string) $cell;
                                                        $containsDropdown = str_contains(strtolower($cellHtml), '<details');
                                                    @endphp
                                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-slate-700 dark:text-slate-200 align-top text-xs sm:text-sm {{ $containsDropdown ? 'whitespace-normal overflow-visible' : 'whitespace-normal break-words min-w-[5rem]' }}">
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
                                                class="px-3 sm:px-4 py-10 sm:py-14 text-center align-middle"
                                            >
                                                <p class="text-sm sm:text-base font-medium text-slate-700 dark:text-slate-200">{{ $emptyTitle }}</p>
                                                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">{{ $emptyHint }}</p>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                                @php
                                    $footerRow = is_array($tableFooterRow ?? null) ? $tableFooterRow : [];
                                    $showTableFooter = count($tableRows) > 0
                                        && count($footerRow) > 0
                                        && count($footerRow) === count($columns);
                                @endphp
                                @if ($showTableFooter)
                                    <tfoot class="bg-slate-100 dark:bg-slate-900/70 border-t-2 border-slate-300 dark:border-slate-600 text-left text-sm">
                                        <tr data-row-ignore-click>
                                            @foreach ($footerRow as $cell)
                                                <td class="px-3 sm:px-4 py-3 font-semibold text-slate-800 dark:text-slate-100 align-top whitespace-normal break-words tabular-nums">
                                                    @if ($cell instanceof \Illuminate\Support\HtmlString)
                                                        {!! $cell !!}
                                                    @else
                                                        {{ $cell }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </x-property.responsive.table-wrapper>
                    </div>

                    @if ($useResponsiveCards)
                        <x-property.responsive.mobile-record-list
                            :columns="$columns"
                            :column-config="$resolvedColumnConfig"
                            :rows="$tableRows"
                            :row-filters="$customRowFilters ? $tableRowFilters : null"
                            :empty-title="$emptyTitle"
                            :empty-hint="$emptyHint"
                        />
                    @endif
                    </div>
                @elseif ($slotHasContent)
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-visible bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        @endif

        @isset($footer)
            @if (! $footer->isEmpty())
                <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-900/30 text-xs sm:text-sm text-slate-600 dark:text-slate-400 w-full min-w-0">
                    {{ $footer }}
                </div>
            @endif
        @endisset

        @if ($hasTable && $slotHasContent)
            <div class="w-full min-w-0 mt-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm p-4 sm:p-6 overflow-visible">
                {{ $slot }}
            </div>
        @endif
    </x-property.page>
</x-property-layout>
