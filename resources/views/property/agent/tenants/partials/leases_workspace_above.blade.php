@php
    $showLeaseFormByDefault = ($errors ?? null)?->hasAny([
        'pm_tenant_id',
        'start_date',
        'end_date',
        'utility_expense_type',
        'utility_expense_rate',
        'status',
        'property_unit_ids',
        'property_unit_ids.*',
        'monthly_rent',
        'deposit_amount',
        'additional_deposits',
        'additional_deposits.*.label',
        'additional_deposits.*.amount',
        'terms_summary',
        'opening_arrears_items',
        'opening_arrears_items.*.type',
        'opening_arrears_items.*.label',
        'opening_arrears_items.*.period',
        'opening_arrears_items.*.amount',
        'opening_arrears_amount',
        'opening_arrears_as_of',
        'opening_arrears_notes',
        'opening_arrears',
        'opening_arrears.*',
        'opening_rent_arrears',
        'opening_rent_arrears_period',
        'opening_rent_arrears_details',
        'opening_deposit_arrears',
        'opening_deposit_arrears.*',
        'opening_arrears_manual_total',
        'opening_arrears_as_of_date',
        'opening_arrears_note',
    ]) ?? false;
    $leaseFormOpen = $showLeaseFormByDefault || (bool) ($openLeaseCreateModal ?? false);
    $leaseCfg = $leaseFields ?? [];
    $leaseRequired = fn (string $k, bool $d = false) => (bool) (($leaseCfg[$k]['required'] ?? $d) && ($leaseCfg[$k]['enabled'] ?? true));
    $openingArrearsRows = old('opening_arrears', []);
    $openingDepositArrearsRows = old('opening_deposit_arrears', []);
    $additionalDeposits = old('additional_deposits', []);
    $leaseCreateAlpineConfig = \App\Support\Property\LeaseCreateAlpineConfig::build($errors ?? null, $openingArrearsTypeOptions ?? []);
@endphp

<div
    data-lease-form-root
    x-data="Object.assign({ showLeaseCreateForm: @js($leaseFormOpen) }, leaseCreateFormAlpineState(@js($leaseCreateAlpineConfig)))"
    :data-lease-form-open="showLeaseCreateForm ? '1' : '0'"
    x-init="
        const leaseFormStorageKey = 'property.leases.createFormOpen';
        const bootLeaseCreatePanel = () => $nextTick(() => { window.initLeaseFormLogic?.(); });
        try {
            if (sessionStorage.getItem(leaseFormStorageKey) === '1') {
                showLeaseCreateForm = true;
            }
        } catch (e) {}
        if (showLeaseCreateForm) bootLeaseCreatePanel();
        $watch('showLeaseCreateForm', (open) => {
            try {
                sessionStorage.setItem(leaseFormStorageKey, open ? '1' : '0');
            } catch (e) {}
            if (open) bootLeaseCreatePanel();
        });
    "
    class="space-y-3 w-full min-w-0"
>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="{{ route('property.tenants.leases', absolute: false) }}"
            data-turbo-frame="property-main"
            class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium {{ ($activeTab ?? 'leases') === 'leases' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200 dark:hover:bg-slate-700/50' }}"
        >
            All leases
        </a>
        <a
            href="{{ route('property.tenants.leases', ['tab' => 'expiry'], false) }}"
            data-turbo-frame="property-main"
            class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium {{ ($activeTab ?? 'leases') === 'expiry' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200 dark:hover:bg-slate-700/50' }}"
        >
            Expiring soon
        </a>

        @if (($activeTab ?? 'leases') === 'leases')
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 sm:ml-auto"
                data-property-modal-open="showLeaseCreateForm" @click="showLeaseCreateForm = true"
            >
                <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                <span>Create lease</span>
            </button>
        @endif
    </div>

    @if (($activeTab ?? 'leases') === 'leases')
        <x-property.modal
            show="showLeaseCreateForm"
            close="showLeaseCreateForm = false"
            name="lease-create-workspace"
            title="Create lease"
            max-width="4xl"
        >
        <div class="w-full min-w-0">
            @include('property.agent.tenants.partials.lease_create_form_content', [
                'leaseFormTurboFrame' => 'property-main',
                'leaseFormAlpineOnParent' => true,
            ])
            @include('property.agent.tenants.partials.lease_create_form_script')
        </div>
        </x-property.modal>

        <div class="rounded-xl sm:rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-950/30 dark:to-slate-900/40 p-3 md:p-4 shadow-sm">
            <p class="text-sm sm:text-base font-semibold text-slate-900 dark:text-slate-100">Rent flow (Step 1 of 3): Allocate a unit</p>
            <p class="mt-1 text-xs sm:text-sm text-slate-600 dark:text-slate-400">Create an <span class="font-semibold">Active</span> lease and select vacant unit(s). The unit becomes <span class="font-semibold">Occupied</span> automatically.</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
                    <span class="text-slate-500">Next:</span> Create rent bill
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200">
                    <span class="text-slate-500">Then:</span> Collect payment
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    @endif
</div>
