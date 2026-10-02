@props([
    'showUrl' => '',
    'printUrl' => '',
    'properties' => [],
    'showProperty' => true,
    'showDetailLevel' => true,
    'defaultPeriod' => 'fy',
    'defaultReport' => 'detail',
    'defaultFy' => null,
    'defaultMonth' => null,
    'defaultFrom' => null,
    'defaultTo' => null,
    'defaultPropertyId' => '',
    'title' => 'Generate statement report',
    'hint' => 'Pick the property (optional), period, and summary or full detail. Print or download CSV / Excel / PDF / Word.',
    'extraQuery' => [],
    'formats' => ['pdf', 'xls', 'csv', 'word'],
    'layout' => 'card', // card | split
    'buttonLabel' => 'Print / Export Statement',
])

@php
    $fy = (int) ($defaultFy ?? now()->year);
    $monthDefault = $defaultMonth ?: now()->format('Y-m');
    $fromDefault = $defaultFrom ?: $fy.'-01';
    $toDefault = $defaultTo ?: (((int) now()->year === $fy) ? $fy.'-'.now()->format('m') : $fy.'-12');
    $extraQueryJson = \Illuminate\Support\Js::from($extraQuery);
    $propertiesList = collect($properties)->map(fn ($p) => [
        'id' => (int) (is_array($p) ? ($p['id'] ?? 0) : ($p->id ?? 0)),
        'name' => (string) (is_array($p) ? ($p['name'] ?? '') : ($p->name ?? '')),
    ])->filter(fn ($p) => $p['id'] > 0)->values();
    $isSplit = $layout === 'split';
    $formatMeta = [
        'pdf' => ['label' => 'PDF Document', 'icon' => 'fa-file-pdf'],
        'xls' => ['label' => 'Excel Spreadsheet', 'icon' => 'fa-file-excel'],
        'xlsx' => ['label' => 'Excel Spreadsheet', 'icon' => 'fa-file-excel'],
        'csv' => ['label' => 'CSV Export', 'icon' => 'fa-file-csv'],
        'word' => ['label' => 'Word Document', 'icon' => 'fa-file-word'],
    ];
@endphp

<div
    @class([
        'relative inline-flex' => $isSplit,
        'rounded-xl sm:rounded-2xl border border-teal-200 dark:border-teal-800 bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0 p-4 sm:p-5' => ! $isSplit,
    ])
    x-data="{
        open: false,
        period: @js($defaultPeriod),
        report: @js($defaultReport),
        propertyId: @js((string) $defaultPropertyId),
        fy: @js((string) $fy),
        month: @js($monthDefault),
        from: @js($fromDefault),
        to: @js($toDefault),
        showUrlBase: @js($showUrl),
        printUrlBase: @js($printUrl),
        showProperty: @js((bool) $showProperty),
        showDetailLevel: @js((bool) $showDetailLevel),
        extra: {{ $extraQueryJson }},
        buildQuery(extra = {}) {
            const q = new URLSearchParams();
            Object.entries(this.extra || {}).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== '') q.set(k, v);
            });
            q.set('fy', this.fy);
            if (this.showDetailLevel) q.set('report', this.report);
            if (this.showProperty && this.propertyId) q.set('property_id', this.propertyId);
            if (this.period === 'month') {
                q.set('month', this.month);
            } else if (this.period === 'range') {
                q.set('from', this.from);
                q.set('to', this.to);
            }
            Object.entries(extra).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== '') q.set(k, v);
            });
            return q.toString();
        },
        withQuery(base, extra = {}) {
            if (!base) return '#';
            const sep = base.includes('?') ? '&' : '?';
            return base + sep + this.buildQuery(extra);
        },
        openPrint() {
            this.open = false;
            window.open(this.withQuery(this.printUrlBase || this.showUrlBase, { print: '1' }), '_blank');
        },
        download(format) {
            this.open = false;
            const scope = this.report === 'summary' ? 'summary' : 'detail';
            window.location = this.withQuery(this.showUrlBase, { export: format, export_scope: scope });
        }
    }"
    @keydown.escape.window="open = false"
