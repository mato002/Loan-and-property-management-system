<select
    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    onchange="if(this.value){ window.location.href=this.value; this.selectedIndex=0; }"
>
    <option value="">Export</option>
    <option value="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">CSV</option>
    <option value="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}">PDF</option>
    <option value="{{ request()->fullUrlWithQuery(['export' => 'word']) }}">Word</option>
</select>

