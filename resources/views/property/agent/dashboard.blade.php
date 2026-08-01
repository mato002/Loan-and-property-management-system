<x-property-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $chartYear = $chartYear ?? (int) now()->year;
    @endphp
    <x-property.page
        title="Dashboard"
        subtitle="Portfolio snapshot — counts, cash movement, maintenance intake, and year-to-date billing vs collections ({{ $chartYear }})."
    >
        @include('property.agent.partials.dashboard_stats_light')

        <div
            id="property-dashboard-heavy-host"
            data-property-heavy-metrics
            data-metrics-url="{{ route('property.dashboard.metrics', absolute: false) }}"
        >
            @include('property.agent.partials.dashboard_heavy_skeleton')
        </div>
    </x-property.page>
</x-property-layout>
