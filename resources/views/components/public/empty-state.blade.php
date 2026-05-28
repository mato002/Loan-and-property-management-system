@props([
    'icon' => null,
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'public-empty-state']) }}>
    @if ($icon)
        <div class="public-empty-state-icon">{!! $icon !!}</div>
    @else
        <div class="public-empty-state-icon">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
    @endif
    <h3 class="text-lg font-black text-gray-900 mb-2">{{ $title }}</h3>
    @if ($description)
        <p class="text-sm text-gray-500 max-w-md mx-auto mb-5">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="public-btn public-btn-primary">{{ $actionLabel }}</a>
    @endif
    {{ $slot }}
</div>
