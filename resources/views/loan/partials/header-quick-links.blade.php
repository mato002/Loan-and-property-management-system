{{-- Full quick-jump strip — rendered in loan topbar only --}}
@php
    use App\Support\LoanNavigation;

    $quickLinks = LoanNavigation::quickLinksForUser(auth()->user());
@endphp

@if ($quickLinks !== [])
    <div class="border-t border-slate-100/90 bg-white/60">
        <nav
            class="flex flex-nowrap items-center gap-1 overflow-x-auto custom-scrollbar py-1.5"
            aria-label="Quick navigation"
        >
            <span class="mr-1 shrink-0 pl-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Quick</span>
            @foreach ($quickLinks as $link)
                @if (Route::has($link['route']))
                    <a
                        href="{{ route($link['route']) }}"
                        data-turbo-frame="loan-main"
                        data-loan-nav="{{ $link['nav'] }}"
                        @if ($link['active']) aria-current="page" @endif
                        class="inline-flex shrink-0 items-center rounded-md px-2 py-1 text-[11px] font-semibold transition-colors whitespace-nowrap {{ $link['active'] ? 'bg-[#2f4f4f] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-[#2f4f4f]' }}"
                        title="{{ $link['label'] }}"
                    >
                        {{ $link['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
@endif
