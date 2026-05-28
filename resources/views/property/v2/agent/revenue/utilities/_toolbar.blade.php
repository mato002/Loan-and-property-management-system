<form method="get" action="{{ route('property.revenue.utilities', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Search label or unit…" class="w-full min-w-0 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]" />
    <select name="charge_type" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        <option value="">Type: All</option>
        <option value="water" @selected(($filters['charge_type'] ?? '') === 'water')>Water</option>
        <option value="electricity" @selected(($filters['charge_type'] ?? '') === 'electricity')>Electricity</option>
        <option value="service" @selected(($filters['charge_type'] ?? '') === 'service')>Service</option>
        <option value="garbage" @selected(($filters['charge_type'] ?? '') === 'garbage')>Garbage</option>
        <option value="other" @selected(($filters['charge_type'] ?? '') === 'other')>Other</option>
    </select>
    <input type="month" name="month" value="{{ $filters['month'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]" />
    <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        <option value="id" @selected(($filters['sort'] ?? 'id') === 'id')>Sort: ID</option>
        <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Added date</option>
        <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Amount</option>
        <option value="label" @selected(($filters['sort'] ?? '') === 'label')>Label</option>
        <option value="billing_month" @selected(($filters['sort'] ?? '') === 'billing_month')>Billing month</option>
    </select>
    <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
        <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
    </select>
    <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-h-[44px]">
        @foreach ([10, 30, 50, 100, 200] as $size)
            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
        @endforeach
    </select>
    <button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-700 min-h-[44px]">Apply</button>
    <a href="{{ route('property.revenue.utilities', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 min-h-[44px] inline-flex items-center">Reset</a>
    @include('property.agent.partials.export_dropdown', [
        'csvUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'csv']), false),
        'xlsUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'xls']), false),
        'pdfUrl' => route('property.revenue.utilities', array_merge(request()->query(), ['export' => 'pdf']), false),
    ])
</form>
