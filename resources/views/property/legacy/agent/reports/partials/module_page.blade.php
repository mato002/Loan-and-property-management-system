<x-property.workspace
    title="Reports"
    subtitle="Standalone reports module for agents."
    :stats="[]"
    :columns="[]"
>
   

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <h3 class="px-4 py-2 text-base font-semibold text-white {{ $headerTone ?? 'bg-emerald-700' }}">{{ $panelTitle }}</h3>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($panelItems as $item)
                <a
                    href="{{ route($item['route'], $item['params'] ?? [], false) }}"
                    data-turbo-frame="property-main"
                    class="group block rounded-xl border border-slate-200 bg-white p-4 text-sm font-medium text-slate-900 shadow-sm hover:border-emerald-300 hover:bg-emerald-50/50"
                >
                    <span class="line-clamp-2">{{ $item['label'] }}</span>
                    <span class="mt-2 inline-flex items-center text-xs font-semibold text-emerald-700 group-hover:text-emerald-800">
                        Open report
                        <i class="fa-solid fa-arrow-right ml-1 text-[10px]" aria-hidden="true"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
</x-property.workspace>
