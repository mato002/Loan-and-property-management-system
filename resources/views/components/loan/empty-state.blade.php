@props([
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center']) }}>
    <p class="text-sm font-semibold text-slate-800">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="mt-4 inline-flex rounded-lg bg-[#0f766e] px-3 py-2 text-xs font-semibold text-white hover:bg-[#0d6560]">
            {{ $actionLabel }}
        </a>
    @endif
    {{ $slot }}
</div>
