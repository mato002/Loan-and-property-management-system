@props([
    /** @var list<array{route: string, title: string, description?: string|null}> $items */
    'items' => [],
])

@php
    use App\Support\Property\PropertyNavigation;
@endphp

<div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-3 gap-2 sm:gap-3 md:gap-4 w-full min-w-0">
    @foreach ($items as $item)
        <a
            href="{{ PropertyNavigation::workspaceHref($item) }}"
            data-turbo-frame="property-main"
            data-property-nav="{{ $item['route'] }}"
            class="group block rounded-xl sm:rounded-2xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-3 sm:p-5 shadow-sm hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all min-h-[72px] sm:min-h-0"
        >
            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $item['title'] }}</h3>
            @if (! empty($item['description'] ?? null))
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">{{ $item['description'] }}</p>
            @endif
        </a>
    @endforeach
</div>
