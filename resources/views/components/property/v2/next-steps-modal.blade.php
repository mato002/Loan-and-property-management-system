@php
    /** @var array|null $next */
    $next = session('next_steps');
    $open = $next !== null;

    $summary = null;
    $summaryLabel = null;
    $badge = $next['badge'] ?? null;

    if (is_array($next)) {
        foreach (['tenant', 'landlord', 'vendor', 'summary'] as $key) {
            if (! empty($next[$key]) && is_array($next[$key])) {
                $summary = $next[$key];
                $summaryLabel = match ($key) {
                    'tenant' => 'Tenant',
                    'landlord' => 'Landlord',
                    'vendor' => 'Vendor',
                    default => $next['summary_label'] ?? 'Details',
                };
                if (! $badge) {
                    $badge = match ($key) {
                        'tenant' => 'Tenant onboarding',
                        'landlord' => 'Landlord onboarding',
                        'vendor' => 'Vendor onboarding',
                        default => null,
                    };
                }
                break;
            }
        }
    }
@endphp

@if ($next)
    <div x-data="{ open: {{ $open ? 'true' : 'false' }} }">
        <x-property.modal
            show="open"
            close="open = false"
            name="next-steps"
            :title="$next['title'] ?? 'What next?'"
            max-width="lg"
            aria-label="Next steps"
        >
            @if ($badge)
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">
                    {{ $badge }}
                </p>
            @endif

            @if (!empty($next['message']))
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $next['message'] }}
                </p>
            @endif

            @if (is_array($summary))
                <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                    @if (!empty($summary['name']))
                        <div class="flex justify-between gap-3">
                            <dt class="font-medium text-slate-700 dark:text-slate-300">{{ $summaryLabel }}</dt>
                            <dd class="text-right">{{ $summary['name'] }}</dd>
                        </div>
                    @endif
                    @foreach (['phone', 'email', 'national_id', 'category'] as $field)
                        @if (!empty($summary[$field]))
                            <div class="flex justify-between gap-3">
                                <dt class="font-medium text-slate-700 dark:text-slate-300">{{ ucfirst(str_replace('_', ' ', $field)) }}</dt>
                                <dd class="text-right">{{ $summary[$field] }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            @endif

            @if (!empty($next['actions']) && is_array($next['actions']))
                <div class="mt-4 grid gap-2">
                    @foreach ($next['actions'] as $action)
                        @php
                            $href = $action['href'] ?? null;
                            $label = $action['label'] ?? null;
                            $kind = $action['kind'] ?? 'primary';
                        @endphp
                        @continue(!$href || !$label)

                        @php
                            $base = 'inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-colors min-h-[44px]';
                            $classes = match ($kind) {
                                'secondary' => $base.' border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800',
                                'ghost' => $base.' text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800',
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

            <x-slot name="footer">
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="min-h-[44px] rounded-xl px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                        @click="open = false"
                    >
                        Not now
                    </button>
                </div>
            </x-slot>
        </x-property.modal>
    </div>
@endif
