@if ($showSubTabs && $subTabs !== [])
    <nav
        class="property-workspace-tabs-sub flex flex-nowrap items-center gap-0.5 overflow-x-auto custom-scrollbar border-t border-slate-100 dark:border-slate-800 px-1.5 py-1"
        aria-label="{{ $subTabGroupLabel }} sections"
        data-property-workspace-tabs-nav="sub"
    >
        <span class="property-workspace-subnav-label shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 bg-slate-100/90 dark:bg-slate-800/80">
            {{ $subTabGroupLabel }}
        </span>
        @foreach ($subTabs as $subTab)
            @php $subActive = $tabIsActive($subTab); @endphp
            <a
                href="{{ \App\Support\Property\PropertyWorkspaceTabs::tabUrl($subTab) }}"
                data-turbo-frame="property-main"
                data-property-nav="{{ implode('|', $subTab['active'] ?? []) }}"
                data-property-workspace-subtab="{{ $subTab['key'] ?? '' }}"
                @if ($subActive) aria-current="page" @endif
                class="property-workspace-subtab shrink-0 inline-flex items-center rounded-md px-2 py-1 text-[11px] font-semibold transition-colors min-h-[28px] border border-slate-200/90 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 aria-[current=page]:bg-emerald-50 aria-[current=page]:border-emerald-300 aria-[current=page]:text-emerald-800 dark:aria-[current=page]:bg-emerald-950/40 dark:aria-[current=page]:text-emerald-200"
            >
                {{ $subTab['label'] }}
            </a>
        @endforeach
    </nav>
@endif
