<x-property.workspace
    title="Accounting periods"
    subtitle="Close, lock, and reopen periods with controlled actions."
    back-route="property.accounting.index"
    :stats="[['label' => 'Periods', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'Control periods']]"
    :columns="['Period', 'Start', 'End', 'Status', 'Closed By', 'Actions']"
    :table-rows="$rows->getCollection()->map(function($p){
        $buttons = '<div class=\'flex gap-1\'>';
        foreach (['open' => 'Reopen', 'closed' => 'Close', 'locked' => 'Lock'] as $status => $label) {
            $buttons .= '<form method=\'post\' action=\''.route('property.accounting.controls.periods.status', ['period' => $p->id]).'\'>'.
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
>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        What needs action: close current period at month-end, then lock after review. Reopen is restricted and should be audited.
    </div>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>

