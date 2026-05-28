<x-property.workspace
    title="Receipts (KRA eTIMS)"
    subtitle="Paid invoice stubs — eTIMS integration can extend this list later."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No paid-invoice receipts listed"
    empty-hint="Shows invoices marked paid; link eTIMS when your integration is ready."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Receipts</p>
            <p class="mt-1 text-sm text-slate-600">Receipts appear after invoices are fully paid. Normal flow: Lease → Invoice → Payment → Receipt.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Lease
                    <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Invoice
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Payment
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.receipts', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Search receipt, invoice, tenant..." class="w-full min-w-0 sm:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="updated_at" @selected(($filters['sort'] ?? 'updated_at') === 'updated_at')>Sort: Submitted</option>
                <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Sort: Amount</option>
                <option value="invoice_no" @selected(($filters['sort'] ?? '') === 'invoice_no')>Sort: Invoice</option>
                <option value="id" @selected(($filters['sort'] ?? '') === 'id')>Sort: ID</option>
            </select>
            <select name="dir" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
                <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
            </select>
            <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                @foreach ([10, 30, 50, 100, 200] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.revenue.receipts', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.revenue.receipts', array_merge(request()->query(), ['export' => 'csv']), false),
                'xlsUrl' => route('property.revenue.receipts', array_merge(request()->query(), ['export' => 'xls']), false),
                'pdfUrl' => route('property.revenue.receipts', array_merge(request()->query(), ['export' => 'pdf']), false),
            ])
        </form>
    </x-slot>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} receipt(s)
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
    </x-slot>
</x-property.workspace>
