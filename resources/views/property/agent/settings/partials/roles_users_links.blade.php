<div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Roles and users">
    <a
        href="{{ route('property.settings.roles') }}"
        data-turbo-frame="property-main"
        role="tab"
        aria-selected="true"
        class="inline-flex items-center rounded-lg border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white"
    >Roles</a>
    <a
        href="{{ route('property.settings.roles', ['panel' => 'users']) }}"
        data-turbo-frame="property-main"
        role="tab"
        class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200 dark:hover:bg-slate-700/50"
    >Users</a>
</div>
