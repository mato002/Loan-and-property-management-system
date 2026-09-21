<div {{ $attributes->merge(['class' => 'property-entity-hub print-hide space-y-2 mb-3']) }} data-property-entity-hub="{{ $entity }}" data-property-mobile-sticky-tabs>
    @if ($alerts !== [])
        <div class="flex flex-wrap gap-1.5">
            @foreach ($alerts as $alert)
                @php
                    $tone = (string) ($alert['tone'] ?? 'slate');
                    $classes = match ($tone) {
                        'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
                        'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
                        default => 'border-slate-200 bg-slate-50 text-slate-800',
                    };
                @endphp
                @if (! empty($alert['href']))
                    <a href="{{ $alert['href'] }}" data-turbo-frame="property-main" class="inline-flex items-center rounded-md border px-2.5 py-1 text-[11px] font-semibold hover:opacity-90 {{ $classes }}">{{ $alert['label'] ?? 'Alert' }}</a>
                @else
                    <span class="inline-flex items-center rounded-md border px-2.5 py-1 text-[11px] font-semibold {{ $classes }}">{{ $alert['label'] ?? 'Alert' }}</span>
                @endif
            @endforeach
        </div>
    @endif

    <nav class="flex gap-1 overflow-x-auto custom-scrollbar rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 px-1.5 py-1.5 shadow-sm snap-x snap-mandatory" aria-label="Entity sections">
        @foreach ($tabs as $tab)
            <a
                href="{{ $tabUrl($tab['key']) }}"
                data-turbo-frame="property-main"
                @if ($activeTab === $tab['key']) aria-current="page" @endif
                class="snap-start shrink-0 inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 aria-[current=page]:bg-indigo-600 aria-[current=page]:text-white aria-[current=page]:shadow-sm"
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
