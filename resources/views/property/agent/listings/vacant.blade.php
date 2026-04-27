<x-property.workspace
    title="Vacant unit listings"
    back-route="property.listings.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="above">
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Vacancy publishing flow</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.properties.units', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Unit status
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.listings.ads', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Live on website
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <a
            href="{{ route('property.listings.create', absolute: false) }}"
            data-turbo-frame="property-main"
            data-property-nav="property.listings.create"
            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
            Setup a listing
        </a>
        <input
            type="search"
            data-table-filter="parent"
            autocomplete="off"
            placeholder="Search unit, property, rent…"
            class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
        />
        <select
            data-table-filter="parent"
            class="w-full min-w-0 sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
        >
            <option value="">All statuses</option>
            <option value="featured">Featured</option>
            <option value="standard">Standard (on site)</option>
        </select>
    </x-slot>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Unit</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Property</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Asking rent</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Vacant since</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Photos</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Status</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vacantUnits as $u)
                    @php
                        $statusWord = $u->public_listing_published ? 'featured' : 'standard';
                        $filterText = mb_strtolower(
                            implode(' ', [
                                (string) $u->label,
                                (string) $u->property->name,
                                (string) $u->rent_amount,
                                \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount),
                                $u->vacant_since?->format('Y-m-d') ?? '',
                                (string) $u->publicImages->count(),
                                $statusWord,
                            ])
                        );
                    @endphp
                    <tr
                        class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        data-filter-text="{{ e($filterText) }}"
                    >
                        <td class="px-3 sm:px-4 py-3 text-slate-900 dark:text-white font-medium">{{ $u->label }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200">{{ $u->property->name }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount) }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-400">{{ $u->vacant_since?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ $u->publicImages->count() }}</td>
                        <td class="px-3 sm:px-4 py-3">
                            @if ($u->public_listing_published)
                                <span class="inline-flex rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-0.5 text-xs font-semibold">Featured</span>
                            @else
                                <span class="inline-flex rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200 px-2 py-0.5 text-xs font-semibold">On Discover</span>
                            @endif
                        </td>
                        <td class="px-3 sm:px-4 py-3">
                            <a href="{{ route('property.listings.create', ['selected_unit' => $u->id], absolute: false) }}#listing-publish" data-turbo-frame="property-main" data-property-nav="property.listings.create" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">Photos &amp; publish</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-14 text-center text-slate-600 dark:text-slate-400">No vacant units. Create units under Properties first.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
