@php
    $cardStatus = $card['status'] ?? 'not_configured';
    $isLocked = (bool) ($card['locked'] ?? false);
    $isComingSoon = (bool) ($card['coming_soon'] ?? false);
    $isClickable = ! $isComingSoon && ! empty($card['href']);

    $statusBarClass = match (true) {
        $isLocked => 'bg-slate-300',
        $cardStatus === 'completed' => 'bg-emerald-500',
        $cardStatus === 'critical' => 'bg-red-500',
        in_array($cardStatus, ['needs_review', 'not_configured'], true) => 'bg-amber-400',
        default => 'bg-slate-300',
    };

    [$statusLabel, $statusPillClass] = match (true) {
        $isLocked => ['Locked', 'bg-slate-100 text-slate-600 ring-slate-200'],
        $cardStatus === 'completed' => ['Complete', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        $cardStatus === 'critical' => ['Critical', 'bg-red-50 text-red-700 ring-red-200'],
        $cardStatus === 'needs_review' => ['Pending', 'bg-amber-50 text-amber-800 ring-amber-200'],
        $cardStatus === 'not_configured' => ['Not set', 'bg-slate-100 text-slate-600 ring-slate-200'],
        default => ['Review', 'bg-slate-100 text-slate-600 ring-slate-200'],
    };

    $actionLabel = match (true) {
        $isComingSoon => 'Soon',
        $isLocked => 'Locked',
        $cardStatus === 'completed' => 'Review',
        $cardStatus === 'critical' => 'Complete',
        in_array($cardStatus, ['needs_review', 'not_configured'], true) => 'Configure',
        default => 'Open',
    };
@endphp

@if ($isClickable)
    <a
        href="{{ $card['href'] }}"
        class="group relative flex h-full min-h-[9.5rem] flex-col rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-slate-300 hover:shadow-md"
    >
@else
    <div class="relative flex h-full min-h-[9.5rem] flex-col rounded-lg border border-slate-200 bg-slate-50/80 p-3.5 shadow-sm">
@endif
        <span class="absolute inset-y-3 left-0 w-0.5 rounded-r {{ $statusBarClass }}" aria-hidden="true"></span>

        <div class="flex items-start justify-between gap-2 pl-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[#1e3a5f]">
                @include('loan.system.setup.icon', ['name' => $card['icon'] ?? 'default', 'class' => 'w-5 h-5'])
            </div>
            <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $statusPillClass }}">
                {{ $statusLabel }}
            </span>
        </div>

        <div class="mt-2.5 flex-1 pl-2">
            <h3 class="text-sm font-semibold leading-snug text-[#0f2744] {{ $isClickable ? 'group-hover:text-[#1e4d6b]' : '' }}">
                {{ $card['title'] }}
            </h3>
            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">
                {{ $card['desc'] }}
            </p>
        </div>

        <div class="mt-3 flex items-center justify-between gap-2 pl-2">
            @if (! empty($card['badge']))
                <span class="truncate text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ $card['badge'] }}</span>
            @else
                <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
                    {{ ['required' => 'Required', 'recommended' => 'Recommended', 'optional' => 'Optional'][$card['priority'] ?? 'optional'] ?? 'Optional' }}
                </span>
            @endif

            @if ($isClickable)
                <span class="inline-flex items-center gap-1 text-xs font-semibold text-[#1e4d6b] group-hover:text-[#163a52]">
                    {{ $actionLabel }}
                    <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            @else
                <span class="text-xs font-medium text-slate-400">{{ $actionLabel }}</span>
            @endif
        </div>
@if ($isClickable)
    </a>
@else
    </div>
@endif
