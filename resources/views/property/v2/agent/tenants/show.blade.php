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
        'showHubDepositRefundForm' => $errors->hasAny(['refunded_at','amount','bank_name','bank_branch','bank_account_name','bank_account_number'])
            && old('refund_form') === 'deposit',
        'showHubCreditApplyForm' => $errors->hasAny(['pm_invoice_id','amount','notes'])
            && old('credit_form') === 'apply',
        'showHubCreditRefundForm' => $errors->hasAny(['amount','reference','notes'])
            && old('credit_form') === 'refund',
        'showLeaseCreateForm' => false,
    ];
@endphp
<x-property.workspace :compact-list="false"
    :title="'Tenant: '.$tenant->name"
    subtitle="360° tenant workspace — occupancy, billing, deposits, notices, and statement."
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
        <a href="{{ route('property.tenants.directory', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Directory</a>
        <a href="{{ route('property.tenants.edit', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit tenant</a>
        <a href="{{ route('property.tenants.statement', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Statement</a>
    </x-slot>

    <x-slot name="modals">
        @include('property.agent.tenants.partials.hub_modals', [
            'tenant' => $tenant,
            'hubOpenInvoices' => $hubOpenInvoices ?? collect(),
            'hubLeases' => $hubLeases ?? collect(),
            'hubUnits' => $hubUnits ?? collect(),
            'advanceCreditsEnabled' => $advanceCreditsEnabled ?? false,
            'noticeTemplate' => $noticeTemplate ?? '',
            'creditBalance' => $creditBalance ?? 0,
            'depositSnapshot' => $depositSnapshot ?? ['held' => 0.0],
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
