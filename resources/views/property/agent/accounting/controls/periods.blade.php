<x-property.workspace
    title="Accounting periods"
    subtitle="Current month opens automatically. Close or lock at month-end when you are ready."
    back-route="property.accounting.index"
    :stats="[['label' => 'Periods', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'Control periods']]"
    :columns="['Period', 'Start', 'End', 'Status', 'Closed By', 'Actions']"
    :table-rows="$rows->getCollection()->map(function($p){
        $buttons = '<div class=\'flex flex-wrap gap-1\'>';
        foreach (['open' => 'Reopen', 'closed' => 'Close', 'locked' => 'Lock'] as $status => $label) {
            $buttons .= '<form method=\'post\' action=\''.route('property.accounting.controls.periods.status', ['period' => $p->id]).'\' data-turbo-frame=\'property-main\'>'.
                csrf_field().
                '<input type=\'hidden\' name=\'status\' value=\''.$status.'\' />'.
                '<button class=\'text-xs rounded border border-slate-200 px-2 py-1 hover:bg-slate-50\' type=\'submit\'>'.$label.'</button>'.
                '</form>';
        }
        $buttons .= '</div>';
        return [
            (string) $p->name,
            optional($p->start_date)->format('Y-m-d') ?? '—',
            optional($p->end_date)->format('Y-m-d') ?? '—',
            ucfirst((string) $p->status),
            (string) ($p->closed_by ?? '—'),
            new \Illuminate\Support\HtmlString($buttons),
        ];
    })->all()"
    :empty-title="($rows->total() ?? 0) === 0 ? 'No accounting periods yet' : 'No records'"
    :empty-hint="($rows->total() ?? 0) === 0 ? 'Open the current month to enable GL postings and payment reversals.' : 'Data will load here once this module is connected to your database.'"
>
    <x-slot name="actions">
        <form method="post" action="{{ route('property.accounting.controls.periods.initialize', absolute: false) }}" data-turbo-frame="property-main" class="inline">
            @csrf
            <input type="hidden" name="months" value="1" />
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                Open {{ $currentMonthLabel ?? now()->format('F Y') }}
            </button>
        </form>
        @if (($rows->total() ?? 0) === 0)
            <form method="post" action="{{ route('property.accounting.controls.periods.initialize', absolute: false) }}" data-turbo-frame="property-main" class="inline">
                @csrf
                <input type="hidden" name="months" value="12" />
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Initialize last 12 months
                </button>
            </form>
        @endif
    </x-slot>

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        The current month is created and kept <strong>open</strong> automatically when invoices, payments, or reversals need it. Use <strong>Close</strong> at month-end, then <strong>Lock</strong> after review.
    </div>

    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>
