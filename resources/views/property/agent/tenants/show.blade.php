@php
    $hubModalDefaults = [
        'showHubInvoiceForm' => $errors->hasAny(['pm_lease_id','property_unit_id','pm_tenant_id','issue_date','due_date','amount','status','description','invoice_type','billing_period'])
            && old('return_to') === 'tenant_show'
            && (string) old('return_tab') === 'invoices',
        'showHubPaymentForm' => $errors->hasAny(['pm_invoice_id','amount','channel','external_ref','paid_at'])
            && old('payment_form', 'invoice') === 'invoice'
            && old('return_to') === 'tenant_show',
        'showHubAdvanceForm' => ($errors->has('advance') || ($errors->hasAny(['amount','channel','external_ref']) && old('payment_form') === 'advance'))
            && old('return_to') === 'tenant_show',
        'showHubNoticeForm' => $errors->hasAny(['notice_type','status','due_on','notes','property_unit_id'])
            && old('return_to') === 'tenant_show',
        'showHubMaintenanceForm' => $errors->hasAny(['property_id','property_unit_id','category','urgency','description'])
            && old('return_to') === 'tenant_show',
    ];
@endphp
<x-property.workspace :compact-list="false"
    :title="'Tenant: '.$tenant->name"
    subtitle="360° tenant workspace — see and act without leaving this page."
    back-route="property.tenants.directory"
    :stats="[
        ['label' => 'Status', 'value' => (string) (($profileStatus['label'] ?? '—')), 'hint' => (string) (($profileStatus['hint'] ?? 'Occupancy'))],
        ['label' => 'Account', 'value' => (string) ($tenant->account_number ?: '—'), 'hint' => $occupancyLabel ?? 'Unit'],
        ['label' => 'Monthly rent', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($monthlyRentTotal ?? 0)), 'hint' => ((int) ($activeLeaseCount ?? 0)).' active lease(s)'],
        ['label' => 'Total due', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totalDue['total_due'] ?? 0)), 'hint' => 'AR + uninvoiced CF − credit'],
    ]"
    :columns="[]"
>
    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from($hubModalDefaults) !!}"></x-slot>

    <x-slot name="actions">
        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700" data-property-modal-open="showHubInvoiceForm" @click="showHubInvoiceForm = true">
            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Invoice
        </button>
        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700" data-property-modal-open="showHubPaymentForm" @click="showHubPaymentForm = true">
            <i class="fa-solid fa-money-bill" aria-hidden="true"></i> Pay
        </button>
        <a href="{{ route('property.tenants.edit', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit</a>
        <a href="{{ route('property.tenants.statement', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Statement</a>
    </x-slot>

    <x-slot name="modals">
        @include('property.agent.tenants.partials.hub_modals')
    </x-slot>

    @include('property.agent.tenants.partials.hub')
</x-property.workspace>
