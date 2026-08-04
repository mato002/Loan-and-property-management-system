<x-property.workspace
    title="Accounting audit trail"
    subtitle="Forensic financial trace across invoices, payments, journals, payroll, maintenance, and payouts."
    back-route="property.accounting.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No financial audit activities"
    empty-hint="No events match your filters. Clear filters to expand the financial trace."
>
    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.audit_trail', get_defined_vars())
    </x-slot>

    @isset($paginator)
        <x-slot name="footer">
            @include('property.agent.partials.pagination_controls', ['paginator' => $paginator])
        </x-slot>
    @endisset
</x-property.workspace>

@include('property.agent.partials.audit_preview_modal')
