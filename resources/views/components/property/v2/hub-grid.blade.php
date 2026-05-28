@props([
    /** @var list<array{route: string, title: string, description?: string|null}> $items */
    'items' => [],
])

<div {{ $attributes->merge(['class' => 'hub-grid']) }}>
    @foreach ($items as $item)
        <a
            href="{{ route($item['route'], absolute: false) }}"
            data-turbo-frame="property-main"
            data-property-nav="{{ $item['route'] }}"
            class="hub-card group"
        >
            <h3 class="hub-card-title">{{ $item['title'] }}</h3>
            @if (! empty($item['description'] ?? null))
                <p class="hub-card-desc">{{ $item['description'] }}</p>
            @endif
        </a>
    @endforeach
</div>
