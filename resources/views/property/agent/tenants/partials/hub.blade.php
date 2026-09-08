@php
    $activeTab = $activeTab ?? 'overview';
    $leaseCarryForward = $leaseCarryForward ?? ['total' => 0.0, 'lines' => [], 'invoiced' => false];
    $totalDue = $totalDue ?? ['invoice_ar' => 0.0, 'uninvoiced_cf' => 0.0, 'tenant_credit' => 0.0, 'total_due' => 0.0];
@endphp

<x-property.entity-hub
    entity="tenant"
    route-name="property.tenants.show"
    :route-params="['tenant' => $tenant->id]"
    :active-tab="$activeTab"
    :quick-actions="$quickActions ?? []"
    :alerts="$alerts ?? []"
/>

<div class="space-y-4 sm:space-y-5 w-full min-w-0">
    @includeWhen($activeTab === 'overview', 'property.agent.tenants.partials.tab-360')
    @includeWhen($activeTab === 'leases', 'property.agent.tenants.partials.tab-leases')
    @includeWhen($activeTab === 'invoices', 'property.agent.tenants.partials.tab-invoices')
    @includeWhen($activeTab === 'payments', 'property.agent.tenants.partials.tab-payments')
    @includeWhen($activeTab === 'deposits', 'property.agent.tenants.partials.tab-deposits')
    @includeWhen($activeTab === 'notices', 'property.agent.tenants.partials.tab-notices')
    @includeWhen($activeTab === 'utilities', 'property.agent.tenants.partials.tab-utilities')
    @includeWhen($activeTab === 'statement', 'property.agent.tenants.partials.tab-statement')
</div>
