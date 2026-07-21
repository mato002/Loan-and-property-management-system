<turbo-frame id="lease-create-modal">
    @php
        $leaseCfg = $leaseFields ?? [];
        $leaseRequired = fn (string $k, bool $d = false) => (bool) (($leaseCfg[$k]['required'] ?? $d) && ($leaseCfg[$k]['enabled'] ?? true));
    @endphp
    <div class="flex items-start justify-end gap-3">
        <button
            type="button"
            data-lease-create-close
            class="shrink-0 rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50"
        >
            Hide form
        </button>
    </div>
    @include('property.agent.tenants.partials.lease_create_form_content', [
        'leaseFormTurboFrame' => 'lease-create-modal',
    ])
    @include('property.agent.tenants.partials.lease_create_form_script')
</turbo-frame>
