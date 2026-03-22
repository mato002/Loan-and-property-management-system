<x-property-layout>
    <x-slot name="header">Maintenance</x-slot>

    <x-property.page
        title="Maintenance"
        subtitle="Tickets through jobs — history and cost in one operational lane."
    >
        <x-property.module-status label="Maintenance" class="mb-4" />

        <x-property.hub-grid :items="[
            ['route' => 'property.maintenance.requests', 'title' => 'Requests', 'description' => 'Intake and triage.'],
            ['route' => 'property.maintenance.jobs', 'title' => 'Active jobs', 'description' => 'Work orders in flight.'],
            ['route' => 'property.maintenance.history', 'title' => 'Maintenance history', 'description' => 'Done and cancelled jobs.'],
            ['route' => 'property.maintenance.costs', 'title' => 'Cost tracking', 'description' => 'Spend by unit and property.'],
            ['route' => 'property.maintenance.frequency', 'title' => 'Issue frequency', 'description' => 'Tickets by month (12 months).'],
        ]" />
    </x-property.page>
</x-property-layout>
