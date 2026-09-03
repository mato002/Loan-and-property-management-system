@props([
    'csvUrl' => null,
    'xlsUrl' => null,
    'pdfUrl' => null,
    'wordUrl' => null,
    'route' => null,
    'query' => [],
    'routeParams' => [],
    'formats' => null,
    'class' => 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50',
])

@php
    if (! empty($route)) {
        $generated = \App\Support\TableExportLinks::forRoute(
            $route,
            is_array($query) ? $query : [],
            is_array($formats) ? $formats : \App\Support\TableExportLinks::STANDARD_FORMATS,
            is_array($routeParams) ? $routeParams : [],
        );
        $csvUrl = $csvUrl ?? ($generated['csvUrl'] ?? null);
        $xlsUrl = $xlsUrl ?? ($generated['xlsUrl'] ?? null);
        $pdfUrl = $pdfUrl ?? ($generated['pdfUrl'] ?? null);
        $wordUrl = $wordUrl ?? ($generated['wordUrl'] ?? null);
    }

    foreach (['pdf' => 'pdfUrl', 'word' => 'wordUrl'] as $format => $urlKey) {
        if (! empty($$urlKey) || empty($csvUrl)) {
            continue;
        }

        $csv = (string) $csvUrl;

        if (str_contains($csv, 'export=csv')) {
            $$urlKey = preg_replace('/export=csv\b/', 'export='.$format, $csv);
        } elseif (str_contains($csv, 'format=csv')) {
            $$urlKey = preg_replace('/format=csv\b/', 'format='.$format, $csv);
        } elseif (! str_contains($csv, 'export=') && ! str_contains($csv, 'format=')) {
            $$urlKey = $csv.(str_contains($csv, '?') ? '&' : '?').'export='.$format;
        }
    }
@endphp

<select
    class="{{ $class }}"
    onchange="if(this.value){ window.location.href=this.value; this.selectedIndex=0; }"
>
    <option value="">Export</option>
    @if (!empty($csvUrl))
        <option value="{{ $csvUrl }}">CSV</option>
    @endif
    @if (!empty($xlsUrl))
        <option value="{{ $xlsUrl }}">XLS</option>
    @endif
    @if (!empty($pdfUrl))
        <option value="{{ $pdfUrl }}">PDF</option>
    @endif
    @if (!empty($wordUrl))
        <option value="{{ $wordUrl }}">Word</option>
    @endif
</select>
