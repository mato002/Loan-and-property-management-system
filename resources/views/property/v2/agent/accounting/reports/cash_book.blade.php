<x-property.workspace
    title="Cash book"
    subtitle="Running balance for cash/bank tagged accounting entries."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No cash book rows"
    empty-hint="Use account names containing 'cash' or 'bank' to populate this report."
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.reports.cash_book.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'q' => $filters['q'] ?? null]),
            'pdfUrl' => route('property.accounting.reports.cash_book.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'q' => $filters['q'] ?? null, 'format' => 'pdf']),
        ])
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.reports.cash_book') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search account/description/ref…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-72" />
            <button type="submit" class="rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Apply</button>
        </form>
    </x-slot>
    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>

