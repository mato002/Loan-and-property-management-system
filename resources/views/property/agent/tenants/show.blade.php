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
        'showLeaseCreateForm' => false,
    ];
@endphp
<x-property.workspace :compact-list="true"
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
        @include('property.agent.partials.hub_quick_actions', ['actions' => $quickActions ?? []])
    </x-slot>

    <x-slot name="modals">
        @include('property.agent.tenants.partials.hub_modals', [
            'tenant' => $tenant,
            'hubOpenInvoices' => $hubOpenInvoices ?? collect(),
            'hubLeases' => $hubLeases ?? collect(),
            'hubUnits' => $hubUnits ?? collect(),
            'advanceCreditsEnabled' => $advanceCreditsEnabled ?? false,
            'noticeTemplate' => $noticeTemplate ?? '',
        ])
        @include('property.agent.partials.lease_create_shell', [
            'openLeaseCreateModal' => false,
            'leaseCreateFormUrl' => route('property.leases.create_form', [
                'pm_tenant_id' => $tenant->id,
                'return_to' => 'tenant_show',
                'return_tenant_id' => $tenant->id,
                'return_tab' => 'leases',
            ], false),
        ])
    </x-slot>

    @include('property.agent.tenants.partials.hub')
</x-property.workspace>
