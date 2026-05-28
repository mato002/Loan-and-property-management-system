@props([
    'filters' => [],
])

@if (count($filters) > 0)
    <div {{ $attributes->merge(['class' => 'public-filter-chips mb-4']) }}>
        <span class="text-xs font-bold uppercase tracking-wide text-gray-500 mr-1">Active:</span>
        @foreach ($filters as $filter)
            <a href="{{ $filter['removeUrl'] ?? '#' }}" class="public-filter-chip hover:bg-emerald-100 transition-colors">
                {{ $filter['label'] }}
                <span class="public-filter-chip-remove" aria-hidden="true">×</span>
            </a>
        @endforeach
        <a href="{{ route('public.properties') }}" class="text-xs font-bold text-gray-500 hover:text-emerald-700 ml-1">Clear all</a>
    </div>
@endif
