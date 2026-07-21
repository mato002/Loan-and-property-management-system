@props([
    'items' => [],
])

<div {{ $attributes->merge(['class' => 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3']) }}>
    @foreach ($items as $item)
        @php
            $route = (string) ($item['route'] ?? '');
            $href = $route !== '' && \Illuminate\Support\Facades\Route::has($route) ? route($route) : '#';
        @endphp
        <a href="{{ $href }}" data-turbo-frame="loan-main" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#2f4f4f]/30 hover:shadow-md">
            <h3 class="text-sm font-semibold text-slate-900 group-hover:text-[#2f4f4f]">{{ $item['title'] ?? 'Open' }}</h3>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $item['description'] ?? '' }}</p>
            <p class="mt-4 text-xs font-semibold text-[#2f4f4f]">Open →</p>
        </a>
    @endforeach
</div>
