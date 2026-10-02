<div class="dashboard-overview mb-3 sm:mb-4">
    <x-property.responsive.kpi-card-grid :kpis="$kpis" />

    <aside class="dashboard-checklist" aria-label="Quick start checklist">
        <p class="text-sm font-semibold text-slate-900">Quick start checklist</p>
        <p class="mt-1 text-xs text-slate-600">Follow these in order to get a unit billed and paid.</p>
        <nav class="dashboard-checklist-list">
            <a href="{{ route('property.properties.list') }}" data-turbo-frame="property-main" class="dashboard-checklist-item">
                <i class="fa-solid fa-building text-slate-400" aria-hidden="true"></i>
                <strong>Properties</strong>
                <span>Add each building you manage.</span>
            </a>
            <a href="{{ route('property.properties.units') }}" data-turbo-frame="property-main" class="dashboard-checklist-item">
                <i class="fa-solid fa-door-open text-slate-400" aria-hidden="true"></i>
                <strong>Units</strong>
                <span>Add the spaces inside each property.</span>
            </a>
            <a href="{{ route('property.tenants.directory') }}" data-turbo-frame="property-main" class="dashboard-checklist-item">
                <i class="fa-solid fa-users text-slate-400" aria-hidden="true"></i>
                <strong>Tenants</strong>
                <span>Register the people who will rent.</span>
            </a>
            <a href="{{ route('property.tenants.leases') }}" data-turbo-frame="property-main" class="dashboard-checklist-item is-primary">
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                <strong>Lease</strong>
                <span>Attach a tenant to a unit and set the rent.</span>
            </a>
            <a href="{{ route('property.revenue.invoices') }}" data-turbo-frame="property-main" class="dashboard-checklist-item">
                <i class="fa-solid fa-file-invoice text-slate-400" aria-hidden="true"></i>
                <strong>Invoices</strong>
                <span>Bill the rent for the month.</span>
            </a>
            <a href="{{ route('property.revenue.payments') }}" data-turbo-frame="property-main" class="dashboard-checklist-item">
                <i class="fa-solid fa-money-bill-wave text-slate-400" aria-hidden="true"></i>
                <strong>Payments</strong>
                <span>Record the money received.</span>
            </a>
        </nav>
    </aside>
</div>
