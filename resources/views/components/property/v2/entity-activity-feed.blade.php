@props([
    'items' => [],
])

@if ($items !== [])
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 p-3 sm:p-4']) }}>
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3">Recent activity</h3>
        <ul class="space-y-2">
            @foreach ($items as $item)
                <li class="flex items-start gap-3 rounded-lg border border-slate-100 dark:border-slate-800 px-3 py-2.5">
                    <span @class([
                        'mt-0.5 h-2 w-2 rounded-full shrink-0',
                        'bg-emerald-500' => ($item['tone'] ?? '') === 'emerald',
                        'bg-cyan-500' => ($item['tone'] ?? '') === 'cyan',
                        'bg-amber-500' => ($item['tone'] ?? '') === 'amber',
                        'bg-slate-400' => ! in_array(($item['tone'] ?? ''), ['emerald', 'cyan', 'amber'], true),
                    ]) aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        @if (! empty($item['href']))
                            <a href="{{ $item['href'] }}" data-turbo-frame="property-main" class="text-sm font-semibold text-slate-900 dark:text-slate-100 hover:text-emerald-700 dark:hover:text-emerald-300">{{ $item['title'] ?? 'Activity' }}</a>
                        @else
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $item['title'] ?? 'Activity' }}</p>
                        @endif
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['subtitle'] ?? '' }}</p>
                    </div>
                    <time class="text-[11px] text-slate-400 shrink-0">{{ $item['at'] ?? '' }}</time>
                </li>
            @endforeach
        </ul>
    </div>
@endif
