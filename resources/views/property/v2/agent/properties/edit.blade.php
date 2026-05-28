<x-property.workspace
    title="Edit property"
    subtitle="Update property details and manage linked landlords for this building."
    back-route="property.properties.list"
    :stats="[
        ['label' => 'Property', 'value' => $property->name, 'hint' => $property->code ?: 'No code'],
        ['label' => 'Linked landlords', 'value' => (string) $property->landlords->count(), 'hint' => 'Owners on this property'],
    ]"
    :columns="[]"
>
    <x-slot name="above">
        <div class="grid gap-4 lg:grid-cols-2">
            <form method="post" action="{{ route('property.properties.update', $property) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3">
                @csrf
                @method('PATCH')
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Property details</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                    <input type="text" name="name" value="{{ old('name', $property->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Code</label>
                        <input type="text" name="code" value="{{ old('code', $property->code) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">City</label>
                        <select name="city" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            <option value="">Select…</option>
                            @foreach (config('kenya.cities', []) as $city)
                                <option value="{{ $city }}" @selected(old('city', $property->city) === $city)>{{ $city }}</option>
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
                        value="{{ old('address_line', $property->address_line) }}"
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
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Commission %</label>
                    <input type="number" name="commission_percent" value="{{ old('commission_percent', $propertyCommissionPercent ?? null) }}" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional (uses default if empty)" />
                    @error('commission_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            </form>

            <form
                method="post"
                action="{{ route('property.properties.landlords.attach') }}"
                data-turbo-frame="property-main"
                data-turbo="false"
                class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3"
            >
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Link landlord</h3>
                <input type="hidden" name="property_id" value="{{ $property->id }}" />
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord user</label>
                    <select name="user_id" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Select…</option>
                        @foreach ($landlordUsers as $u)
                            <option value="{{ $u->id }}" @selected((string) old('user_id') === (string) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    @error('user_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ownership %</label>
                    <input type="number" name="ownership_percent" value="{{ old('ownership_percent', '100') }}" min="0" max="100" step="0.01" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('ownership_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/80">Attach landlord</button>
            </form>
        </div>
    </x-slot>

    <div class="overflow-x-auto w-full min-w-0 -mx-4 px-4 sm:mx-0 sm:px-0">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Landlord</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Email</th>
                    <th class="px-3 sm:px-4 py-3 whitespace-nowrap">Ownership %</th>
                    <th class="px-3 sm:px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($property->landlords as $u)
                    <tr class="border-t border-slate-100 dark:border-slate-700/80">
                        <td class="px-3 sm:px-4 py-3">{{ $u->name }}</td>
                        <td class="px-3 sm:px-4 py-3 text-slate-600 dark:text-slate-300">{{ $u->email }}</td>
                        <td class="px-3 sm:px-4 py-3">
                        <form
                            method="post"
                            action="{{ route('property.properties.landlords.ownership') }}"
                            data-turbo-frame="property-main"
                            data-turbo="false"
                            class="flex flex-wrap items-center gap-2"
                        >
                                @csrf
                                <input type="hidden" name="property_id" value="{{ $property->id }}" />
                                <input type="hidden" name="user_id" value="{{ $u->id }}" />
                                <input type="number" name="ownership_percent" value="{{ (float) ($u->pivot->ownership_percent ?? 0) }}" min="0" max="100" step="0.01" class="w-24 rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2 py-1 text-sm" />
                                <button type="submit" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">Save</button>
                            </form>
                        </td>
                        <td class="px-3 sm:px-4 py-3">
                            <form
                                method="post"
                                action="{{ route('property.properties.landlords.detach') }}"
                                data-turbo-frame="property-main"
                                data-turbo="false"
                                data-swal-title="Detach landlord?"
                                data-swal-confirm="Unlink this landlord from the property?"
                                data-swal-confirm-text="Yes, detach"
                            >
                                @csrf
                                <input type="hidden" name="property_id" value="{{ $property->id }}" />
                                <input type="hidden" name="user_id" value="{{ $u->id }}" />
                                <button type="submit" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Detach</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No landlords linked to this property yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>

