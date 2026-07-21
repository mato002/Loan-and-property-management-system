<x-property.workspace
    :legacy-toolbar="false"
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
    <x-slot name="above">
        @include('property.agent.tenants.partials.leases_workspace_above')
    </x-slot>

    @if (($activeTab ?? 'leases') === 'expiry')
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'tenants-renewal-email') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Email renewals</a>
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
