<turbo-frame id="lease-create-modal">
    @php
        $leaseCfg = $leaseFields ?? [];
        $leaseRequired = fn (string $k, bool $d = false) => (bool) (($leaseCfg[$k]['required'] ?? $d) && ($leaseCfg[$k]['enabled'] ?? true));
    @endphp
    <div class="flex max-h-[90vh] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-gray-800">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
            <div class="min-w-0">
                <h3 id="lease-create-title" class="text-base font-semibold text-slate-900 dark:text-white">New lease</h3>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Allocate one vacant unit to a tenant to activate tenancy and unlock monthly billing.</p>
            </div>
            <button type="button" data-lease-create-close class="shrink-0 rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50">Close</button>
        </div>
        <div class="overflow-y-auto px-4 py-4">
            @include('property.agent.tenants.partials.lease_create_form_content')
        </div>
    </div>
    @include('property.agent.tenants.partials.lease_create_form_script')
</turbo-frame>
