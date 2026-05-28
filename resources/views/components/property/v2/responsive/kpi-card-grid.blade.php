@props([
    /** @var list<array{label: string, value: string, icon: string, bar: string, route: string}> $kpis */
    'kpis' => [],
])

@if (count($kpis) > 0)
    <div {{ $attributes->merge(['class' => 'kpi-card-grid']) }}>
        @foreach ($kpis as $kpi)
            <article class="kpi-card group">
                <a
                    href="{{ route($kpi['route']) }}"
                    data-turbo-frame="property-main"
                    class="kpi-card-tap md:hidden"
                    aria-label="View {{ $kpi['label'] }}"
                ></a>
                <div class="kpi-card-bar {{ $kpi['bar'] }}"></div>
                <div class="kpi-card-body">
                    <div class="min-w-0 flex-1">
                        <p class="kpi-card-value">{{ $kpi['value'] }}</p>
                        <p class="kpi-card-label">{{ $kpi['label'] }}</p>
                    </div>
                    <div class="kpi-card-icon" aria-hidden="true">
                        <i class="fa-solid {{ $kpi['icon'] }}"></i>
                    </div>
                </div>
                <a
                    href="{{ route($kpi['route']) }}"
                    data-turbo-frame="property-main"
                    class="kpi-card-link max-md:hidden"
                >
                    <span>View</span>
                    <i class="fa-solid fa-arrow-right text-[10px] sm:text-xs" aria-hidden="true"></i>
                </a>
            </article>
        @endforeach
    </div>
@endif
