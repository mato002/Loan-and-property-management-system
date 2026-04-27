<x-property.workspace
    title="Edit tenant"
    subtitle="Update tenant profile details used in leases, billing, and tenant operations."
    back-route="property.tenants.directory"
    :stats="[
        ['label' => 'Tenant', 'value' => $tenant->name, 'hint' => $tenant->email ?: 'No email'],
        ['label' => 'Risk', 'value' => ucfirst($tenant->risk_level), 'hint' => 'Current'],
    ]"
    :columns="[]"
>
    @if ((int) ($tenant->leases_count ?? 0) === 0)
        <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold">Not allocated yet</p>
                    <p class="mt-0.5 text-sm text-amber-800/90">This tenant has no lease/unit allocation. Allocate a vacant unit by creating a lease.</p>
                </div>
                <a
                    href="{{ route('property.tenants.leases', ['pm_tenant_id' => $tenant->id], absolute: false) }}"
                    data-turbo-frame="property-main"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    <span>Allocate vacant unit</span>
                </a>
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('property.tenants.update', $tenant) }}" x-data="{
        showOpeningArrearsSection: @js($errors->hasAny(['opening_arrears_items','opening_arrears_items.*.type','opening_arrears_items.*.period','opening_arrears_items.*.amount','opening_arrears_amount','opening_arrears_as_of','opening_arrears_notes']) || count((array) old('opening_arrears_items', (array) ($tenant->opening_arrears_items ?? []))) > 0 || (float) old('opening_arrears_amount', $tenant->opening_arrears_amount) > 0 || trim((string) old('opening_arrears_notes', $tenant->opening_arrears_notes)) !== ''),
        arrearsItems: @js(array_values((array) old('opening_arrears_items', (array) ($tenant->opening_arrears_items ?? [])))),
        arrearsTypeLabels: @js($openingArrearsTypeOptions ?? []),
        addArrearsItem() {
            this.arrearsItems.push({ type: 'water', label: '', period: '', amount: '', reference: '' });
        },
        removeArrearsItem(index) {
            this.arrearsItems.splice(index, 1);
        },
        setDefaultLabel(item) {
            if ((item.label ?? '').trim() !== '') return;
            item.label = this.arrearsTypeLabels[item.type] ?? '';
        }
    }" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl">
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tenant details</h3>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                <input type="email" name="email" value="{{ old('email', $tenant->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">National ID / ref</label>
                <input type="text" name="national_id" value="{{ old('national_id', $tenant->national_id) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Risk</label>
                <select name="risk_level" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="normal" @selected(old('risk_level', $tenant->risk_level) === 'normal')>Normal</option>
                    <option value="medium" @selected(old('risk_level', $tenant->risk_level) === 'medium')>Medium</option>
                    <option value="high" @selected(old('risk_level', $tenant->risk_level) === 'high')>High</option>
                </select>
                @error('risk_level')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <button
            type="button"
            @click="showOpeningArrearsSection = !showOpeningArrearsSection"
            class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100"
        >
            <i class="fa-solid fa-receipt" aria-hidden="true"></i>
            <span x-text="showOpeningArrearsSection ? 'Hide previous debt / opening arrears' : 'Edit previous debt / opening arrears'"></span>
        </button>
        <div x-show="showOpeningArrearsSection" x-cloak class="rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-3">
            <p class="text-xs font-semibold text-amber-900">Previous debt / opening arrears</p>
            <p class="mt-1 text-xs text-amber-800/90">Track each charge with a specific type, billing period, and demanded balance.</p>
            <div class="mt-3 space-y-2">
                <template x-for="(item, idx) in arrearsItems" :key="idx">
                    <div class="rounded-lg border border-amber-200 bg-white/90 p-3">
                        <div class="grid gap-2 sm:grid-cols-5">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600">Charge type</label>
                                <select :name="`opening_arrears_items[${idx}][type]`" x-model="item.type" @change="setDefaultLabel(item)" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-2 py-2">
                                    @foreach (($openingArrearsTypeOptions ?? []) as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600">Specific charge</label>
                                <input type="text" :name="`opening_arrears_items[${idx}][label]`" x-model="item.label" maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-2 py-2" placeholder="e.g. Water meter bill" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600">Period (YYYY-MM)</label>
                                <input type="month" :name="`opening_arrears_items[${idx}][period]`" x-model="item.period" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-2 py-2" />
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600">Amount (KES)</label>
                                <input type="number" min="0.01" step="0.01" :name="`opening_arrears_items[${idx}][amount]`" x-model="item.amount" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-2 py-2" placeholder="0.00" />
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="removeArrearsItem(idx)" class="w-full rounded-lg border border-rose-200 bg-rose-50 px-2 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">Remove</button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-[11px] font-medium text-slate-600">Reference (optional)</label>
                            <input type="text" :name="`opening_arrears_items[${idx}][reference]`" x-model="item.reference" maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-2 py-2" placeholder="e.g. Water bill APT-B4" />
                        </div>
                    </div>
                </template>
                <button type="button" @click="addArrearsItem()" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-200">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Add charge line
                </button>
                @error('opening_arrears_items')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('opening_arrears_items.*.type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('opening_arrears_items.*.label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('opening_arrears_items.*.period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('opening_arrears_items.*.amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Manual total override (optional)</label>
                    <input type="number" name="opening_arrears_amount" value="{{ old('opening_arrears_amount', $tenant->opening_arrears_amount) }}" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('opening_arrears_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">As of date</label>
                    <input type="date" name="opening_arrears_as_of" value="{{ old('opening_arrears_as_of', optional($tenant->opening_arrears_as_of)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('opening_arrears_as_of')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="mt-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Arrears note (optional)</label>
                <input type="text" name="opening_arrears_notes" value="{{ old('opening_arrears_notes', $tenant->opening_arrears_notes) }}" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('opening_arrears_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes', $tenant->notes) }}</textarea>
            @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.tenants.directory') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
</x-property.workspace>

