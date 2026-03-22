<x-property.workspace
    title="Landlords"
    subtitle="Landlord portal accounts and the properties each one is linked to. New links are created from the property list."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="toolbar">
        <input
            type="search"
            data-table-filter="parent"
            autocomplete="off"
            placeholder="Search name, email, property…"
            class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
        />
        <a
            href="{{ route('property.properties.list') }}#link-landlord-form"
            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
            Link landlord to property
        </a>
    </x-slot>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Name</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Email</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Properties</th>
                    <th class="px-3 sm:px-4 py-3 min-w-[12rem]">Building names</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($landlords as $u)
                    @php
                        $props = $u->landlordProperties;
                        $names = $props->pluck('name')->all();
                        $namesLine = $props->isEmpty() ? '' : implode(', ', $names);
                        $filterText = mb_strtolower(
                            implode(' ', array_filter([$u->name, $u->email, $namesLine]))
                        );
                    @endphp
                    <tr
                        class="border-t border-slate-100 dark:border-slate-700/80 hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                        data-filter-text="{{ e($filterText) }}"
                    >
                        <td class="px-3 sm:px-4 py-3 text-slate-900 dark:text-white font-medium">{{ $u->name }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ $u->email }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ $props->count() }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-300">
                            @if ($props->isEmpty())
                                <span class="text-slate-400 dark:text-slate-500">Not linked — use “Link landlord to property”</span>
                            @else
                                <span class="leading-relaxed">{{ $namesLine }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-14 text-center align-middle">
                            <p class="font-medium text-slate-700 dark:text-slate-200">No landlord accounts yet</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">Register users with the landlord portal role, then attach them to properties from the property list.</p>
                            <a href="{{ route('property.properties.list') }}" class="mt-4 inline-flex text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">Open property list</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