>
    @if ($isSplit)
        <div class="relative inline-flex" @click.outside="open = false">
            <div class="inline-flex rounded-lg shadow-sm">
                <button
                    type="button"
                    @click="openPrint()"
                    class="inline-flex min-h-[36px] items-center gap-1.5 rounded-l-lg border border-indigo-600 bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                    title="Print / open the statement with the current filters (defaults if unchanged)"
                >
                    <i class="fa-solid fa-print text-[10px] opacity-90" aria-hidden="true"></i>
                    <span>{{ $buttonLabel }}</span>
                </button>
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex min-h-[36px] w-9 items-center justify-center rounded-r-lg border border-l-0 border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700"
                    :aria-expanded="open.toString()"
                    aria-haspopup="dialog"
                    title="Choose period, detail level, and export format"
                >
                    <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                    <span class="sr-only">Open export options</span>
                </button>
            </div>

            <div
                x-show="open"
                x-cloak
                x-transition.opacity.duration.150ms
                class="absolute right-0 z-40 mt-2 w-[min(100vw-2rem,22rem)] rounded-xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900"
                style="top: 100%;"
                role="dialog"
                aria-label="Statement export options"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Filters</p>
                <div class="mt-2 grid grid-cols-1 gap-2">
                    @if ($showProperty)
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                            Property
                            <select x-model="propertyId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                <option value="">All linked properties</option>
                                @foreach ($propertiesList as $prop)
                                    <option value="{{ $prop['id'] }}">{{ $prop['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Period
                        <select x-model="period" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                            <option value="fy">Full year (FY)</option>
                            <option value="month">Single month</option>
                            <option value="range">Custom from → to</option>
                        </select>
                    </label>

                    @if ($showDetailLevel)
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                            Detail level
                            <select x-model="report" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                <option value="summary">Summary (totals)</option>
                                <option value="detail">Full (line / unit detail)</option>
                            </select>
                        </label>
                    @endif

                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300" x-show="period === 'fy'">
                        Financial year
                        <input type="number" min="2000" max="2100" x-model="fy" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    </label>

                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300" x-show="period === 'month'" x-cloak>
                        Month
                        <input type="month" x-model="month" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    </label>

                    <div class="grid grid-cols-2 gap-2" x-show="period === 'range'" x-cloak>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                            From
                            <input type="month" x-model="from" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                            To
                            <input type="month" x-model="to" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        </label>
                    </div>
                </div>

                <div class="mt-3 border-t border-slate-100 pt-2 dark:border-slate-700">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Export</p>
                    <div class="mt-1.5 flex flex-col gap-0.5">
                        <button
                            type="button"
                            @click="openPrint()"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium text-slate-800 hover:bg-indigo-50 dark:text-slate-100 dark:hover:bg-slate-800"
                        >
                            <i class="fa-solid fa-print w-4 text-indigo-600" aria-hidden="true"></i>
                            Print / open PDF
                        </button>
                        @foreach ($formats as $format)
                            @php $meta = $formatMeta[$format] ?? ['label' => strtoupper((string) $format), 'icon' => 'fa-file']; @endphp
                            <button
                                type="button"
                                @click="download(@js($format))"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium text-slate-800 hover:bg-slate-50 dark:text-slate-100 dark:hover:bg-slate-800"
                            >
                                <i class="fa-solid {{ $meta['icon'] }} w-4 text-slate-500" aria-hidden="true"></i>
                                {{ $meta['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @else
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
            <p class="mt-0.5 text-xs text-slate-500">{{ $hint }}</p>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @if ($showProperty)
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Property
                    <select x-model="propertyId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        <option value="">All linked properties</option>
                        @foreach ($propertiesList as $prop)
                            <option value="{{ $prop['id'] }}">{{ $prop['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                Period type
                <select x-model="period" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                    <option value="fy">Full year (FY)</option>
                    <option value="month">Single month</option>
                    <option value="range">Custom from → to</option>
                </select>
            </label>

            @if ($showDetailLevel)
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Detail level
                    <select x-model="report" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                        <option value="summary">Summary (totals)</option>
                        <option value="detail">Full (line / unit detail)</option>
                    </select>
                </label>
            @endif

            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300" x-show="period === 'fy'">
                Financial year
                <input type="number" min="2000" max="2100" x-model="fy" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </label>

            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300" x-show="period === 'month'" x-cloak>
                Month
                <input type="month" x-model="month" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </label>

            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300" x-show="period === 'range'" x-cloak>
                From (month)
                <input type="month" x-model="from" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </label>

            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300" x-show="period === 'range'" x-cloak>
                To (month)
                <input type="month" x-model="to" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </label>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @if ($printUrl || $showUrl)
                <button
                    type="button"
                    @click="openPrint()"
                    class="inline-flex min-h-[40px] items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >Print / open</button>
            @endif
            @foreach ($formats as $format)
                @php
                    $label = match ($format) {
                        'xls', 'xlsx' => 'Excel',
                        'csv' => 'CSV',
                        'pdf' => 'PDF',
                        'word' => 'Word',
                        default => strtoupper((string) $format),
                    };
                @endphp
                <button
                    type="button"
                    @click="download(@js($format))"
                    class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >{{ $label }}</button>
            @endforeach
        </div>
    @endif
</div>
