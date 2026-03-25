@php
    /** @var array|null $next */
    $next = session('next_steps');
    $open = $next !== null;
@endphp

@if ($next)
    <div
        x-data="{ open: {{ $open ? 'true' : 'false' }} }"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Next steps"
        @keydown.escape.window="open = false"
    >
        <button
            type="button"
            class="absolute inset-0 bg-slate-950/50"
            aria-label="Close modal"
            @click="open = false"
        ></button>

        <div
            x-transition
            class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">
                        {{ $next['title'] ?? 'What next?' }}
                    </h3>
                    @if (!empty($next['message']))
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $next['message'] }}
                        </p>
                    @endif
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close"
                    @click="open = false"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            @if (!empty($next['actions']) && is_array($next['actions']))
                <div class="mt-4 grid gap-2">
                    @foreach ($next['actions'] as $action)
                        @php
                            $href = $action['href'] ?? null;
                            $label = $action['label'] ?? null;
                            $kind = $action['kind'] ?? 'primary'; // primary|secondary|ghost
                        @endphp
                        @continue(!$href || !$label)

                        @php
                            $base = 'inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-colors';
                            $classes = match ($kind) {
                                'secondary' => $base.' border border-slate-300 text-slate-800 hover:bg-slate-50',
                                'ghost' => $base.' text-slate-700 hover:bg-slate-100',
                                default => $base.' bg-blue-600 text-white hover:bg-blue-700',
                            };
                        @endphp

                        <a
                            href="{{ $href }}"
                            class="{{ $classes }}"
                            @click="open = false"
                            @if (!empty($action['turbo_frame']))
                                data-turbo-frame="{{ $action['turbo_frame'] }}"
                            @endif
                        >
                            @if (!empty($action['icon']))
                                <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                            @endif
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 flex items-center justify-end">
                <button
                    type="button"
                    class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-800"
                    @click="open = false"
                >
                    Not now
                </button>
            </div>
        </div>
    </div>
@endif

