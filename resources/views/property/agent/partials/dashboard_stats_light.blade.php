<div class="mb-3 sm:mb-5 property-compact-panel rounded-xl sm:rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white shadow-sm">
    <p class="text-base sm:text-lg font-semibold text-slate-900">Quick start checklist</p>
    <p class="mt-1 text-xs sm:text-sm text-slate-600">If you’re new here: setup portfolio → onboard tenant → allocate unit → bill rent → collect payment.</p>
    <x-property.responsive.quick-action-grid class="mt-3">
        <a href="{{ route('property.properties.list') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
            Properties
            <i class="fa-solid fa-building" aria-hidden="true"></i>
        </a>
        <a href="{{ route('property.properties.units') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
            Units
            <i class="fa-solid fa-door-open" aria-hidden="true"></i>
        </a>
        <a href="{{ route('property.tenants.directory') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
            Tenants
            <i class="fa-solid fa-users" aria-hidden="true"></i>
        </a>
        <a href="{{ route('property.tenants.leases') }}" data-turbo-frame="property-main" class="quick-action-btn bg-blue-600 text-white hover:bg-blue-700">
            Lease
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
        <a href="{{ route('property.revenue.invoices') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
            Invoices
            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
        </a>
        <a href="{{ route('property.revenue.payments') }}" data-turbo-frame="property-main" class="quick-action-btn border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
            Payments
            <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
        </a>
    </x-property.responsive.quick-action-grid>
</div>

<x-property.responsive.kpi-card-grid :kpis="$kpis" class="mb-3 sm:mb-4" />
