<x-property.workspace
    title="Edit lease"
    subtitle="Update term dates, rent, linked units, and status for an existing lease."
    back-route="property.tenants.leases"
    :stats="[
        ['label' => 'Lease', 'value' => '#'.$lease->id, 'hint' => ucfirst($lease->status)],
        ['label' => 'Tenant', 'value' => $lease->pmTenant->name, 'hint' => 'Current'],
    ]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.leases.update', $lease) }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
        @csrf
        @method('PUT')
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Lease details</h3>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                <x-property.quick-create-select
                    name="pm_tenant_id"
                    :required="true"
                    :options="collect($tenants)->map(fn($t) => ['value' => $t->id, 'label' => $t->name, 'selected' => (string) old('pm_tenant_id', $lease->pm_tenant_id) === (string) $t->id])->all()"
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
                <input type="date" name="start_date" value="{{ old('start_date', $lease->start_date->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('start_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">End</label>
                <input type="date" name="end_date" value="{{ old('end_date', $lease->end_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('end_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500">Optional for open-ended leases.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly rent</label>
                <input type="number" name="monthly_rent" value="{{ old('monthly_rent', $lease->monthly_rent) }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('monthly_rent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Deposit</label>
                <input type="number" name="deposit_amount" value="{{ old('deposit_amount', $lease->deposit_amount) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('deposit_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Utility expense type (optional)</label>
                <select name="utility_expense_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">None</option>
                    <option value="water" @selected(old('utility_expense_type', $lease->utility_expense_type) === 'water')>Water</option>
                    <option value="electricity" @selected(old('utility_expense_type', $lease->utility_expense_type) === 'electricity')>Electricity</option>
                    <option value="other" @selected(old('utility_expense_type', $lease->utility_expense_type) === 'other')>Other</option>
                </select>
                @error('utility_expense_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Utility expense amount paid (optional)</label>
                <input type="number" name="utility_expense_amount" value="{{ old('utility_expense_amount', $lease->utility_expense_amount) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. 2500" />
                @error('utility_expense_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="draft" @selected(old('status', $lease->status) === 'draft')>Draft</option>
                    <option value="active" @selected(old('status', $lease->status) === 'active')>Active</option>
                    <option value="expired" @selected(old('status', $lease->status) === 'expired')>Expired</option>
                    <option value="terminated" @selected(old('status', $lease->status) === 'terminated')>Terminated</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (with vacant/linked units)</label>
            <select id="lease-property-select" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">All properties</option>
                @foreach (($vacantProperties ?? []) as $property)
                    <option value="{{ $property->id }}">{{ $property->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
            @php $selectedUnitId = (string) collect(old('property_unit_ids', $lease->units->pluck('id')->all()))->first(); @endphp
            <select id="lease-unit-select" name="property_unit_ids[]" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select unit...</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" data-property-id="{{ $u->property_id }}" @selected($selectedUnitId === (string) $u->id)>{{ $u->property->name }} / {{ $u->label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">A tenant can only be assigned one unit.</p>
            @error('property_unit_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('property_unit_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Terms summary</label>
            <textarea name="terms_summary" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('terms_summary', $lease->terms_summary ?: ($leaseTemplate ?? '')) }}</textarea>
            @error('terms_summary')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.tenants.leases') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
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

