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
    class="w-full min-w-0"
    data-property-page-modals
>
<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    :title="($activeTab ?? 'leases') === 'expiry' ? 'Lease expiry tracking' : 'Lease agreements'"
    :subtitle="($activeTab ?? 'leases') === 'expiry'
        ? 'Active leases ending within the next 90 days. Use the window filter to focus renewals.'
        : 'Terms, deposits, rent, and linked units.'"
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="($activeTab ?? 'leases') === 'expiry' ? ($expiryFilterTexts ?? []) : []"
    :empty-title="($activeTab ?? 'leases') === 'expiry' ? 'No upcoming expiries' : 'No leases'"
    :empty-hint="($activeTab ?? 'leases') === 'expiry'
        ? 'When leases have end dates in the next 90 days, they appear here.'
        : 'Create a lease and select vacant units; active leases mark units occupied.'"
>
    <x-slot name="actions">
        @if (($activeTab ?? 'leases') === 'leases')
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                @click="showLeaseCreateForm = true"
            >
                <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                <span>Create lease</span>
            </button>
        @else
            <a
                href="{{ route('property.workspace.form.show', 'tenants-renewal-email') }}"
                class="inline-flex justify-center items-center rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
            >Email renewals</a>
        @endif
    </x-slot>

    <x-slot name="tabs">
        @include('property.agent.tenants.partials.leases_workspace_tabs')
    </x-slot>

    @if (($activeTab ?? 'leases') === 'leases')
        <x-slot name="modals">
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
        </x-slot>

        <x-slot name="secondary">
            @include('property.agent.tenants.partials.leases_workspace_onboarding')
        </x-slot>
    @endif

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.leases', [
            'activeTab' => $activeTab ?? 'leases',
            'filters' => $filters ?? [],
            'filterOptions' => $filterOptions ?? ['tenants' => [], 'properties' => []],
        ])
    </x-slot>

    <x-slot name="table_actions">
        @if (($activeTab ?? 'leases') === 'leases' && count($tableRows ?? []) > 0)
            <x-property.bulk-action-bar
                form-id="property-leases-bulk-form"
                :action="route('property.leases.bulk', absolute: false)"
                confirm="Apply this bulk action to all selected leases?"
                :actions="[
                    ['value' => 'activate', 'label' => 'Activate (allocate unit)'],
                    ['value' => 'terminate', 'label' => 'Terminate'],
                    ['value' => 'restore', 'label' => 'Restore to active'],
                    ['value' => 'delete', 'label' => 'Delete draft only'],
                ]"
            />
        @endif
    </x-slot>

    @if (($activeTab ?? 'leases') === 'leases' && is_array(session('bulk_lease_errors')) && count(session('bulk_lease_errors')) > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Some leases were skipped</p>
            <ul class="mt-2 list-disc pl-5 space-y-1">
                @foreach (session('bulk_lease_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (($activeTab ?? 'leases') === 'leases')
        @include('property.agent.partials.lease_list_row_action_form')
    @endif

    @if (isset($leasePager) && ($activeTab ?? 'leases') === 'leases')
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">
                    Showing {{ $leasePager->firstItem() ?? 0 }}-{{ $leasePager->lastItem() ?? 0 }} of {{ $leasePager->total() }} leases.
                </p>
                <div>
                    {{ $leasePager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif
</x-property.workspace>
</div>
