<x-property.workspace
    title="Live on website"
    back-route="property.listings.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="toolbar">
        <input
            type="search"
            data-table-filter="parent"
            autocomplete="off"
            placeholder="Search unit or property…"
            class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
        />
        <a
            href="{{ route('public.properties') }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm font-medium text-slate-700 dark:text-slate-200 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700/50"
        >
            Open Discover properties ↗
        </a>
    </x-slot>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Unit</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Property</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Rent</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Photos</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Public page</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Portal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($publishedUnits as $u)
                    @php
                        $filterText = mb_strtolower(
                            implode(' ', [(string) $u->label, (string) $u->property->name, (string) $u->rent_amount])
                        );
                    @endphp
                    <tr
                        class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        data-filter-text="{{ e($filterText) }}"
                    >
                        <td class="px-3 sm:px-4 py-3 text-slate-900 dark:text-white font-medium">{{ $u->label }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">{{ $u->property->name }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ $u->publicImages->count() }}</td>
                        <td class="px-3 sm:px-4 py-3">
                            <a
                                href="{{ route('public.property_details', $u) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-600 dark:text-blue-400 font-medium hover:underline"
                            >View live ↗</a>
                        </td>
                        <td class="px-3 sm:px-4 py-3">
                            <a href="{{ route('property.listings.create', ['selected_unit' => $u->id], absolute: false) }}#listing-publish" data-turbo-frame="property-main" data-property-nav="property.listings.create" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">Edit listing</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-14 text-center align-middle">
                            <p class="font-medium text-slate-700 dark:text-slate-200">No published listings yet</p>
                            <a href="{{ route('property.listings.vacant', absolute: false) }}" data-turbo-frame="property-main" data-property-nav="property.listings.vacant" class="mt-4 inline-flex text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Open vacant units</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
