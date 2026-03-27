@props([
    /** @var list<array{route: string, title: string, description: string}> $items */
    'items' => [],
])

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
    @foreach ($items as $item)
        <a
            href="{{ route($item['route'], absolute: false) }}"
            data-turbo-frame="property-main"
            data-property-nav="{{ $item['route'] }}"
            class="group block rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all"
        >
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $item['title'] }}</h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">{{ $item['description'] }}</p>
        </a>
    @endforeach
</div>
