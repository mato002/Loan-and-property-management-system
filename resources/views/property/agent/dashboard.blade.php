<x-property-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $chartYear = $chartYear ?? (int) now()->year;
    @endphp
    <x-property.page
        title="Dashboard"
        subtitle="Portfolio snapshot — counts, cash movement, maintenance intake, and year-to-date billing vs collections ({{ $chartYear }})."
    >
        @if ($deferDashboardMetrics ?? false)
            <turbo-frame
                id="property-dashboard-metrics"
                src="{{ route('property.dashboard.metrics', absolute: false) }}"
                loading="lazy"
                data-turbo-action="replace"
            >
                @include('property.agent.partials.dashboard_stats_skeleton')
            </turbo-frame>
        @else
            @include('property.agent.partials.dashboard_stats_inner')
        @endif
    </x-property.page>
</x-property-layout>
