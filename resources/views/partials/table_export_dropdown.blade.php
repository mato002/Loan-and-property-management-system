@props([
    'route' => null,
    'query' => [],
    'routeParams' => [],
    'formats' => null,
    'current' => false,
])

@include('property.agent.partials.table_export_dropdown', get_defined_vars())
