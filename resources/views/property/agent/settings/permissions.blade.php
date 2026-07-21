<x-property-layout>
    <x-slot name="header">Permissions</x-slot>

    <x-property.page
        title="Permissions"
        subtitle="Manage system permission keys, groups, and role assignments."
    >
        <x-slot name="actions">
            <div
                class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full lg:w-auto"
                x-data
            >
                <button
                    type="button"
                    @click="$dispatch('permissions-open-add')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                    Add permission
                </button>
                <button
                    type="button"
                    onclick="window.exportPermissionsCsv?.()"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                >
                    <i class="fa-solid fa-file-export text-xs" aria-hidden="true"></i>
                    Export
                </button>
                <a
                    href="{{ route('property.settings.permissions', absolute: false) }}"
                    data-turbo-frame="property-main"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200"
                >
                    <i class="fa-solid fa-rotate text-xs" aria-hidden="true"></i>
                    Refresh defaults
                </a>
            </div>
        </x-slot>

        @include('property.agent.settings.partials.subnav', ['active' => 'property.settings.permissions'])

        @include('property.agent.settings.partials.permissions_console', [
            'permissions' => $permissions ?? collect(),
            'stats' => $stats ?? [],
        ])
    </x-property.page>
</x-property-layout>
