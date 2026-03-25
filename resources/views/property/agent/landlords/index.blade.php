<x-property.workspace
    title="Landlords"
    subtitle="Landlord portal accounts and the properties each one is linked to. New links are created from the property list."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="above">
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Landlord setup</p>
            <p class="mt-1 text-sm text-slate-600">Create landlord accounts here, then link them to properties (ownership %) so they can access landlord portal reports and earnings.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.properties.list', absolute: false) }}#link-landlord-form" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Link landlord to property
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.properties.list', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Properties
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <form method="post" action="{{ route('property.landlords.onboard') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Onboard landlord</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Full name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Temporary password</label>
                    <input type="text" name="password" value="{{ old('password') }}" required minlength="8" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Link to property (optional)</label>
                    <select name="property_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Not now</option>
                        @foreach ($properties as $p)
                            <option value="{{ $p->id }}" @selected((string) old('property_id') === (string) $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ownership % (if property selected)</label>
                <input type="number" name="ownership_percent" value="{{ old('ownership_percent', '100') }}" min="0" max="100" step="0.01" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('ownership_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create landlord account</button>
        </form>
    </x-slot>

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
