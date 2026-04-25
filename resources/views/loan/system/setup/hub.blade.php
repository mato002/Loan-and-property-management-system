<x-loan-layout>
    <div class="min-h-[calc(100vh-5rem)] bg-slate-100 py-8 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-[1180px] ml-0 mr-auto lg:ml-8 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-6 sm:px-8 py-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-3">
                            <a
                                href="{{ route('loan.dashboard') }}"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors"
                                title="Back"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </a>
                            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight">System Setup</h1>
                        </div>
                        <p class="text-sm text-slate-600">
                            Configure and activate your lending and operational infrastructure.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('loan.system.setup.preferences') }}" class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 transition-colors">
                            Open General Settings
                        </a>
                        <a href="{{ route('loan.system.form_setup.page', ['page' => 'loan-settings']) }}" class="inline-flex items-center rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                            Open Loan Settings
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-semibold text-slate-800">System Readiness: {{ $readinessPercent }}%</p>
                    <span class="text-xs text-slate-500">Operational readiness snapshot</span>
                </div>
                <div class="h-2.5 rounded-full bg-slate-200 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $readinessPercent }}%"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                    @foreach ($moduleBreakdown as $module)
                        <a href="#module-{{ $module['key'] }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs hover:border-blue-300 hover:bg-blue-50/40 transition-colors">
                            <p class="font-semibold text-slate-700">{{ $module['title'] }}</p>
                            <p class="text-slate-500 mt-0.5">{{ $module['progress_percent'] }}% ready</p>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="space-y-6">
                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 sm:p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick Actions</p>
                        <div class="mt-3 flex flex-wrap gap-2.5">
                            @foreach ($quickActions as $action)
                                <a href="{{ $action['href'] }}" class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 transition-colors">
                                    {{ $action['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    @foreach ($modules as $module)
                        @php
                            $statusChipClasses = [
                                'green' => 'bg-green-100 text-green-700 border-green-200',
                                'orange' => 'bg-orange-100 text-orange-700 border-orange-200',
                                'red' => 'bg-red-100 text-red-700 border-red-200',
                            ];
                            $statusClass = $statusChipClasses[$module['status_tone']] ?? $statusChipClasses['orange'];
                        @endphp
                        <details id="module-{{ $module['key'] }}" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden" open>
                            <summary class="list-none cursor-pointer px-5 py-4 border-b border-slate-200/80">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-base font-semibold text-slate-900">{{ $module['title'] }}</p>
                                        <p class="text-xs text-slate-500 mt-1">{{ $module['summary'] }}</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $module['status_label'] }}
                                    </span>
                                </div>
                            </summary>
                            <div class="p-4 sm:p-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 min-[1200px]:grid-cols-3 gap-4">
                                    @foreach ($module['cards'] as $card)
                                        @php
                                            $cardStatus = $card['status'] ?? 'not_configured';
                                            $isLocked = (bool) ($card['locked'] ?? false);
                                            $isComingSoon = (bool) ($card['coming_soon'] ?? false);
                                            $isClickable = ! $isComingSoon && ! empty($card['href']);
                                            $statusDotClass = [
                                                'completed' => 'bg-green-500',
                                                'needs_review' => 'bg-orange-500',
                                                'critical' => 'bg-red-500',
                                                'locked' => 'bg-purple-500',
                                                'not_configured' => 'bg-slate-400',
                                            ][$cardStatus] ?? 'bg-slate-400';
                                            $borderClass = [
                                                'completed' => 'border-green-300',
                                                'needs_review' => 'border-orange-300',
                                                'critical' => 'border-red-300',
                                                'locked' => 'border-purple-300',
                                                'not_configured' => 'border-slate-300',
                                            ][$cardStatus] ?? 'border-slate-300';
                                            $priorityText = [
                                                'required' => 'Required',
                                                'recommended' => 'Recommended',
                                                'optional' => 'Optional',
                                            ][$card['priority'] ?? 'optional'] ?? 'Optional';
                                        @endphp

                                        @if ($isClickable)
                                            <a href="{{ $card['href'] }}" class="group block rounded-xl border {{ $borderClass }} bg-white p-3 shadow-sm hover:shadow transition">
                                        @else
                                            <div class="rounded-xl border {{ $isLocked ? 'border-purple-300' : 'border-slate-300' }} bg-slate-50 p-3 shadow-sm">
                                        @endif
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                                                        @include('loan.system.setup.icon', ['name' => $card['icon']])
                                                    </div>
                                                    <span class="mt-1 h-2.5 w-2.5 rounded-full {{ $isLocked ? 'bg-purple-500' : $statusDotClass }}"></span>
                                                </div>

                                                <div class="mt-2 space-y-1.5">
                                                    <h3 class="text-sm font-semibold text-slate-900 {{ $isClickable ? 'group-hover:text-blue-700' : '' }}">
                                                        {{ $card['title'] }}
                                                    </h3>
                                                    <p class="text-xs leading-relaxed text-slate-600">{{ $card['desc'] }}</p>
                                                </div>

                                                <div class="mt-2">
                                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                        {{ $card['badge'] ?? $priorityText }}
                                                    </span>
                                                </div>
                                        @if ($isClickable)
                                            </a>
                                        @else
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</x-loan-layout>
