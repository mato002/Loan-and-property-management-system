@props([
    'label' => 'Module UI',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-blue-200/80 dark:border-blue-900/50 bg-blue-50/60 dark:bg-blue-950/25 px-4 py-3 text-sm text-blue-900 dark:text-blue-100']) }}>
    <span class="font-semibold">{{ $label }}:</span>
    <span class="text-blue-800/90 dark:text-blue-200/90">Screens implemented · connect database &amp; APIs to activate data and actions.</span>
</div>
