@props([
    'workspace' => null,
    /** @var array{all?: list<array{key: string, label: string, route?: string|null}>}|list<array{key: string, label: string, route?: string|null}> $tabs */
    'tabs' => [],
    'activeKey' => null,
    'mode' => 'route',
    'workspaceKey' => '',
    'workspaceLabel' => '',
])

@php
    $tabList = $tabs ?? [];
    if (! is_array($tabList)) {
        $tabList = [];
    }
    $isPanel = ($mode ?? 'route') === 'panel';
    $hasTabs = count($tabList) > 0;
@endphp

@if ($hasTabs)
    <div {{ $attributes->merge(['class' => 'loan-workspace-tabs print-hide w-full min-w-0']) }} data-loan-workspace="{{ $workspaceKey !== '' ? $workspaceKey : $workspace }}">
        <div class="rounded-lg border border-slate-200/90 bg-white shadow-sm overflow-hidden">
            <nav
                class="flex flex-nowrap gap-0.5 overflow-x-auto custom-scrollbar px-1.5 py-1"
                aria-label="{{ $workspaceLabel !== '' ? $workspaceLabel : 'Workspace' }} sections"
            >
                @foreach ($tabList as $tab)
                    @php
                        $key = (string) ($tab['key'] ?? '');
                        $label = (string) ($tab['label'] ?? $key);
                        $route = trim((string) ($tab['route'] ?? ''));
                        $active = $isPanel
                            ? ($activeKey !== null && $key === (string) $activeKey)
                            : \App\Support\LoanWorkspaceTabs::tabIsActive($tab);
                    @endphp
                    @if ($isPanel)
                        <button
                            type="button"
                            @click="setTab(@js($key))"
                            :aria-current="activeTab === @js($key) ? 'page' : false"
                            class="loan-workspace-tab shrink-0 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold transition-colors min-h-[32px] border border-transparent text-slate-700 hover:bg-slate-100 whitespace-nowrap"
                            :class="activeTab === @js($key) ? 'bg-[#0f766e] border-[#0f766e] text-white shadow-sm' : ''"
                        >
                            {{ $label }}
                        </button>
                    @elseif ($route !== '' && \Illuminate\Support\Facades\Route::has($route))
                        <a
                            href="{{ \App\Support\LoanWorkspaceTabs::tabUrl($tab) }}"
                            data-turbo-frame="loan-main"
                            data-loan-nav="{{ implode('|', $tab['active'] ?? [$route]) }}"
                            data-loan-workspace-tab="{{ $key }}"
                            @if ($active) aria-current="page" @endif
                            @class([
                                'loan-workspace-tab shrink-0 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold transition-colors min-h-[32px] border border-transparent text-slate-700 hover:bg-slate-100 whitespace-nowrap',
                                'bg-[#0f766e] border-[#0f766e] text-white shadow-sm' => $active,
                            ])
                        >
                            {{ $label }}
                        </a>
                    @endif
                @endforeach
            </nav>
        </div>
    </div>
@endif
