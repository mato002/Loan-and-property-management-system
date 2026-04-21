<x-loan-layout>
    <x-loan.page
        title="Edit group"
        subtitle="{{ $default_client_group->name }}"
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.default_groups.show', $default_client_group) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to members
            </a>
        </x-slot>

        <div
            x-data="{
                showPicker: false,
                search: '',
                selected: @js(collect(old('loan_client_ids', $selectedClientIds ?? []))->map(fn ($id) => (int) $id)->values()->all()),
                get filteredClients() {
                    const q = this.search.trim().toLowerCase();
                    if (!q) return window.__groupClientOptions || [];
                    return (window.__groupClientOptions || []).filter((c) => {
                        const hay = `${c.client_number} ${c.full_name} ${c.phone || ''} ${c.id_number || ''}`.toLowerCase();
                        return hay.includes(q);
                    });
                },
                toggleClient(id) {
                    const n = Number(id);
                    if (this.selected.includes(n)) {
                        this.selected = this.selected.filter((x) => x !== n);
                    } else {
                        this.selected.push(n);
                    }
                },
                toggleAllVisible() {
                    const ids = this.filteredClients.map((c) => Number(c.id));
                    if (ids.length === 0) return;
                    const missing = ids.filter((id) => !this.selected.includes(id));
                    if (missing.length === 0) {
                        this.selected = this.selected.filter((id) => !ids.includes(id));
                        return;
                    }
                    this.selected = Array.from(new Set([...this.selected, ...ids]));
                },
                removeSelected(id) {
                    const n = Number(id);
                    this.selected = this.selected.filter((x) => x !== n);
                },
                selectedLabel(id) {
                    const row = (window.__groupClientOptions || []).find((c) => Number(c.id) === Number(id));
                    return row ? `${row.full_name} (${row.client_number})` : `Client #${id}`;
                }
            }"
            class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-4xl"
        >
            <form method="post" action="{{ route('loan.clients.default_groups.update', $default_client_group) }}" class="space-y-5">
                @csrf
                @method('patch')
                <div>
                    <x-input-label for="name" value="Group name" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $default_client_group->name)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $default_client_group->description) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>
                <div class="border-y border-slate-200 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="showPicker = true" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Select Contacts</button>
                        <input type="text" x-model="search" placeholder="Search Contact" class="h-10 w-64 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <div class="ml-auto flex items-center gap-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="select_all_clients" value="1" @checked(old('select_all_clients')) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                All Clients ({{ number_format((int) ($totalClientCount ?? 0)) }})
                            </label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">Selected Contacts</p>
                    <template x-if="selected.length === 0">
                        <p class="text-sm text-slate-500">No contacts selected yet.</p>
                    </template>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="id in selected" :key="id">
                            <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                                <span x-text="selectedLabel(id)"></span>
                                <button type="button" @click="removeSelected(id)" class="text-slate-500 hover:text-red-600">✕</button>
                            </span>
                        </template>
                    </div>
                </div>
                <template x-for="id in selected" :key="'hidden-'+id">
                    <input type="hidden" name="loan_client_ids[]" :value="id">
                </template>
                <x-primary-button type="submit">{{ __('Save changes') }}</x-primary-button>
            </form>

            <div x-show="showPicker" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/55 p-4">
                <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                        <h3 class="text-xl font-semibold text-slate-800">Select Clients</h3>
                        <button type="button" @click="showPicker = false" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700">✕</button>
                    </div>
                    <div class="p-5">
                        <div class="mb-3">
                            <input type="text" x-model="search" placeholder="Search by name, number, phone..." class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        </div>
                        <div class="max-h-80 overflow-y-auto rounded-lg border border-slate-200 p-3">
                            <template x-for="client in filteredClients" :key="client.id">
                                <label class="mb-2 flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" :checked="selected.includes(Number(client.id))" @change="toggleClient(client.id)" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span x-text="client.full_name"></span>
                                </label>
                            </template>
                            <template x-if="filteredClients.length === 0">
                                <p class="text-sm text-slate-500">No clients match your search.</p>
                            </template>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <button type="button" @click="toggleAllVisible()" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Select All</button>
                            <button type="button" @click="showPicker = false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Add Clients</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    window.__groupClientOptions = @json(($clientOptions ?? collect())->map(function ($c) {
        return [
            'id' => (int) $c->id,
            'client_number' => (string) $c->client_number,
            'full_name' => (string) $c->full_name,
            'phone' => (string) ($c->phone ?? ''),
            'id_number' => (string) ($c->id_number ?? ''),
        ];
    })->values());
</script>
