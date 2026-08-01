<x-property.workspace
    title="Water & Utility Operations"
    subtitle="Capture meter readings, run billing, and manage charge lines — utility AR stays separate from core rent."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats"
    :columns="[]"
    empty-title="No utility charges"
    empty-hint="Use the workspace tabs to capture readings and manage charge lines."
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 min-h-[44px]">Ledger</a>
        <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-xl border border-teal-300 bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-900 hover:bg-teal-100 min-h-[44px]">Reconciliation</a>
        <a href="{{ route('property.revenue.utilities.periods', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-xl border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-900 hover:bg-indigo-100 min-h-[44px]">Period closing</a>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.utilities', get_defined_vars())
    </x-slot>

    <x-slot name="above">
        @include('property.agent.revenue.utilities._workspace')
    </x-slot>
</x-property.workspace>
