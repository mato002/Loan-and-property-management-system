@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
    'compact' => false,
    'dark' => true,
])

<div @class([
    'relative border-b border-gray-200 overflow-hidden',
    $compact ? 'public-hero-wrap py-12 sm:py-16' : 'public-hero-wrap',
])>
    @isset($background)
        <div class="absolute inset-0">
            {{ $background }}
        </div>
    @endisset
    <div class="relative w-full px-4 sm:px-6 lg:px-12 xl:px-16 2xl:px-20 text-center">
        <div @class([
            'inline-block max-w-3xl rounded-2xl px-4 py-4 sm:px-8 sm:py-6 ring-1 backdrop-blur-[1px]',
            $dark ? 'bg-slate-950/25 ring-white/20' : 'bg-white/90 ring-gray-200',
        ])>
            @if ($eyebrow)
                <p @class([
                    'text-xs sm:text-sm font-bold uppercase tracking-[0.18em] sm:tracking-[0.2em] mb-3 sm:mb-4',
                    $dark ? 'text-emerald-200' : 'text-emerald-700',
                ])>{{ $eyebrow }}</p>
            @endif
            <h1 @class([
                'text-3xl sm:text-5xl md:text-6xl font-black tracking-tight mb-3 sm:mb-6',
                $dark ? 'text-white drop-shadow-[0_4px_14px_rgba(15,23,42,0.95)]' : 'text-gray-900',
            ])>{{ $title }}</h1>
            @if ($subtitle)
                <p @class([
                    'text-sm sm:text-xl max-w-2xl mx-auto font-medium',
                    $dark ? 'text-slate-100 drop-shadow-[0_2px_8px_rgba(15,23,42,0.9)]' : 'text-gray-600',
                ])>{{ $subtitle }}</p>
            @endif
        </div>
    </div>
</div>
