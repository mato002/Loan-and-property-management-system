<x-property.crud-shell
    :in-property-form-modal="$inPropertyFormModal ?? false"
    :title="'Edit employee: '.$employee->full_name"
    subtitle="Update HR profile and property role links."
    back-route="property.hr.employees.show"
    :back-route-params="['employee' => $employee->id]"
    :stats="[
        ['label' => 'Employee #', 'value' => $employee->employee_number, 'hint' => $employee->job_title ?: 'No title'],
        ['label' => 'Status', 'value' => ucfirst((string) ($employee->employment_status ?: 'active')), 'hint' => 'Employment'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.hr.employees.update', $employee, false) }}" class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-4 max-w-3xl w-full min-w-0">
        @csrf
        @method('PUT')
        @include('property.agent.hr.employees.partials.form_fields')
        <div class="flex flex-col sm:flex-row gap-2 pt-1">
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Save changes</button>
            <a href="{{ route('property.hr.employees.show', $employee, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</a>
        </div>
    </form>
</x-property.crud-shell>
