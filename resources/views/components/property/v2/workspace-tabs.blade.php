@if ($tabs !== [])
    <div {{ $attributes->merge(['class' => 'property-workspace-tabs print-hide space-y-2']) }} data-property-workspace="{{ $workspaceKey }}" data-property-mobile-sticky-tabs>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-900/50 shadow-sm">
            <div class="px-2 pt-2 pb-1 border-b border-slate-100 dark:border-slate-800">
                <p class="px-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ $workspaceLabel }} workspace
                </p>
            </div>
            <nav
                class="property-workspace-tabs-primary flex gap-1 overflow-x-auto custom-scrollbar px-2 py-2"
                aria-label="{{ $workspaceLabel }} sections"
                data-property-workspace-tabs-nav="primary"
            >
                @foreach ($tabs as $tab)
                    @php $active = $tabIsActive($tab); @endphp
                    <a
                        href="{{ \App\Support\Property\PropertyWorkspaceTabs::tabUrl($tab) }}"
                        data-turbo-frame="property-main"
                        data-property-nav="{{ implode('|', $tab['active'] ?? []) }}"
                        data-property-workspace-tab="{{ $tab['key'] ?? '' }}"
                        @if ($active) aria-current="page" @endif
                        class="property-workspace-tab shrink-0 inline-flex items-center rounded-lg px-3 py-2 text-xs sm:text-sm font-semibold transition-colors min-h-[40px] border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 aria-[current=page]:bg-emerald-600 aria-[current=page]:border-emerald-600 aria-[current=page]:text-white aria-[current=page]:shadow-sm"
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>

            @include('components.property.partials.workspace_tabs_subnav')
        </div>
    </div>
@endif
