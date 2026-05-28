@php
    use App\Models\PmLease;

    $isTerminated = $lease->status === PmLease::STATUS_TERMINATED;
    $tenantQuery = $tenantName ?? ($lease->pmTenant?->name ?? '');
@endphp

<x-property.action-menu width="w-40">
    <a href="{{ route('property.leases.show', $lease, false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">View</a>
    <a href="{{ route('property.leases.edit', $lease, false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit</a>
    <a href="{{ route('property.revenue.invoices', ['q' => $tenantQuery], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Invoices</a>
    <a href="{{ route('property.revenue.payments', ['q' => $tenantQuery], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Payments</a>
    <a href="{{ route('property.tenants.notices', ['tenant_id' => $lease->pm_tenant_id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Notices</a>
    <a href="{{ route('property.tenants.statement', ['tenant' => $lease->pm_tenant_id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Statement</a>

    @if ($isTerminated)
        <button
            type="button"
            data-property-lease-row-action
            data-action-url="{{ route('property.leases.restore', $lease, false) }}"
            data-action-method="POST"
            data-swal-confirm="Restore this lease to active?"
            class="block w-full border-t border-slate-100 px-3 py-2 text-left text-xs font-medium text-emerald-700 hover:bg-emerald-50 dark:border-slate-700 dark:text-emerald-300 dark:hover:bg-slate-700/50"
        >
            Restore
        </button>
    @else
        <button
            type="button"
            data-property-lease-row-action
            data-action-url="{{ route('property.leases.terminate', $lease, false) }}"
            data-action-method="POST"
            data-swal-confirm="Terminate this lease now?"
            class="block w-full border-t border-slate-100 px-3 py-2 text-left text-xs font-medium text-amber-700 hover:bg-amber-50 dark:border-slate-700 dark:text-amber-300 dark:hover:bg-slate-700/50"
        >
            Terminate
        </button>
    @endif

    <button
        type="button"
        data-property-lease-row-action
        data-action-url="{{ route('property.leases.destroy', $lease, false) }}"
        data-action-method="DELETE"
        data-swal-confirm="Delete this lease permanently?"
        class="block w-full px-3 py-2 text-left text-xs font-medium text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-slate-700/50"
    >
        Delete
    </button>
</x-property.action-menu>
