<x-property.workspace
    title="Accounting audit trail"
    subtitle="Trace posting source, reversals, and reference trails for accounting records."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No accounting audit rows"
    empty-hint="Accounting activity will appear here as entries are posted."
>
    <x-slot name="actions">
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.accounting.audit_trail.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'source_key' => $filters['source_key'] ?? null, 'q' => $filters['q'] ?? null]),
            'pdfUrl' => route('property.accounting.audit_trail.export', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'source_key' => $filters['source_key'] ?? null, 'q' => $filters['q'] ?? null, 'format' => 'pdf']),
        ])
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.audit_trail') }}" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
            <select name="source_key" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
                <option value="">Source: All</option>
                @foreach ($sourceOptions as $source)
                    <option value="{{ $source }}" @selected(($filters['source_key'] ?? '') === $source)>{{ $source }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search account/ref/source…" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-72" />
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.accounting.audit_trail') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-center">Reset</a>
        </form>
    </x-slot>
    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>

