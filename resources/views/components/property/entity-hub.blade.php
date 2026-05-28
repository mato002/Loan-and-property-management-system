<div {{ $attributes->merge(['class' => 'property-entity-hub print-hide space-y-3 mb-4 sm:mb-5']) }} data-property-entity-hub="{{ $entity }}">
    @if ($alerts !== [])
        <div class="flex flex-wrap gap-2">
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
                    <a href="{{ $alert['href'] }}" data-turbo-frame="property-main" class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold hover:opacity-90 {{ $classes }}">{{ $alert['label'] ?? 'Alert' }}</a>
                @else
                    <span class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $classes }}">{{ $alert['label'] ?? 'Alert' }}</span>
                @endif
            @endforeach
        </div>
    @endif

    @if ($quickActions !== [])
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 p-2 sm:p-3 shadow-sm">
            <p class="px-2 pb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Quick actions</p>
            <div class="flex flex-wrap gap-2 px-1 pb-1">
                @foreach ($quickActions as $action)
                    @php
                        $tone = (string) ($action['tone'] ?? 'default');
                        $btnClass = match ($tone) {
                            'primary' => 'bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700',
                            'muted' => 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800',
                            default => 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800',
                        };
                    @endphp
                    <a
                        href="{{ route($action['route'], $action['params'] ?? [], false) }}"
                        data-turbo-frame="property-main"
                        class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs sm:text-sm font-semibold min-h-[40px] {{ $btnClass }}"
                    >
                        @if (! empty($action['icon']))
                            <i class="fa-solid {{ $action['icon'] }} text-sm" aria-hidden="true"></i>
                        @endif
                        {{ $action['label'] ?? 'Action' }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 shadow-sm overflow-hidden">
        <div class="px-3 pt-2 pb-1 border-b border-slate-100 dark:border-slate-800">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Entity workspace</p>
        </div>
        <nav class="flex gap-1 overflow-x-auto custom-scrollbar px-2 py-2 snap-x snap-mandatory" aria-label="Entity sections">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $tabUrl($tab['key']) }}"
                    data-turbo-frame="property-main"
                    @if ($activeTab === $tab['key']) aria-current="page" @endif
                    class="snap-start shrink-0 inline-flex items-center rounded-lg px-3 py-2 text-xs sm:text-sm font-semibold min-h-[40px] border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 aria-[current=page]:bg-indigo-600 aria-[current=page]:text-white aria-[current=page]:shadow-sm"
                >
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</div>
