<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Receipts (KRA eTIMS)"
    subtitle="Paid invoice stubs — eTIMS integration can extend this list later."
    back-route="property.revenue.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No paid-invoice receipts listed"
    empty-hint="Shows invoices marked paid; link eTIMS when your integration is ready."
>
    <x-slot name="secondary">
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
        @include('property.agent.partials.filter_toolbars.receipts', get_defined_vars())
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
