<x-property.workspace
    title="Property list"
    subtitle="Portfolio hierarchy: buildings, metadata, and landlord portal access."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No properties"
    empty-hint="Add a property below, then open Units to add doors and rents."
>
    <x-slot name="above">
        <div class="grid gap-4 lg:grid-cols-2">
            <form method="post" action="{{ route('property.properties.store') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add property</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to auto-generate.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">City</label>
                        <select name="city" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            <option value="">Select…</option>
                            @foreach (config('kenya.cities', []) as $city)
                                <option value="{{ $city }}" @selected(old('city') === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                        @error('city')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Address</label>
                    <input
                        type="text"
                        name="address_line"
                        value="{{ old('address_line') }}"
                        class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2"
                        placeholder="Start typing an address…"
                        autocomplete="off"
                        data-ke-address-autocomplete
                        data-ke-address-endpoint="{{ route('property.geo.kenya_addresses', absolute: false) }}"
                        list="ke-address-suggestions"
                    />
                    <datalist id="ke-address-suggestions"></datalist>
                    @error('address_line')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save property</button>
            </form>

            <form id="link-landlord-form" method="post" action="{{ route('property.properties.landlords.attach') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 scroll-mt-24">
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Link landlord user</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Users must have the landlord portal role at registration.</p>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                    <select name="property_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @foreach ($properties as $p)
                            <option value="{{ $p->id }}" @selected(old('property_id', request('property_id')) == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord user</label>
                    <select name="user_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @forelse ($landlordUsers as $u)
                            <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @empty
                            <option value="" disabled>No landlord users yet</option>
                        @endforelse
                    </select>
                    @error('user_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ownership % (this link)</label>
                    <input type="number" name="ownership_percent" value="{{ old('ownership_percent', '100') }}" min="0" max="100" step="0.01" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('ownership_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Co-owners on the same property cannot exceed 100% in total.</p>
                </div>
                <button type="submit" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">Attach</button>
            </form>
        </div>

        @if (isset($landlordLinks) && $landlordLinks->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Landlord links</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Update ownership % or detach. New links are rejected if total ownership on a property would exceed 100%.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-600">
                                <th class="py-2 pr-4">Property</th>
                                <th class="py-2 pr-4">Landlord</th>
                                <th class="py-2 pr-4">Email</th>
                                <th class="py-2 pr-4">Ownership %</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($landlordLinks as $link)
                                <tr class="border-b border-slate-100 dark:border-slate-700/80">
                                    <td class="py-2 pr-4 text-slate-900 dark:text-white">{{ $link->property_name }}</td>
                                    <td class="py-2 pr-4">{{ $link->user_name }}</td>
                                    <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">{{ $link->user_email }}</td>
                                    <td class="py-2 pr-4">
                                        <form method="post" action="{{ route('property.properties.landlords.ownership') }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="property_id" value="{{ $link->property_id }}" />
                                            <input type="hidden" name="user_id" value="{{ $link->user_id }}" />
                                            <input
                                                type="number"
                                                name="ownership_percent"
                                                value="{{ old('ownership_percent', $link->ownership_percent) }}"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                class="w-24 rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1 text-sm"
                                            />
                                            <button type="submit" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">Save</button>
                                        </form>
                                    </td>
                                    <td class="py-2">
                                        <form method="post" action="{{ route('property.properties.landlords.detach') }}" data-swal-title="Detach landlord?" data-swal-confirm="Unlink this landlord from this property?" data-swal-confirm-text="Yes, detach">
                                            @csrf
                                            <input type="hidden" name="property_id" value="{{ $link->property_id }}" />
                                            <input type="hidden" name="user_id" value="{{ $link->user_id }}" />
                                            <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Detach</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-slot>

    <x-slot name="toolbar">
        <input type="search" data-table-filter="parent" autocomplete="off" placeholder="Search property…" class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2" />
    </x-slot>
</x-property.workspace>
