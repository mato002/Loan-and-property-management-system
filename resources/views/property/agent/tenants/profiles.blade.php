<x-property.workspace
    :legacy-toolbar="false"
    :title="$pageTitle"
    :subtitle="$pageSubtitle ?? 'Profile completeness — ID, contacts, risk flags, and portal access.'"
    :show-search="false"
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No tenant profiles"
    empty-hint="Add tenants from Directory, then complete ID, contact, and risk details here."
>
    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.tenants_compliance', ['filters' => $filters])
    </x-slot>

    @if (isset($tenantPager))
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $tenantPager->firstItem() ?? 0 }}-{{ $tenantPager->lastItem() ?? 0 }} of {{ $tenantPager->total() }} profiles.
                </p>
                <div>
                    {{ $tenantPager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif
</x-property.workspace>
