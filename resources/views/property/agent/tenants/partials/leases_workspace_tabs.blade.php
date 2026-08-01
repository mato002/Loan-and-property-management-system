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
</div>
