<x-loan-layout>
    @php
        $allSetupCards = collect($modules)->flatMap(fn (array $module) => $module['cards'] ?? []);
        $setupTotal = $allSetupCards->count();
        $setupCompleted = $allSetupCards->where('status', 'completed')->count();
        $setupPending = $allSetupCards->whereIn('status', ['not_configured', 'needs_review'])->count();
        $setupCritical = $allSetupCards->where('status', 'critical')->count();
    @endphp

    <div class="min-h-[calc(100vh-5rem)] bg-white px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            {{-- Page header --}}
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <a
                            href="{{ route('loan.dashboard') }}"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 transition hover:bg-slate-50"
                            title="Back to dashboard"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </a>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#1e4d6b]">Loan MFI</p>
                    </div>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#0f2744]">System Setup</h1>
                    <p class="mt-1 text-sm text-slate-500">Configure core loan operations.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('loan.system.setup.preferences') }}" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                        General settings
                    </a>
                    <a href="{{ route('loan.system.form_setup.page', ['page' => 'loan-settings']) }}" class="inline-flex items-center rounded-md bg-[#0f2744] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#163a52]">
                        Loan settings
                    </a>
                </div>
            </div>

            {{-- Summary strip --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Total items</p>
                    <p class="mt-0.5 text-xl font-bold text-[#0f2744]">{{ $setupTotal }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 px-3 py-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Completed</p>
                    <p class="mt-0.5 text-xl font-bold text-emerald-800">{{ $setupCompleted }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50/40 px-3 py-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Pending</p>
                    <p class="mt-0.5 text-xl font-bold text-amber-900">{{ $setupPending }}</p>
                </div>
                <div class="rounded-lg border {{ $setupCritical > 0 ? 'border-red-200 bg-red-50/50' : 'border-slate-200 bg-white' }} px-3 py-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide {{ $setupCritical > 0 ? 'text-red-700' : 'text-slate-500' }}">Critical</p>
                    <p class="mt-0.5 text-xl font-bold {{ $setupCritical > 0 ? 'text-red-800' : 'text-slate-700' }}">{{ $setupCritical }}</p>
                </div>
                <div class="col-span-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 sm:col-span-4 lg:col-span-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Readiness</p>
                        <p class="text-sm font-bold text-[#0f2744]">{{ $readinessPercent }}%</p>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-[#1e4d6b] transition-all" style="width: {{ $readinessPercent }}%"></div>
                    </div>
                </div>
            </div>

            @if (! empty($quickActions))
                <div class="flex flex-wrap gap-2">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-[#0f2744]">
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Module sections --}}
            <div class="space-y-8">
                @foreach ($modules as $module)
                    @php
                        $moduleToneClass = ($module['status_tone'] ?? 'orange') === 'green'
                            ? 'text-emerald-700 bg-emerald-50 ring-emerald-200'
                            : (($module['status_tone'] ?? '') === 'red'
                                ? 'text-red-700 bg-red-50 ring-red-200'
                                : 'text-amber-800 bg-amber-50 ring-amber-200');
                    @endphp
                    <section id="module-{{ $module['key'] }}" class="scroll-mt-6">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2">
                            <div>
                                <h2 class="text-sm font-bold uppercase tracking-[0.12em] text-[#0f2744]">{{ $module['title'] }}</h2>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $module['summary'] ?? '' }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $moduleToneClass }}">
                                {{ $module['status_label'] ?? 'In progress' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($module['cards'] as $card)
                                @include('loan.system.setup.partials.setup_card', ['card' => $card])
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-loan-layout>
