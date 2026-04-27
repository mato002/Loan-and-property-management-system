<x-property-layout>
    <x-slot name="header">Tenants</x-slot>

    <x-property.page
        title="Tenants"
        subtitle="People-first — leases and notices live here; no separate lease module."
    >
        <x-property.module-status label="Tenants" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.tenants.directory', 'title' => 'Tenant list', 'description' => 'Directory and onboarding.'],
            ['route' => 'property.tenants.leases', 'title' => 'Lease agreements', 'description' => 'Terms, units, deposits.'],
            ['route' => 'property.tenants.expiry', 'title' => 'Lease expiry', 'description' => 'Active leases ending in the next 90 days.'],
            ['route' => 'property.tenants.movements', 'title' => 'Move-ins / move-outs', 'description' => 'Checklists and handover.'],
            ['route' => 'property.tenants.notices', 'title' => 'Notices', 'description' => 'Vacate, eviction, statutory packs.'],
        ]" />
    </x-property.page>
</x-property-layout>
