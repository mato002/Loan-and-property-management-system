<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Accounts payable — bills listing"
    subtitle="Vendor bills imported from EZEN Bills & Vendors (Bills Listing). These are supplier invoices, not tenant rent bills."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    table-min-width="1280px"
    empty-title="No vendor bills imported"
    empty-hint="Export Bills Listing from EZEN as CSV (or PDF/.txt), then run php artisan property:import-ezen-bills storage/passion-legacy/bills_list.txt --dry-run --agent-user-id=2"
>
    <x-slot name="secondary">
        <div class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">EZEN vendor bills</p>
            <p class="mt-1 text-sm text-slate-600">Garbage collection and other supplier invoices from EZEN. Closed bills show amount due 0. Paying a bill in EZEN is a payment voucher; this register is the bill itself.</p>
        </div>
    </x-slot>

    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'route' => 'property.accounting.payables.accounts_payable',
            'query' => request()->except(['export', 'format', 'page']),
        ])
        <a href="{{ route('property.accounting.payables.payment_vouchers') }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Payment vouchers</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.payables.accounts_payable') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Bill #, vendor inv, vendor…" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[14rem]">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Vendor</label>
                <input type="search" name="vendor" value="{{ $filters['vendor'] ?? '' }}" placeholder="TOSHIA…" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[12rem]">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[10rem]">
                    <option value="">All statuses</option>
                    @foreach (['paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid', 'closed' => 'Closed (EZEN)', 'open' => 'Open (EZEN)'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 mt-5">Apply</button>
            <a href="{{ route('property.accounting.payables.accounts_payable') }}" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 mt-5">Reset</a>
        </form>
    </x-slot>

    <x-slot name="footer">
        @isset($paginator)
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        @endisset
    </x-slot>
</x-property.workspace>
