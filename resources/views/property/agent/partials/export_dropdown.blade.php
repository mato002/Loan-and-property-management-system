@props([
    'csvUrl' => null,
    'xlsUrl' => null,
    'pdfUrl' => null,
    'wordUrl' => null,
    'class' => 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50',
])

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
