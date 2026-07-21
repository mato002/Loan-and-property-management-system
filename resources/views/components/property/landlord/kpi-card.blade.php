@props([
    'label' => null,
    'value' => null,
    'emphasis' => false,
    'wide' => false,
])

<div @class([
    'rounded-xl border p-3 sm:p-4 min-w-0 shadow-sm',
    'border-emerald-300 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20' => $emphasis,
    'border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80' => ! $emphasis,
    'col-span-2 xl:col-span-1' => $wide,
]) {{ $attributes }}>
    @if ($label)
        <p class="text-[10px] sm:text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400 leading-snug">{{ $label }}</p>
    @endif
    @if (! is_null($value))
        <p @class([
            'font-semibold tabular-nums break-words text-slate-900 dark:text-white',
            'mt-1.5 text-lg sm:text-xl xl:text-2xl' => $emphasis,
            'mt-1 text-base sm:text-lg' => ! $emphasis,
        ])>{{ $value }}</p>
    @endif
    {{ $slot }}
</div>
