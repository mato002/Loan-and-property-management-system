<x-property.workspace
    title="Field officers"
    subtitle="Portfolio managers assigned to properties — landlords, units, tenants, and rent under each officer."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    :column-config="$columnConfig"
    :responsive-cards="false"
    :legacy-toolbar="false"
    :show-search="false"
    table-min-width="960px"
    empty-title="No field officers yet"
    empty-hint="Field officers are managed as employees in HR. Enable the field officer role when adding staff, then assign properties from the portfolio tab."
>
    <x-slot name="actions">
        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            <a href="{{ route('property.hr.employees.create', ['field_officer' => 1, 'job_title' => 'Field Officer'], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Add employee</a>
            <a href="{{ route('property.hr.employees.index', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200">HR directory</a>
        @endif
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.field_officers')
    </x-slot>
</x-property.workspace>
