<x-property-layout>
    <x-slot name="header">Properties</x-slot>

    <x-property.page title="Properties">
        @php $activeView = $activeView ?? 'list'; @endphp

        <div class="property-workspace-tabs print-hide w-full min-w-0 mb-3">
            <div class="property-workspace-tabs-shell rounded-lg border border-slate-200/90 dark:border-slate-700 bg-white/95 dark:bg-slate-900/60 shadow-sm">
                <nav class="property-workspace-tabs-primary flex flex-nowrap gap-0.5 overflow-x-auto custom-scrollbar px-1.5 py-1" aria-label="Property views">
                    <a
                        href="{{ route('property.landlord.properties') }}"
                        data-turbo-frame="property-main"
                        @if ($activeView === 'list') aria-current="page" @endif
                        class="property-workspace-tab shrink-0 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold transition-colors min-h-[32px] border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 whitespace-nowrap aria-[current=page]:bg-emerald-600 aria-[current=page]:border-emerald-600 aria-[current=page]:text-white"
                    >All properties</a>
                    <a
                        href="{{ route('property.landlord.properties', ['view' => 'vacancies']) }}"
                        data-turbo-frame="property-main"
                        @if ($activeView === 'vacancies') aria-current="page" @endif
                        class="property-workspace-tab shrink-0 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold transition-colors min-h-[32px] border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 whitespace-nowrap aria-[current=page]:bg-emerald-600 aria-[current=page]:border-emerald-600 aria-[current=page]:text-white"
                    >Vacant &amp; notice units</a>
                </nav>
            </div>
        </div>

        @if ($activeView === 'vacancies')
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-white dark:bg-gray-800/70 shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Property</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Target rent</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vacancyUnits ?? [] as $u)
                            <tr class="border-t border-slate-100 dark:border-slate-700">
                                <td class="px-4 py-3">{{ $u['property'] }}</td>
                                <td class="px-4 py-3">{{ $u['unit'] }}</td>
                                <td class="px-4 py-3">{{ $u['status'] }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $u['rent'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No vacant or notice units.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <x-property.workspace
                title=""
                back-route="property.landlord.portfolio"
                :stats="$stats"
                :columns="$columns"
                :table-rows="$tableRows"
                empty-title="No properties linked"
                empty-hint=""
            >
                <x-slot name="actions">
                    <a
                        href="{{ route('property.landlord.properties.export') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
                    >Download summary (CSV)</a>
                </x-slot>
                <x-slot name="toolbar">
                    <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search property…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
                </x-slot>
            </x-property.workspace>
        @endif
    </x-property.page>
</x-property-layout>
