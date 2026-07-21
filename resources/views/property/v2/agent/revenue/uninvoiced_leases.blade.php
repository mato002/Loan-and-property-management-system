<x-property.workspace
    title="Uninvoiced active leases"
    subtitle="Active leases missing a rent invoice for the selected billing month — generate bills in one place."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No rows for this filter"
    empty-hint="All active leases are invoiced for this month, or try filter “All active”."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Who is not being invoiced?</p>
            <p class="mt-1 text-sm text-slate-600">
                This report compares <span class="font-medium">active leases</span> to rent invoices issued in
                <span class="font-medium">{{ $month }}</span>.
                Rows marked <span class="font-medium">Not invoiced</span> can be billed from here.
                After a lease rent increase, use filter <span class="font-medium">Rent increase due</span> to issue supplement invoices for the difference.
            </p>
            @if (! ($automationOn ?? false))
                <p class="mt-2 text-xs text-amber-800 rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2">
                    Auto rent invoicing is <span class="font-semibold">off</span> (Settings → Workflow adjustments).
                    Use <span class="font-semibold">Generate all missing</span> below or enable automation.
                </p>
            @endif
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Leases</a>
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Invoices</a>
                <a href="{{ route('property.revenue.arrears', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Arrears (unpaid only)</a>
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        @if ($canGenerate ?? false)
            <form
                id="uninvoiced-bulk-form"
                method="post"
                action="{{ route('property.revenue.uninvoiced_leases.generate', absolute: false) }}"
                data-turbo-frame="property-main"
                class="flex flex-wrap items-center gap-2"
            >
                @csrf
                <input type="hidden" name="month" value="{{ $month }}" />
                <input type="hidden" name="generate_all" value="1" />
                <button
                    type="submit"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    @disabled(($missingCount ?? 0) === 0)
                >
                    Generate all missing ({{ $missingCount ?? 0 }})
                </button>
            </form>
            <form
                method="post"
                action="{{ route('property.revenue.uninvoiced_leases.generate_supplements', absolute: false) }}"
                data-turbo-frame="property-main"
                class="flex flex-wrap items-center gap-2"
            >
                @csrf
                <input type="hidden" name="month" value="{{ $month }}" />
                <input type="hidden" name="generate_all" value="1" />
                <button
                    type="submit"
                    class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"
                    @disabled(($underbilledCount ?? 0) === 0)
                >
                    Bill all increases ({{ $underbilledCount ?? 0 }})
                </button>
            </form>
        @endif
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.uninvoiced_leases', [
            'filters' => $filters,
            'month' => $month,
            'canGenerate' => $canGenerate ?? false,
        ])
    </x-slot>

    @if (session('bulk_invoice_errors'))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            <p class="font-semibold">Some invoices could not be generated:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ((array) session('bulk_invoice_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
    </x-slot>

    <script>
        (function () {
            const selectPage = document.getElementById('uninvoiced-select-page');
            const clearPage = document.getElementById('uninvoiced-clear-page');
            const boxes = () => Array.from(document.querySelectorAll('input[form="uninvoiced-selected-form"][name="keys[]"]'));

            selectPage?.addEventListener('click', () => boxes().forEach((el) => { el.checked = true; }));
            clearPage?.addEventListener('click', () => boxes().forEach((el) => { el.checked = false; }));
        })();
    </script>
</x-property.workspace>
