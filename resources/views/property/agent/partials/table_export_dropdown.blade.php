@props([
    'route' => null,
    'query' => [],
    'routeParams' => [],
    'formats' => null,
    'current' => false,
    'class' => 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50',
])

@php
    $links = $current
        ? \App\Support\TableExportLinks::forCurrentUrl(is_array($query) ? $query : [], is_array($formats) ? $formats : \App\Support\TableExportLinks::STANDARD_FORMATS)
        : \App\Support\TableExportLinks::forRoute(
            (string) $route,
            is_array($query) ? $query : [],
            is_array($formats) ? $formats : \App\Support\TableExportLinks::BASIC_FORMATS,
            is_array($routeParams) ? $routeParams : [],
        );
@endphp

@include('property.agent.partials.export_dropdown', array_merge($links, [
    'class' => $class,
]))
