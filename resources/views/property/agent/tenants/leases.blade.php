<x-property.workspace
    title="Lease agreements"
    subtitle="Terms, deposits, rent, and linked units."
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No leases"
    empty-hint="Create a lease and select vacant units; active leases mark units occupied."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Rent flow (Step 1 of 3): Allocate a unit</p>
            <p class="mt-1 text-sm text-slate-600">Create an <span class="font-semibold">Active</span> lease and select the vacant unit(s). The unit becomes <span class="font-semibold">Occupied</span> automatically.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="text-slate-500">Next:</span> Create rent bill
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="text-slate-500">Then:</span> Collect payment
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <form method="post" action="{{ route('property.leases.store') }}" class="property-attention-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <h3 class="property-attention-title dark:text-white">New Lease</h3>
            <p class="property-attention-hint dark:text-slate-300">Allocate one vacant unit to a tenant to activate tenancy and unlock monthly billing.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                    <x-property.quick-create-select
                        name="pm_tenant_id"
                        :required="true"
                        :options="collect($tenants)->map(fn($t) => ['value' => $t->id, 'label' => $t->name, 'selected' => (string) old('pm_tenant_id', request('pm_tenant_id')) === (string) $t->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create tenant',
                            'endpoint' => route('property.tenants.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Full name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. John Tenant'],
                                ['name' => 'phone', 'label' => 'Phone', 'required' => false, 'span' => '2', 'placeholder' => '+2547…'],
                                ['name' => 'email', 'label' => 'Email (optional)', 'type' => 'email', 'required' => false, 'span' => '2', 'placeholder' => 'name@example.com'],
                            ],
                        ]"
                    />
                    @error('pm_tenant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Start</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('start_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">End</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('end_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">Optional for open-ended leases.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly rent</label>
                    <input type="number" name="monthly_rent" value="{{ old('monthly_rent') }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('monthly_rent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Deposit</label>
                    <input type="number" name="deposit_amount" value="{{ old('deposit_amount', 0) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('deposit_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Utility expense type (optional)</label>
                    <select name="utility_expense_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">None</option>
                        <option value="water" @selected(old('utility_expense_type') === 'water')>Water</option>
                        <option value="electricity" @selected(old('utility_expense_type') === 'electricity')>Electricity</option>
                        <option value="other" @selected(old('utility_expense_type') === 'other')>Other</option>
                    </select>
                    @error('utility_expense_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Utility expense amount paid (optional)</label>
                    <input type="number" name="utility_expense_amount" value="{{ old('utility_expense_amount') }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. 2500" />
                    @error('utility_expense_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                        <option value="active" @selected(old('status') === 'active')>Active</option>
                        <option value="expired" @selected(old('status') === 'expired')>Expired</option>
                        <option value="terminated" @selected(old('status') === 'terminated')>Terminated</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (with vacant units)</label>
                <select id="lease-property-select" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">All properties</option>
                    @foreach (($vacantProperties ?? []) as $property)
                        <option value="{{ $property->id }}">{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit (vacant)</label>
                <select id="lease-unit-select" name="property_unit_ids[]" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select unit...</option>
                    @forelse ($vacantUnits as $u)
                        <option value="{{ $u->id }}" data-property-id="{{ $u->property_id }}" @selected(collect(old('property_unit_ids', []))->contains($u->id))>{{ $u->property->name }} / {{ $u->label }}</option>
                    @empty
                        <option value="" disabled>No vacant units</option>
                    @endforelse
                </select>
                <p class="mt-1 text-xs text-slate-500">A tenant can only be assigned one unit.</p>
                @error('property_unit_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('property_unit_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Terms summary</label>
                <textarea name="terms_summary" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('terms_summary', $leaseTemplate ?? '') }}</textarea>
                @error('terms_summary')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save lease</button>
        </form>
    </x-slot>

    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Status: All</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="terminated">Terminated</option>
        </select>
    </x-slot>
    <script>
        (function () {
            const propertySelect = document.getElementById('lease-property-select');
            const unitSelect = document.getElementById('lease-unit-select');
            if (!propertySelect || !unitSelect) return;

            const filterUnits = () => {
                const propertyId = (propertySelect.value || '').toString();
                let visibleCount = 0;
                Array.from(unitSelect.options).forEach((opt) => {
                    const optPropertyId = (opt.getAttribute('data-property-id') || '').toString();
                    const selected = opt.selected;
                    const shouldShow = propertyId === '' || optPropertyId === propertyId || selected;
                    opt.hidden = !shouldShow;
                    if (shouldShow) visibleCount++;
                });

                if (visibleCount === 0 && propertyId !== '') {
                    unitSelect.title = 'No vacant units under selected property.';
                } else {
                    unitSelect.title = '';
                }
            };

            propertySelect.addEventListener('change', filterUnits);
            filterUnits();
        })();
    </script>
</x-property.workspace>
