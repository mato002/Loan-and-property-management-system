<x-property-layout>

    <x-slot name="header">Reports</x-slot>



    <x-property.page title="Reports">
        <x-property.landlord.reports-hub :panels="$panels" :panel-groups="$panelGroups" :active="$activePanel" :active-group="$activeGroup" class="mb-3" />

        <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 shadow-sm overflow-hidden w-full min-w-0">
            <div class="p-3 sm:p-4 lg:p-5 w-full min-w-0">
                @include('property.landlord.reports.panels.'.$activePanel)
            </div>
        </section>
    </x-property.page>

</x-property-layout>

