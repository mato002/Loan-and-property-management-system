@php
    $brandPaletteOptions = \App\Support\Property\PropertyBrandPalette::all();
    $selectedPalette = \App\Support\Property\PropertyBrandPalette::normalize(old('brand_palette', $brandPalette ?? \App\Support\Property\PropertyBrandPalette::PLATFORM));
@endphp
<div>
    <p class="block text-xs font-medium text-slate-600 dark:text-slate-400">Brand color palette</p>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Public website, portal chrome, and print letterheads follow this palette. Switch here when a client logo uses different colors.</p>
    <div class="mt-2 grid gap-2 sm:grid-cols-2">
        @foreach ($brandPaletteOptions as $paletteKey => $palette)
            <label class="flex cursor-pointer gap-3 rounded-xl border p-3 transition {{ $selectedPalette === $paletteKey ? 'border-slate-900 bg-slate-50 ring-1 ring-slate-900 dark:border-white dark:bg-slate-800 dark:ring-white' : 'border-slate-200 hover:border-slate-300 dark:border-slate-600' }}">
                <input type="radio" name="brand_palette" value="{{ $paletteKey }}" class="mt-1" @checked($selectedPalette === $paletteKey) />
                <span class="min-w-0">
                    <span class="flex items-center gap-1.5">
                        <span class="h-4 w-4 rounded-full ring-1 ring-black/10" style="background: {{ $palette['primary'] }}"></span>
                        <span class="h-4 w-4 rounded-full ring-1 ring-black/10" style="background: {{ $palette['cta'] }}"></span>
                        @if (strcasecmp($palette['gold'], $palette['cta']) !== 0)
                            <span class="h-4 w-4 rounded-full ring-1 ring-black/10" style="background: {{ $palette['gold'] }}"></span>
                        @endif
                        <span class="h-4 w-4 rounded-full ring-1 ring-black/10" style="background: {{ $palette['navy'] }}"></span>
                    </span>
                    <span class="mt-1.5 block text-sm font-semibold text-slate-900 dark:text-white">{{ $palette['label'] }}</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500 dark:text-slate-400">{{ $palette['hint'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('brand_palette')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
</div>
