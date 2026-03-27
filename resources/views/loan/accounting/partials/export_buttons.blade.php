@php
    $url = $url ?? request()->fullUrl();
@endphp

<div class="inline-flex rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 border-r border-slate-200">CSV</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 border-r border-slate-200">PDF</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'word']) }}" class="px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Word</a>
</div>

