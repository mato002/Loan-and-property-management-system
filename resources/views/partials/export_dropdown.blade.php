@php
    $query = is_array($query ?? null) ? $query : [];
@endphp

@include('property.agent.partials.export_dropdown', [
    'csvUrl' => request()->fullUrlWithQuery(array_merge($query, ['export' => 'csv'])),
    'xlsUrl' => request()->fullUrlWithQuery(array_merge($query, ['export' => 'xls'])),
    'pdfUrl' => request()->fullUrlWithQuery(array_merge($query, ['export' => 'pdf'])),
    'wordUrl' => request()->fullUrlWithQuery(array_merge($query, ['export' => 'word'])),
])
