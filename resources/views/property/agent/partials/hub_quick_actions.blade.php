@php
    /** @var list<array<string, mixed>> $actions */
    $actions = $actions ?? [];
@endphp
@if ($actions !== [])
    <div class="print-hide flex flex-wrap items-center justify-end gap-1.5">
        @foreach ($actions as $action)
            @php
                $tone = (string) ($action['tone'] ?? 'default');
                $btnClass = match ($tone) {
                    'primary' => 'bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700',
                    'muted' => 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800',
                    default => 'border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800',
                };
                $modalKey = trim((string) ($action['modal'] ?? ''));
                $jsAction = trim((string) ($action['js'] ?? ''));
                $actionHref = ! empty($action['href'])
                    ? (string) $action['href']
                    : ((! empty($action['route'])) ? route($action['route'], $action['params'] ?? [], false) : '#');
            @endphp
            @if ($modalKey !== '')
                <button
                    type="button"
                    data-property-modal-open="{{ $modalKey }}"
                    @click="{{ $modalKey }} = true"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold {{ $btnClass }}"
                >
                    @if (! empty($action['icon']))
                        <i class="fa-solid {{ $action['icon'] }} text-[11px]" aria-hidden="true"></i>
                    @endif
                    {{ $action['label'] ?? 'Action' }}
                </button>
            @elseif ($jsAction !== '')
                <button
                    type="button"
                    onclick="{{ $jsAction }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold {{ $btnClass }}"
                >
                    @if (! empty($action['icon']))
                        <i class="fa-solid {{ $action['icon'] }} text-[11px]" aria-hidden="true"></i>
                    @endif
                    {{ $action['label'] ?? 'Action' }}
                </button>
            @else
                <a
                    href="{{ $actionHref }}"
                    data-turbo-frame="property-main"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold {{ $btnClass }}"
                >
                    @if (! empty($action['icon']))
                        <i class="fa-solid {{ $action['icon'] }} text-[11px]" aria-hidden="true"></i>
                    @endif
                    {{ $action['label'] ?? 'Action' }}
                </a>
            @endif
        @endforeach
    </div>
@endif
