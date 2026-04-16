@props([
    'title',
    'subtitle' => null,
    'showQuickLinks' => true,
])

<div {{ $attributes->merge(['class' => 'max-w-[1600px] mx-auto w-full space-y-6']) }}>
    <header class="space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl sm:text-[1.65rem] font-semibold text-slate-900 tracking-tight leading-tight">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="text-sm text-slate-600 mt-2 leading-relaxed max-w-3xl">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                @if (! $actions->isEmpty())
                    <div class="flex flex-wrap items-center gap-2 shrink-0 lg:pt-1">
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
        @if ($showQuickLinks)
            @include('loan.partials.quick-links-strip')
        @endif
        @isset($banner)
            <div class="w-full">
                {{ $banner }}
            </div>
        @endisset
    </header>

    {{ $slot }}
</div>
