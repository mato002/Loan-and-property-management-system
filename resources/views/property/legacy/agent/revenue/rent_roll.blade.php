<x-property.workspace
    title="Rent roll"
    subtitle="Active leases by unit — scheduled rent vs paid vs balance."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No rent roll lines yet"
    empty-hint="Add properties, units, active leases, invoices, and payments to populate this grid."
>
    <x-slot name="actions">
        <span class="inline-flex items-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">Live data</span>
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.rent_roll', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Search unit, tenant…" class="w-full min-w-0 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="unit" @selected(($filters['sort'] ?? 'unit') === 'unit')>Sort: Unit</option>
                <option value="tenant" @selected(($filters['sort'] ?? '') === 'tenant')>Sort: Tenant</option>
                <option value="period" @selected(($filters['sort'] ?? '') === 'period')>Sort: Period</option>
                <option value="due" @selected(($filters['sort'] ?? '') === 'due')>Sort: Rent due</option>
                <option value="paid" @selected(($filters['sort'] ?? '') === 'paid')>Sort: Paid</option>
                <option value="balance" @selected(($filters['sort'] ?? '') === 'balance')>Sort: Balance</option>
                <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Sort: Status</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="asc" @selected(($filters['dir'] ?? 'asc') === 'asc')>Asc</option>
                <option value="desc" @selected(($filters['dir'] ?? '') === 'desc')>Desc</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.revenue.rent_roll', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.revenue.rent_roll', array_merge(request()->query(), ['export' => 'csv']), false),
                'xlsUrl' => route('property.revenue.rent_roll', array_merge(request()->query(), ['export' => 'xls']), false),
                'pdfUrl' => route('property.revenue.rent_roll', array_merge(request()->query(), ['export' => 'pdf']), false),
            ])
        </form>
    </x-slot>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} rent line(s)
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
    </x-slot>
</x-property.workspace>
