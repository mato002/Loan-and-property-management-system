@php
    $showUrl = $showUrl ?? route('property.hr.employees.show', $employee);
    $editUrl = $editUrl ?? route('property.hr.employees.edit', $employee);
@endphp
<x-property.action-menu width="w-44">
    <a href="{{ $showUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">View</a>
    <a href="{{ $editUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit</a>
    @if (! empty($isFieldOfficer) && ! empty($portfolioUrl))
        <a href="{{ $portfolioUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">Portfolio</a>
    @endif
</x-property.action-menu>
