@if ($tabs !== [])
    <div {{ $attributes->merge(['class' => 'property-workspace-tabs print-hide']) }} data-property-workspace="{{ $workspaceKey }}">
        <div class="property-workspace-tabs-shell rounded-lg border border-slate-200/90 dark:border-slate-700 bg-white/95 dark:bg-slate-900/60 shadow-sm">
            <nav
                class="property-workspace-tabs-primary flex gap-0.5 overflow-x-auto custom-scrollbar px-1.5 py-1"
                aria-label="{{ $workspaceLabel }} workspace sections"
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
                        class="property-workspace-tab shrink-0 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold transition-colors min-h-[32px] border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 aria-[current=page]:bg-emerald-600 aria-[current=page]:border-emerald-600 aria-[current=page]:text-white"
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>

            @include('components.property.partials.workspace_tabs_subnav')
        </div>
    </div>
@endif
