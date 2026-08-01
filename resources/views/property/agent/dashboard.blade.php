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

        <turbo-frame
            id="property-dashboard-heavy"
            src="{{ route('property.dashboard.metrics', absolute: false) }}"
            loading="eager"
            data-turbo-action="replace"
            data-property-heavy-metrics
        >
            @include('property.agent.partials.dashboard_heavy_skeleton')
        </turbo-frame>
    </x-property.page>
</x-property-layout>
