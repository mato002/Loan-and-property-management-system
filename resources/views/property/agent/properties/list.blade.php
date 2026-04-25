<x-property.workspace
    title="Property list"
    subtitle="Portfolio hierarchy: buildings, metadata, and landlord portal access."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No properties"
    empty-hint="Add a property below, then open Units to add doors and rents."
>
    <x-slot name="above">
        @php
            $propertyFormHasErrors = $errors->has('name')
                || $errors->has('code')
                || $errors->has('city')
                || $errors->has('commission_percent')
                || $errors->has('address_line')
                || $errors->has('charge_templates');
            $linkLandlordFormHasErrors = $errors->has('property_id')
                || $errors->has('user_id')
                || $errors->has('ownership_percent');
        @endphp
        <div x-data="{ showPropertyForm: @js($propertyFormHasErrors), showLinkLandlordForm: @js($linkLandlordFormHasErrors) }" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-4 text-base font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 sm:w-auto"
                    @click="showPropertyForm = !showPropertyForm"
                >
                    <i class="fa-solid fa-building-circle-plus text-lg" aria-hidden="true"></i>
                    <span x-text="showPropertyForm ? 'Hide add property form' : 'Add property'"></span>
                </button>
                <button
                    type="button"
                    class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-emerald-600 px-6 py-4 text-base font-bold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-700 sm:w-auto"
                    @click="showLinkLandlordForm = !showLinkLandlordForm"
                >
                    <i class="fa-solid fa-link text-lg" aria-hidden="true"></i>
                    <span x-text="showLinkLandlordForm ? 'Hide link landlord form' : 'Link landlord user'"></span>
                </button>
            </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <form
                method="post"
                action="{{ route('property.properties.store') }}"
                x-show="showPropertyForm"
                x-cloak
                x-data="{
                    showChargeBuilder: @js(count((array) old('charge_templates', [])) > 0),
                    chargeTypeOptions: ['water', 'service', 'garbage', 'other'],
                    charges: (() => {
                        const seed = @js(old('charge_templates', []));
                        if (Array.isArray(seed) && seed.length > 0) return seed;
                        return [];
                    })(),
                    init() {
                        this.charges.forEach((charge) => {
                            const type = String(charge?.charge_type || '').trim().toLowerCase();
                            if (type !== '' && !this.chargeTypeOptions.includes(type)) {
                                this.chargeTypeOptions.push(type);
                            }
                        });
                    },
                    addCharge() {
                        this.showChargeBuilder = true;
                        this.charges.push({ charge_type: 'water', label: '', rate_per_unit: '', fixed_charge: '', notes: '' });
                    },
                    addChargeType(index) {
                        const raw = window.prompt('New charge type (e.g. internet, security, sewer):', '');
                        if (!raw) return;
                        const normalized = String(raw)
                            .trim()
                            .toLowerCase()
                            .replace(/[^a-z0-9]+/g, '_')
                            .replace(/^_+|_+$/g, '');
                        if (!normalized) return;
                        if (!this.chargeTypeOptions.includes(normalized)) {
                            this.chargeTypeOptions.push(normalized);
                        }
                        if (this.charges[index]) {
                            this.charges[index].charge_type = normalized;
                        }
                    },
                    removeCharge(index) {
                        this.charges.splice(index, 1);
                        if (this.charges.length === 0) this.showChargeBuilder = false;
                    }
                }"
                class="property-attention-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3"
            >
                @csrf
                <h3 class="property-attention-title dark:text-white">Add Property</h3>
                <p class="property-attention-hint dark:text-slate-300">Start here: create the property first, then add units and landlord links.</p>
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
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Commission %</label>
                    <input type="number" name="commission_percent" value="{{ old('commission_percent') }}" min="0" max="100" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional (uses default if empty)" />
                    @error('commission_percent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/70 dark:bg-slate-900/40 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Utility charge templates (optional)</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">If this property has bills (e.g. water), tap <span class="font-medium">Add charge</span>.</p>
                        </div>
                        <button type="button" @click="addCharge()" class="rounded-lg border border-slate-300 dark:border-slate-500 bg-white dark:bg-gray-900 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-gray-800">Add charge</button>
                    </div>

                    <div x-show="showChargeBuilder" x-cloak class="mt-3 space-y-3">
                        <template x-for="(charge, index) in charges" :key="index">
                            <div class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 p-3 space-y-2">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
                                            <button type="button" @click="addChargeType(index)" class="rounded border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">+</button>
                                        </div>
                                        <select :name="`charge_templates[${index}][charge_type]`" x-model="charge.charge_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                            <template x-for="type in chargeTypeOptions" :key="`charge-type-${type}`">
                                                <option :value="type" x-text="type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ')"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Label</label>
                                        <input :name="`charge_templates[${index}][label]`" x-model="charge.label" type="text" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Water bill" />
                                    </div>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rate / unit</label>
                                        <input :name="`charge_templates[${index}][rate_per_unit]`" x-model="charge.rate_per_unit" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="0.00" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fixed charge</label>
                                        <input :name="`charge_templates[${index}][fixed_charge]`" x-model="charge.fixed_charge" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="0.00" />
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input :name="`charge_templates[${index}][notes]`" x-model="charge.notes" type="text" class="flex-1 min-w-[12rem] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Optional notes" />
                                    <button type="button" @click="removeCharge(index)" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Remove</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    @error('charge_templates')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save property</button>
            </form>

            <form
                id="link-landlord-form"
                method="post"
                action="{{ route('property.properties.landlords.attach') }}"
                data-turbo-frame="property-main"
                data-turbo="false"
                x-show="showLinkLandlordForm"
                x-cloak
                class="property-attention-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 scroll-mt-24"
                x-data="{
                    showNewLandlord: false,
                    creating: false,
                    onboardUrl: '{{ route('property.landlords.onboard_json') }}',
                    async createLandlord() {
                        const name = (document.getElementById('new-landlord-name')?.value || '').trim();
                        const email = (document.getElementById('new-landlord-email')?.value || '').trim();
                        const password = (document.getElementById('new-landlord-password')?.value || '').trim();
                        if (!name || !email || !password) {
                            if (window.Swal) Swal.fire({ icon: 'warning', title: 'Missing fields', text: 'Name, email and password are required.' });
                            else alert('Name, email and password are required.');
                            return;
                        }
                        this.creating = true;
                        try {
                            const token = document.querySelector('#link-landlord-form input[name=_token]')?.value;
                            const res = await fetch(this.onboardUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token || ''
                                },
                                body: JSON.stringify({ name, email, password })
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok || !data.ok) {
                                const msg = (data && (data.message || data.error)) ? (data.message || data.error) : 'Could not create landlord.';
                                if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: msg });
                                else alert(msg);
                                return;
                            }
                            const u = data.user;
                            const sel = document.getElementById('landlord-user-select');
                            if (sel && u && u.id) {
                                const opt = document.createElement('option');
                                opt.value = String(u.id);
                                opt.textContent = `${u.name} (${u.email})`;
                                sel.appendChild(opt);
                                sel.value = String(u.id);
                            }
                            if (window.Swal) Swal.fire({ icon: 'success', title: 'Landlord created', text: data.message || 'Created.', timer: 1800, showConfirmButton: false });
                            this.showNewLandlord = false;
                        } catch (e) {
                            if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Network/server error while creating landlord.' });
                            else alert('Network/server error while creating landlord.');
                        } finally {
                            this.creating = false;
                        }
                    }
                }"
            >
                <div class="mb-1 flex items-center justify-between gap-3">
                    <h3 class="property-attention-title dark:text-white">Link Landlord User</h3>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showLinkLandlordForm = false"
                    >
                        <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
                        Hide
                    </button>
                </div>
                @csrf
                <p class="property-attention-hint dark:text-slate-300">Assign a landlord account to a property so ownership and statements are connected.</p>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property</label>
                    <x-property.quick-create-select
                        name="property_id"
                        :required="true"
                        :options="collect($linkableProperties ?? [])->map(fn($p) => ['value' => $p->id, 'label' => $p->name, 'selected' => (string) old('property_id', request('property_id')) === (string) $p->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create property',
                            'endpoint' => route('property.properties.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Property name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Prady Court'],
                                ['name' => 'code', 'label' => 'Code (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Auto if blank'],
                                ['name' => 'address_line', 'label' => 'Address (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Street / building'],
                                ['name' => 'city', 'label' => 'City (optional)', 'required' => false, 'span' => '2', 'placeholder' => 'Nairobi'],
                                ['name' => 'commission_percent', 'label' => 'Commission % (optional)', 'required' => false, 'type' => 'number', 'step' => '0.01', 'min' => '0', 'max' => '100', 'span' => '2', 'placeholder' => 'Uses default if blank'],
                            ],
                        ]"
                    />
                    @error('property_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @if (collect($linkableProperties ?? [])->isEmpty())
                        <p class="text-xs text-amber-700 mt-1">All properties are already linked to landlord users.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord user</label>
                    <x-property.quick-create-select
                        name="user_id"
                        :required="true"
                        select-id="landlord-user-select"
                        :options="collect($landlordUsers)->map(fn($u) => ['value' => $u->id, 'label' => $u->name.' ('.$u->email.')', 'selected' => (string) old('user_id') === (string) $u->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create landlord',
                            'endpoint' => route('property.landlords.onboard_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Full name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Jane Landlord'],
                                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'span' => '2', 'placeholder' => 'name@example.com'],
                                ['name' => 'password', 'label' => 'Temporary password', 'required' => true, 'span' => '2', 'placeholder' => 'At least 8 characters'],
                            ],
                        ]"
                    />
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
                <div class="overflow-x-auto overflow-y-auto max-h-80 pr-1">
                    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-600">
                                <th class="sticky top-0 z-10 py-2 pr-4 bg-white dark:bg-gray-800/95">Property</th>
                                <th class="sticky top-0 z-10 py-2 pr-4 bg-white dark:bg-gray-800/95">Landlord</th>
                                <th class="sticky top-0 z-10 py-2 pr-4 bg-white dark:bg-gray-800/95">Email</th>
                                <th class="sticky top-0 z-10 py-2 pr-4 bg-white dark:bg-gray-800/95">Ownership %</th>
                                <th class="sticky top-0 z-10 py-2 bg-white dark:bg-gray-800/95"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($landlordLinks as $link)
                                <tr class="border-b border-slate-100 dark:border-slate-700/80">
                                    <td class="py-2 pr-4 text-slate-900 dark:text-white">{{ $link->property_name }}</td>
                                    <td class="py-2 pr-4">{{ $link->user_name }}</td>
                                    <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">{{ $link->user_email }}</td>
                                    <td class="py-2 pr-4">
                                        <form
                                            method="post"
                                            action="{{ route('property.properties.landlords.ownership') }}"
                                            data-turbo-frame="property-main"
                                            data-turbo="false"
                                            class="flex flex-wrap items-center gap-2"
                                        >
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
                                        <form
                                            method="post"
                                            action="{{ route('property.properties.landlords.detach') }}"
                                            data-turbo-frame="property-main"
                                            data-turbo="false"
                                            data-swal-title="Detach landlord?"
                                            data-swal-confirm="Unlink this landlord from this property?"
                                            data-swal-confirm-text="Yes, detach"
                                        >
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
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.properties.list') }}" class="w-full grid gap-2 sm:grid-cols-2 lg:grid-cols-7 items-end">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name, code, city..." class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 lg:col-span-2" />
            <select name="city" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All cities</option>
                @foreach (($cities ?? []) as $city)
                    <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                @endforeach
            </select>
            <select name="landlord" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Landlord: All</option>
                <option value="linked" @selected(($filters['landlord'] ?? '') === 'linked')>Linked only</option>
                <option value="unlinked" @selected(($filters['landlord'] ?? '') === 'unlinked')>Unlinked only</option>
            </select>
            <select name="sort" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Sort: Name</option>
                <option value="city" @selected(($filters['sort'] ?? '') === 'city')>Sort: City</option>
                <option value="units_count" @selected(($filters['sort'] ?? '') === 'units_count')>Sort: Units</option>
                <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Newest</option>
            </select>
            <div class="flex items-center gap-2">
                <label class="text-xs text-slate-500 dark:text-slate-400">Per page</label>
                <select name="per_page" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-2 py-2">
                    @foreach ([10, 30, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="dir" value="{{ ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc' }}" />
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.properties.list', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
            <div class="flex items-center">
                @include('property.agent.partials.export_dropdown', [
                    'csvUrl' => route('property.properties.list.export', array_merge(request()->query(), ['format' => 'csv']), false),
                    'xlsUrl' => route('property.properties.list.export', array_merge(request()->query(), ['format' => 'xls']), false),
                    'pdfUrl' => route('property.properties.list.export', array_merge(request()->query(), ['format' => 'pdf']), false),
                ])
            </div>
        </form>
    </x-slot>
    <x-slot name="footer">
        @isset($properties)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Showing {{ $properties->firstItem() ?? 0 }}–{{ $properties->lastItem() ?? 0 }} of {{ $properties->total() }} propert{{ $properties->total() === 1 ? 'y' : 'ies' }}
                </p>
                <div>
                    {{ $properties->links() }}
                </div>
            </div>
        @endisset
    </x-slot>
</x-property.workspace>
