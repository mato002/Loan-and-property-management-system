<x-property-layout>
    <x-slot name="header">Maintenance</x-slot>

    <x-property.page
        title="Maintenance"
        subtitle="Report with photos, track status, view history."
    >
        <a href="{{ route('property.tenant.maintenance.report') }}" class="block w-full rounded-2xl bg-teal-600 text-white text-center py-4 text-sm font-semibold shadow-md hover:bg-teal-700 transition-colors">
            Report an issue
        </a>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4 mt-4">
            <p class="text-xs font-medium uppercase text-slate-500">Active requests</p>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">None yet — your open jobs will list here.</p>
        </div>
    </x-property.page>
</x-property-layout>
