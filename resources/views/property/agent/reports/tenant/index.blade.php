@php
    $activeTab = 'tenant';
    $panelTitle = 'Tenant Reports';
    $headerTone = 'bg-emerald-700';
    $panelItems = [
        ['label' => 'Tenant Statements', 'route' => 'property.reports.tenant.statements'],
        ['label' => 'Rent Penalties Report', 'route' => 'property.reports.tenant.rent_penalties'],
        ['label' => 'Tenant De-Allocation Report', 'route' => 'property.reports.tenant.de_allocation'],
        ['label' => 'Tenant Allocation Report', 'route' => 'property.reports.tenant.allocation'],
        ['label' => 'Tenant Deposits Report', 'route' => 'property.reports.tenant.deposits'],
        ['label' => 'Tenant Aging Balance Summary', 'route' => 'property.reports.tenant.aging_balance'],
        ['label' => 'Tenant Statements By Allocation', 'route' => 'property.reports.tenant.statements_by_allocation'],
    ];
@endphp

@include('property.agent.reports.partials.module_page')
