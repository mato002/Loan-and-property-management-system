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
    <form
        method="post"
        action="{{ route('property.leases.update', $lease) }}"
        x-data="{
            showOpeningArrearsSection: @js($errors->hasAny(['opening_arrears_items','opening_arrears_items.*.type','opening_arrears_items.*.label','opening_arrears_items.*.period','opening_arrears_items.*.amount','opening_arrears_amount','opening_arrears_as_of','opening_arrears_notes']) || count((array) old('opening_arrears_items', (array) ($lease->pmTenant->opening_arrears_items ?? []))) > 0 || (float) old('opening_arrears_amount', $lease->pmTenant->opening_arrears_amount ?? 0) > 0 || trim((string) old('opening_arrears_notes', $lease->pmTenant->opening_arrears_notes ?? '')) !== ''),
            arrearsItems: @js(array_values((array) old('opening_arrears_items', (array) ($lease->pmTenant->opening_arrears_items ?? [])))),
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
        }"
        class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl"
    >
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
                            ['name' => 'national_id', 'label' => 'National ID / reference', 'required' => false, 'span' => '2', 'placeholder' => 'e.g. 12345678'],
                            ['name' => 'risk_level', 'label' => 'Risk level', 'type' => 'select', 'required' => false, 'options' => [
                                ['value' => 'normal', 'label' => 'Normal'],
                                ['value' => 'medium', 'label' => 'Medium'],
                                ['value' => 'high', 'label' => 'High'],
                            ]],
                            ['name' => 'create_portal_login', 'label' => 'Create portal login', 'type' => 'select', 'required' => false, 'options' => [
                                ['value' => '0', 'label' => 'No'],
                                ['value' => '1', 'label' => 'Yes'],
                            ]],
                            ['name' => 'notes', 'label' => 'Notes', 'required' => false, 'span' => '2', 'placeholder' => 'Optional notes'],
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
                <input id="lease-monthly-rent" type="number" name="monthly_rent" value="{{ old('monthly_rent', $lease->monthly_rent) }}" step="0.01" min="0" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <p class="mt-1 text-xs text-slate-500">Auto-fills from selected unit rent.</p>
                @error('monthly_rent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent deposit</label>
                <input id="lease-rent-deposit" type="number" name="deposit_amount" value="{{ old('deposit_amount', $lease->deposit_amount) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <p id="rent-deposit-meta" class="mt-1 text-xs text-slate-500">—</p>
                @error('deposit_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <button type="button" id="open-optional-fields-edit-modal" class="inline-flex items-center gap-2 rounded-lg border border-emerald-700 bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:border-emerald-300 disabled:bg-emerald-400/90 disabled:text-white/95 disabled:shadow-none dark:border-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500 dark:disabled:bg-emerald-800/80" disabled>
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                    Utilities, deposits &amp; terms
                </button>
            </div>
        </div>
        <div id="optional-fields-edit-modal" class="fixed inset-y-0 right-0 left-0 md:left-[230px] z-[70] hidden items-center justify-center bg-slate-900/40 p-2 sm:p-4">
            <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xl max-h-[calc(100vh-1.5rem)] sm:max-h-[calc(100vh-3rem)] overflow-y-auto">
                <div class="mb-2 flex items-center justify-between gap-2 border-b border-emerald-100 pb-2">
                    <h4 class="text-sm font-semibold text-emerald-900">Utilities, deposits &amp; terms</h4>
                    <button type="button" id="close-optional-fields-edit-modal" class="rounded-md border border-slate-300 px-2 py-1 text-xs">Close</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Utility defaults</label>
                <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="min-w-[640px] w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-2">Utility type</th>
                                <th class="px-3 py-2 whitespace-nowrap">Rate / unit</th>
                                <th class="px-3 py-2 whitespace-nowrap">Fixed (flat)</th>
                            </tr>
                        </thead>
                        <tbody id="utility-defaults-tbody"></tbody>
                    </table>
                    <p id="utility-defaults-empty" class="px-3 py-4 text-xs text-slate-500 hidden">Select a unit to load configured utility types.</p>
                </div>
                @error('utility_expenses')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('utility_expense_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('utility_expense_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
        </div>
        </div>
        @php($selectedPropertyId = (string) old('property_id', optional($lease->units->first())->property_id))
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (with vacant/linked units)</label>
            <select id="lease-property-select" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">All properties</option>
                @foreach (($vacantProperties ?? []) as $property)
                    <option value="{{ $property->id }}" @selected($selectedPropertyId !== '' && (string) $property->id === (string) $selectedPropertyId)>{{ $property->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit</label>
            @php $selectedUnitId = (string) collect(old('property_unit_ids', $lease->units->pluck('id')->all()))->first(); @endphp
            <select id="lease-unit-select" name="property_unit_ids[]" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select unit...</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" data-property-id="{{ $u->property_id }}" data-rent="{{ (float) ($u->rent_amount ?? 0) }}" @selected($selectedUnitId === (string) $u->id)>{{ $u->property->name }} / {{ $u->label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">A tenant can only be assigned one unit.</p>
            @error('property_unit_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('property_unit_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        @php($additionalDeposits = old('additional_deposits', $lease->additional_deposits ?? []))
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Configured deposit lines</label>
            <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="min-w-[760px] p-2">
                    <div class="grid gap-2 grid-cols-[2fr_1fr_2fr_auto] px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <div>Deposit type</div>
                        <div>Amount</div>
                        <div>Rule details</div>
                        <div>Action</div>
                    </div>
                    <div id="additional-deposits-rows" class="mt-2 space-y-2">
                        @foreach ($additionalDeposits as $idx => $row)
                            <div class="grid gap-2 grid-cols-[2fr_1fr_2fr_auto] additional-deposit-row">
                                <select name="additional_deposits[{{ $idx }}][label]" class="additional-deposit-label rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                    <option value="{{ $row['label'] ?? '' }}" selected>{{ $row['label'] ?? 'Select deposit type' }}</option>
                                </select>
                                <input type="number" name="additional_deposits[{{ $idx }}][amount]" value="{{ $row['amount'] ?? '' }}" step="0.01" min="0" placeholder="Amount" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                                <div class="deposit-line-meta rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">—</div>
                                <button type="button" class="remove-deposit-row rounded-lg border border-red-200 px-2.5 py-2 text-xs font-medium text-red-700 hover:bg-red-50">Remove</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @error('additional_deposits')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('additional_deposits.*.label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('additional_deposits.*.amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Terms summary</label>
            <textarea name="terms_summary" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('terms_summary', $lease->terms_summary ?: ($leaseTemplate ?? '')) }}</textarea>
            @error('terms_summary')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        @php($openingArrearsRows = old('opening_arrears', ($lease->opening_arrears ?? [])))
        <div class="rounded-xl border border-amber-200 bg-amber-50/40 dark:border-amber-700/40 dark:bg-amber-900/10 p-3 space-y-2">
            <button type="button" id="toggle-opening-arrears-edit" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-700 px-3 py-2 text-xs font-medium text-amber-800 dark:text-amber-300 hover:bg-amber-100/70 dark:hover:bg-amber-800/20">
                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                <span>Add previous carry-forward details for this tenant</span>
            </button>
        </div>
        <div id="opening-arrears-edit-modal" class="fixed inset-y-0 right-0 left-0 md:left-56 z-[80] hidden items-center justify-center bg-slate-900/40 p-2 sm:p-4">
            <div class="w-full max-w-3xl rounded-2xl border border-amber-200 bg-white p-3 sm:p-4 shadow-xl max-h-[calc(100vh-1.5rem)] sm:max-h-[calc(100vh-3rem)] overflow-y-auto">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-amber-900">Carry-forward details</h4>
                    <button type="button" id="close-opening-arrears-edit-modal" class="rounded-md border border-slate-300 px-2 py-1 text-xs">Close</button>
                </div>
                <div id="opening-arrears-edit-wrap" class="space-y-3">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-300">Opening arrears at lease setup (optional)</p>
                <p class="text-xs text-amber-700 dark:text-amber-300">Use this when tenant starts lease with brought-forward balance.</p>
                <button type="button" id="open-arrears-line-modal-edit" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-100/70 px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-800/20 dark:text-amber-300">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Add charge line
                </button>
                <div class="overflow-x-auto rounded-xl border border-amber-200/80 bg-white/70">
                    <table class="w-full table-fixed text-sm">
                        <colgroup>
                            <col class="w-[18%]" />
                            <col class="w-[28%]" />
                            <col class="w-[18%]" />
                            <col class="w-[18%]" />
                            <col class="w-[18%]" />
                        </colgroup>
                        <thead class="bg-amber-50 text-left text-xs font-semibold text-amber-900">
                            <tr>
                                <th class="px-3 py-2">Charge type</th>
                                <th class="px-3 py-2">Specific charge</th>
                                <th class="px-3 py-2">Period</th>
                                <th class="px-3 py-2">Amount (KES)</th>
                                <th class="px-3 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody id="opening-arrears-edit-rows">
                            @foreach ($openingArrearsRows as $idx => $row)
                                <tr class="opening-arrears-row border-t border-amber-100">
                                    <td class="px-3 py-2">
                                        <select name="opening_arrears[{{ $idx }}][charge_type]" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                            <option value="water" @selected(($row['charge_type'] ?? '') === 'water')>Water</option>
                                            <option value="electricity" @selected(($row['charge_type'] ?? '') === 'electricity')>Electricity</option>
                                            <option value="service" @selected(($row['charge_type'] ?? '') === 'service')>Service</option>
                                            <option value="garbage" @selected(($row['charge_type'] ?? '') === 'garbage')>Garbage</option>
                                            <option value="other" @selected(($row['charge_type'] ?? '') === 'other')>Other</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" name="opening_arrears[{{ $idx }}][specific_charge]" value="{{ $row['specific_charge'] ?? '' }}" placeholder="e.g. Water meter bill" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="month" name="opening_arrears[{{ $idx }}][period]" value="{{ $row['period'] ?? '' }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" name="opening_arrears[{{ $idx }}][amount]" value="{{ $row['amount'] ?? '' }}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <button type="button" class="remove-opening-arrears-row rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Manual total override (optional)</label>
                        <input type="number" step="0.01" min="0" name="opening_arrears_manual_total" value="{{ old('opening_arrears_manual_total', $lease->opening_arrears_manual_total) }}" placeholder="Auto-sums charge lines if left blank" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">As of date</label>
                        <input type="date" name="opening_arrears_as_of_date" value="{{ old('opening_arrears_as_of_date', optional($lease->opening_arrears_as_of_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Arrears note (optional)</label>
                    <input type="text" name="opening_arrears_note" value="{{ old('opening_arrears_note', $lease->opening_arrears_note) }}" placeholder="Source / reason for brought-forward debt" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
        </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save changes</button>
            <a href="{{ route('property.tenants.leases') }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Back</a>
        </div>
    </form>
    <div id="arrears-line-modal-edit" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800 p-5 shadow-xl space-y-3">
            <div class="flex items-start justify-between gap-3">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Add charge line</h4>
                <button type="button" id="close-arrears-line-modal-edit" class="rounded-md border border-slate-300 dark:border-slate-600 px-2 py-1 text-xs">Close</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
                    <select id="arrears-line-edit-charge-type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="water">Water</option>
                        <option value="electricity">Electricity</option>
                        <option value="service">Service</option>
                        <option value="garbage">Garbage</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Period (YYYY-MM)</label>
                    <input id="arrears-line-edit-period" type="month" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Specific charge</label>
                    <input id="arrears-line-edit-specific-charge" type="text" placeholder="e.g. Water meter bill" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                    <input id="arrears-line-edit-amount" type="number" step="0.01" min="0" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" id="cancel-arrears-line-modal-edit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium">Cancel</button>
                <button type="button" id="save-arrears-line-modal-edit" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700">Add line</button>
            </div>
        </div>
    </div>
    <div id="charge-type-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800 p-5 shadow-xl space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Add charge type</h4>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Create a new charge type for this lease form.</p>
                </div>
                <button type="button" id="close-charge-type-modal" class="rounded-md border border-slate-300 dark:border-slate-600 px-2 py-1 text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60">Close</button>
            </div>
            <div>
                <label for="new-charge-type-input" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
                <input id="new-charge-type-input" type="text" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="e.g. Security" />
                <p id="charge-type-modal-error" class="mt-1 hidden text-xs text-red-600">Please enter a charge type name.</p>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" id="cancel-charge-type-modal" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60">Cancel</button>
                <button type="button" id="save-charge-type-modal" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700">Add type</button>
            </div>
        </div>
    </div>
    @php
        $_leaseUtilityRowsForJs = old('utility_expenses');
        if (! is_array($_leaseUtilityRowsForJs) || $_leaseUtilityRowsForJs === []) {
            $_leaseUtilityRowsForJs = $lease->utility_expenses ?? [];
        }
    @endphp
    <script>
        (function () {
            const leaseUtilityExpenseFormOld = @json(collect($_leaseUtilityRowsForJs)->values()->all());
            const utilityTemplatesByProperty = @json($utilityChargeTemplatesByProperty ?? []);
            const depositDefinitionsByProperty = @json($depositDefinitionsByProperty ?? []);
            const canCustomDepositOverride = @js((bool) ((auth()->user()?->is_super_admin ?? false) || (auth()->user()?->hasPmPermission('settings.manage') ?? false) || (\App\Models\PropertyPortalSetting::getValue('lease_deposit_allow_custom_types', '0') === '1')));
            const propertySelect = document.getElementById('lease-property-select');
            const unitSelect = document.getElementById('lease-unit-select');
            const monthlyRentInput = document.getElementById('lease-monthly-rent');
            const additionalDepositsWrap = document.getElementById('additional-deposits-rows');
            const rentDepositInput = document.getElementById('lease-rent-deposit');
            const rentDepositMeta = document.getElementById('rent-deposit-meta');
            const optionalFieldsEditModal = document.getElementById('optional-fields-edit-modal');
            const openOptionalFieldsEditModalButton = document.getElementById('open-optional-fields-edit-modal');
            const closeOptionalFieldsEditModalButton = document.getElementById('close-optional-fields-edit-modal');
            const utilityDefaultsTbody = document.getElementById('utility-defaults-tbody');
            const utilityDefaultsEmptyHint = document.getElementById('utility-defaults-empty');
            const openingArrearsEditModal = document.getElementById('opening-arrears-edit-modal');
            const openingArrearsEditWrap = document.getElementById('opening-arrears-edit-wrap');
            const closeOpeningArrearsEditModalButton = document.getElementById('close-opening-arrears-edit-modal');
            const openingArrearsEditRows = document.getElementById('opening-arrears-edit-rows');
            const openArrearsLineModalEditButton = document.getElementById('open-arrears-line-modal-edit');
            const arrearsLineModalEdit = document.getElementById('arrears-line-modal-edit');
            const closeArrearsLineModalEditButton = document.getElementById('close-arrears-line-modal-edit');
            const cancelArrearsLineModalEditButton = document.getElementById('cancel-arrears-line-modal-edit');
            const saveArrearsLineModalEditButton = document.getElementById('save-arrears-line-modal-edit');
            const arrearsLineEditChargeType = document.getElementById('arrears-line-edit-charge-type');
            const arrearsLineEditSpecificCharge = document.getElementById('arrears-line-edit-specific-charge');
            const arrearsLineEditPeriod = document.getElementById('arrears-line-edit-period');
            const arrearsLineEditAmount = document.getElementById('arrears-line-edit-amount');
            const toggleOpeningArrearsEditButton = document.getElementById('toggle-opening-arrears-edit');
            const chargeTypeModal = document.getElementById('charge-type-modal');
            const openChargeTypeModalButton = document.getElementById('open-charge-type-modal');
            const closeChargeTypeModalButton = document.getElementById('close-charge-type-modal');
            const cancelChargeTypeModalButton = document.getElementById('cancel-charge-type-modal');
            const saveChargeTypeModalButton = document.getElementById('save-charge-type-modal');
            const newChargeTypeInput = document.getElementById('new-charge-type-input');
            const chargeTypeModalError = document.getElementById('charge-type-modal-error');
            if (!propertySelect || !unitSelect || !monthlyRentInput) return;

            const normalizeTypeValue = (value) => (value || '').toString().trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            const toMoney = (value) => {
                const num = Number(value);
                return Number.isFinite(num) ? num.toFixed(2) : '';
            };
            const getSelectedUnitOption = () => {
                if (unitSelect.selectedIndex < 0) return null;
                const selected = unitSelect.options[unitSelect.selectedIndex];
                return selected && selected.value !== '' ? selected : null;
            };
            const getCurrentPropertyId = () => {
                const selectedUnit = getSelectedUnitOption();
                if (selectedUnit) {
                    return (selectedUnit.getAttribute('data-property-id') || '').toString();
                }
                return (propertySelect.value || '').toString();
            };
            const getPropertyTemplates = (propertyId) => {
                if (!propertyId) return [];
                const rows = utilityTemplatesByProperty[String(propertyId)];
                return Array.isArray(rows) ? rows : [];
            };
            const getEffectivePropertyTemplates = (propertyId) => {
                const rows = getPropertyTemplates(propertyId);
                const selectedUnit = getSelectedUnitOption();
                const unitId = selectedUnit ? Number(selectedUnit.value || 0) : 0;
                if (rows.length === 0) return [];
                const map = new Map();
                rows
                    .filter((r) => !r?.property_unit_id)
                    .forEach((r) => map.set(normalizeTypeValue(r?.charge_type || r?.label || ''), r));
                rows
                    .filter((r) => Number(r?.property_unit_id || 0) === unitId)
                    .forEach((r) => map.set(normalizeTypeValue(r?.charge_type || r?.label || ''), r));
                return Array.from(map.values());
            };
            const getEffectiveDepositDefinitions = () => {
                const propertyId = getCurrentPropertyId();
                if (!propertyId) return [];
                const selectedUnit = getSelectedUnitOption();
                const unitId = selectedUnit ? Number(selectedUnit.value || 0) : 0;
                const rows = Array.isArray(depositDefinitionsByProperty[String(propertyId)]) ? depositDefinitionsByProperty[String(propertyId)] : [];
                const map = new Map();
                rows
                    .filter((r) => !r.property_unit_id || Number(r.property_unit_id) === unitId)
                    .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))
                    .forEach((r) => map.set(String(r.deposit_key || ''), r));
                return Array.from(map.values());
            };
            const computeDefinitionAmount = (definition) => {
                const monthlyRent = Number(monthlyRentInput?.value || 0);
                const val = Number(definition?.amount_value || 0);
                if (!Number.isFinite(val) || val <= 0) return 0;
                return String(definition?.amount_mode || '') === 'percent_rent' ? (monthlyRent * val) / 100 : val;
            };
            const additionalLabelOptionsHtml = (definitions, selected = '') => {
                const optionalDefs = definitions.filter((d) => String(d.deposit_key || '') !== 'rent_deposit');
                const options = optionalDefs.map((d) => {
                    const label = String(d.label || d.deposit_key || 'Deposit');
                    const isSel = selected !== '' && selected === label ? 'selected' : '';
                    return `<option value="${label.replace(/"/g, '&quot;')}" ${isSel}>${label}</option>`;
                });
                if (selected && !optionalDefs.some((d) => String(d.label || '') === selected)) {
                    options.unshift(`<option value="${selected.replace(/"/g, '&quot;')}" selected>${selected}</option>`);
                }
                if (options.length === 0) options.push('<option value="">No configured deposit types</option>');
                return options.join('');
            };
            const reindexDepositRows = () => {
                additionalDepositsWrap?.querySelectorAll('.additional-deposit-row').forEach((row, idx) => {
                    const labelSelect = row.querySelector('.additional-deposit-label');
                    const amountInput = row.querySelector('input[type="number"]');
                    if (labelSelect) labelSelect.setAttribute('name', `additional_deposits[${idx}][label]`);
                    if (amountInput) amountInput.setAttribute('name', `additional_deposits[${idx}][amount]`);
                });
            };
            const renderDepositMeta = (def) => {
                if (!def) return '—';
                const mode = String(def.amount_mode || 'fixed');
                const value = Number(def.amount_value || 0);
                const formula = mode === 'percent_rent' ? `${value}% of rent` : `Fixed ${toMoney(value)}`;
                return `${def.is_required ? 'Required' : 'Optional'} | ${def.is_refundable ? 'Refundable' : 'Non-refundable'} | ${formula}`;
            };
            const styleDepositRow = (row, def) => {
                row.classList.remove('bg-amber-50/40', 'ring-1', 'ring-amber-200');
                if (def && def.is_required) {
                    row.classList.add('bg-amber-50/40', 'ring-1', 'ring-amber-200');
                }
            };
            const getTemplateTypeRows = (propertyId) => {
                const rows = getEffectivePropertyTemplates(propertyId);
                return rows
                    .map((row) => {
                        const type = normalizeTypeValue(row?.charge_type || row?.label || '');
                        if (!type) return null;
                        const label = (row?.label || '').toString().trim() || type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                        const rate = Number(row?.rate_per_unit ?? 0);
                        const fixed = Number(row?.fixed_charge ?? 0);
                        let mode = 'fixed';
                        let amount = 0;
                        if (rate > 0 && fixed <= 0) {
                            mode = 'rate_per_unit';
                            amount = rate;
                        } else if (fixed > 0) {
                            mode = 'fixed';
                            amount = fixed;
                        } else if (rate > 0) {
                            mode = 'rate_per_unit';
                            amount = rate;
                        }
                        let inputMode = 'both';
                        if (rate > 0 && fixed <= 0) inputMode = 'rate_only';
                        else if (fixed > 0 && rate <= 0) inputMode = 'fixed_only';
                        else if (rate > 0 && fixed > 0) inputMode = 'mixed';
                        return { value: type, label, amount, mode, tplRate: rate, tplFixed: fixed, inputMode };
                    })
                    .filter((row, idx, arr) => row && arr.findIndex((x) => x.value === row.value) === idx);
            };
            const buildTypeOptionsHtml = (rows, includeNone = false) => {
                const chunks = [];
                if (includeNone) chunks.push('<option value="">None</option>');
                rows.forEach((row) => {
                    chunks.push(`<option value="${row.value}" data-default-amount="${toMoney(row.amount)}" data-default-mode="${row.mode || 'fixed'}">${row.label}</option>`);
                });
                return chunks.join('');
            };
            const syncOptionalSectionState = () => {
                const selectedUnit = getSelectedUnitOption();
                const enabled = !!selectedUnit;
                if (openOptionalFieldsEditModalButton) {
                    openOptionalFieldsEditModalButton.disabled = !enabled;
                }
            };
            const getActiveUtilityTemplateRows = () => {
                const propertyId = getCurrentPropertyId();
                const templateRows = getTemplateTypeRows(propertyId);
                const fallbackRows = [
                    { value: 'water', label: 'Water', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'electricity', label: 'Electricity', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'service', label: 'Service', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'garbage', label: 'Garbage', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                    { value: 'other', label: 'Other', amount: 0, mode: 'fixed', tplRate: 0, tplFixed: 0, inputMode: 'both' },
                ];
                return templateRows.length > 0 ? templateRows : fallbackRows;
            };
            const pickSavedAmountForType = (typeValue, tpl) => {
                const savedRow = (leaseUtilityExpenseFormOld || []).find((r) => normalizeTypeValue(String(r?.type || '')) === typeValue);
                if (savedRow) {
                    const r = savedRow.rate_per_unit;
                    const f = savedRow.fixed_charge ?? savedRow.fixed;
                    if (r !== undefined && r !== null && r !== '' && Number(r) >= 0) return { rate: Number(r), fixed: f !== undefined && f !== null && f !== '' ? Number(f) : null };
                    if (f !== undefined && f !== null && f !== '' && Number(f) >= 0) return { rate: null, fixed: Number(f) };
                    const a = savedRow.amount;
                    if (a !== undefined && a !== null && a !== '' && Number(a) > 0) {
                        const amt = Number(a);
                        if (tpl.inputMode === 'rate_only') return { rate: amt, fixed: null };
                        if (tpl.inputMode === 'fixed_only') return { rate: null, fixed: amt };
                        if (tpl.inputMode === 'mixed') {
                            return Number(tpl.tplRate) >= Number(tpl.tplFixed) ? { rate: amt, fixed: null } : { rate: null, fixed: amt };
                        }
                        return { rate: null, fixed: amt };
                    }
                }
                return null;
            };
            const renderUtilityDefaultsTable = () => {
                if (!utilityDefaultsTbody) return;
                utilityDefaultsTbody.innerHTML = '';
                const selectedUnit = getSelectedUnitOption();
                if (!selectedUnit) {
                    if (utilityDefaultsEmptyHint) {
                        utilityDefaultsEmptyHint.classList.remove('hidden');
                        utilityDefaultsEmptyHint.textContent = 'Select a unit first.';
                    }
                    return;
                }
                if (utilityDefaultsEmptyHint) utilityDefaultsEmptyHint.classList.add('hidden');
                const rows = getActiveUtilityTemplateRows();
                rows.forEach((tpl, idx) => {
                    const saved = pickSavedAmountForType(tpl.value, tpl);
                    let rateVal = '';
                    let fixedVal = '';
                    if (saved && saved.rate !== null && saved.rate !== undefined && Number(saved.rate) >= 0) {
                        rateVal = Number(saved.rate) > 0 ? toMoney(saved.rate) : '';
                    } else if (tpl.tplRate > 0) {
                        rateVal = toMoney(tpl.tplRate);
                    }
                    if (saved && saved.fixed !== null && saved.fixed !== undefined && Number(saved.fixed) >= 0) {
                        fixedVal = Number(saved.fixed) > 0 ? toMoney(saved.fixed) : '';
                    } else if (tpl.tplFixed > 0) {
                        fixedVal = toMoney(tpl.tplFixed);
                    }
                    const rateDisabled = tpl.inputMode === 'fixed_only';
                    const fixedDisabled = tpl.inputMode === 'rate_only';
                    const ratePost = rateDisabled ? '' : rateVal;
                    const fixedPost = fixedDisabled ? '' : fixedVal;
                    const rateShow = rateDisabled ? '—' : (rateVal !== '' ? rateVal : '—');
                    const fixedShow = fixedDisabled ? '—' : (fixedVal !== '' ? fixedVal : '—');
                    const escAttr = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-slate-100 dark:border-slate-700';
                    tr.innerHTML = `
                        <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-200">${tpl.label.replace(/</g, '&lt;')}</td>
                        <td class="px-3 py-2">
                            <input type="hidden" name="utility_expenses[${idx}][type]" value="${escAttr(tpl.value)}" />
                            <input type="hidden" name="utility_expenses[${idx}][rate_per_unit]" value="${escAttr(ratePost)}" />
                            <span class="text-sm tabular-nums text-slate-700 dark:text-slate-200">${rateShow === '—' ? '—' : escAttr(rateShow)}</span>
                        </td>
                        <td class="px-3 py-2">
                            <input type="hidden" name="utility_expenses[${idx}][fixed_charge]" value="${escAttr(fixedPost)}" />
                            <span class="text-sm tabular-nums text-slate-700 dark:text-slate-200">${fixedShow === '—' ? '—' : escAttr(fixedShow)}</span>
                        </td>
                    `;
                    utilityDefaultsTbody.appendChild(tr);
                });
            };
            const refreshUtilityTypeSources = () => {
                renderUtilityDefaultsTable();
            };

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

            const syncMonthlyRentFromUnit = () => {
                const selected = unitSelect.options[unitSelect.selectedIndex];
                if (!selected) return;
                const rent = selected.getAttribute('data-rent');
                if (!rent || selected.value === '') return;
                monthlyRentInput.value = Number(rent).toFixed(2);
                const selectedPropertyId = (selected.getAttribute('data-property-id') || '').toString();
                if (selectedPropertyId !== '' && propertySelect.value !== selectedPropertyId) {
                    propertySelect.value = selectedPropertyId;
                }
                syncOptionalSectionState();
                refreshUtilityTypeSources();
                syncDepositRules();
            };

            const createDepositRow = (label = '', amount = '', locked = false) => {
                if (!additionalDepositsWrap) return;
                const index = additionalDepositsWrap.querySelectorAll('.additional-deposit-row').length;
                const defs = getEffectiveDepositDefinitions();
                const row = document.createElement('div');
                row.className = 'grid gap-2 grid-cols-[2fr_1fr_2fr_auto] additional-deposit-row';
                row.innerHTML = `
                    <select name="additional_deposits[${index}][label]" class="additional-deposit-label rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        ${additionalLabelOptionsHtml(defs, label)}
                    </select>
                    <input type="number" name="additional_deposits[${index}][amount]" value="${amount}" step="0.01" min="0" placeholder="Amount" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <div class="deposit-line-meta rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">—</div>
                    <button type="button" class="remove-deposit-row rounded-lg border border-red-200 px-2.5 py-2 text-xs font-medium text-red-700 hover:bg-red-50" ${locked ? 'disabled' : ''}>Remove</button>
                `;
                if (locked && !canCustomDepositOverride) {
                    const labelSelect = row.querySelector('.additional-deposit-label');
                    if (labelSelect instanceof HTMLSelectElement) labelSelect.disabled = true;
                }
                additionalDepositsWrap.appendChild(row);
            };
            const syncDepositRules = () => {
                const defs = getEffectiveDepositDefinitions();
                const byLabel = new Map(defs.map((d) => [String(d.label || ''), d]));
                const rentDef = defs.find((d) => String(d.deposit_key || '') === 'rent_deposit');
                if (rentDepositInput && rentDef) {
                    const required = !!rentDef.is_required;
                    if (!rentDepositInput.value || Number(rentDepositInput.value) === 0) {
                        rentDepositInput.value = toMoney(computeDefinitionAmount(rentDef));
                    }
                    rentDepositInput.readOnly = required && !canCustomDepositOverride;
                    if (rentDepositMeta) rentDepositMeta.textContent = renderDepositMeta(rentDef);
                }
                additionalDepositsWrap?.querySelectorAll('.additional-deposit-label').forEach((el) => {
                    if (!(el instanceof HTMLSelectElement)) return;
                    const current = el.value || '';
                    el.innerHTML = additionalLabelOptionsHtml(defs, current);
                    const row = el.closest('.additional-deposit-row');
                    const def = byLabel.get(el.value || '');
                    const metaEl = row?.querySelector('.deposit-line-meta');
                    if (metaEl) metaEl.textContent = renderDepositMeta(def);
                    if (row) styleDepositRow(row, def);
                });
                const requiredOptional = defs.filter((d) => d.is_required && String(d.deposit_key || '') !== 'rent_deposit');
                const existing = new Set(Array.from(additionalDepositsWrap?.querySelectorAll('.additional-deposit-label') ?? []).map((el) => el.value || ''));
                requiredOptional.forEach((d) => {
                    const label = String(d.label || '');
                    if (!label || existing.has(label)) return;
                    createDepositRow(label, toMoney(computeDefinitionAmount(d)), true);
                });
                const rows = Array.from(additionalDepositsWrap?.querySelectorAll('.additional-deposit-row') ?? []);
                rows
                    .sort((a, b) => {
                        const aLabel = a.querySelector('.additional-deposit-label')?.value || '';
                        const bLabel = b.querySelector('.additional-deposit-label')?.value || '';
                        const aReq = !!byLabel.get(aLabel)?.is_required;
                        const bReq = !!byLabel.get(bLabel)?.is_required;
                        return Number(bReq) - Number(aReq);
                    })
                    .forEach((row) => additionalDepositsWrap?.appendChild(row));
                reindexDepositRows();
            };
            openOptionalFieldsEditModalButton?.addEventListener('click', () => {
                if (openOptionalFieldsEditModalButton.disabled) return;
                optionalFieldsEditModal?.classList.remove('hidden');
                optionalFieldsEditModal?.classList.add('flex');
                renderUtilityDefaultsTable();
            });
            closeOptionalFieldsEditModalButton?.addEventListener('click', () => {
                optionalFieldsEditModal?.classList.add('hidden');
                optionalFieldsEditModal?.classList.remove('flex');
            });
            optionalFieldsEditModal?.addEventListener('click', (event) => {
                if (event.target !== optionalFieldsEditModal) return;
                optionalFieldsEditModal.classList.add('hidden');
                optionalFieldsEditModal.classList.remove('flex');
            });
            additionalDepositsWrap?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-deposit-row')) return;
                const row = target.closest('.additional-deposit-row');
                if (row) row.remove();
                reindexDepositRows();
            });
            toggleOpeningArrearsEditButton?.addEventListener('click', () => {
                if (!openingArrearsEditModal) return;
                openingArrearsEditModal.classList.remove('hidden');
                openingArrearsEditModal.classList.add('flex');
            });
            closeOpeningArrearsEditModalButton?.addEventListener('click', () => {
                openingArrearsEditModal?.classList.add('hidden');
                openingArrearsEditModal?.classList.remove('flex');
            });
            openingArrearsEditModal?.addEventListener('click', (event) => {
                if (event.target !== openingArrearsEditModal) return;
                openingArrearsEditModal.classList.add('hidden');
                openingArrearsEditModal.classList.remove('flex');
            });
            const openArrearsLineModalEdit = () => {
                if (!arrearsLineModalEdit) return;
                arrearsLineModalEdit.classList.remove('hidden');
                arrearsLineModalEdit.classList.add('flex');
            };
            const closeArrearsLineModalEdit = () => {
                if (!arrearsLineModalEdit) return;
                arrearsLineModalEdit.classList.add('hidden');
                arrearsLineModalEdit.classList.remove('flex');
            };
            const appendOpeningArrearsEditRow = (chargeType = 'water', specificCharge = '', period = '', amount = '') => {
                if (!openingArrearsEditRows) return;
                const index = openingArrearsEditRows.querySelectorAll('.opening-arrears-row').length;
                const row = document.createElement('tr');
                row.className = 'opening-arrears-row border-t border-amber-100';
                row.innerHTML = `
                    <td class="px-3 py-2">
                        <select name="opening_arrears[${index}][charge_type]" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            <option value="water">Water</option>
                            <option value="electricity">Electricity</option>
                            <option value="service">Service</option>
                            <option value="garbage">Garbage</option>
                            <option value="other">Other</option>
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <input type="text" name="opening_arrears[${index}][specific_charge]" value="${specificCharge}" placeholder="e.g. Water meter bill" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </td>
                    <td class="px-3 py-2">
                        <input type="month" name="opening_arrears[${index}][period]" value="${period}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="opening_arrears[${index}][amount]" value="${amount}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </td>
                    <td class="px-3 py-2">
                        <button type="button" class="remove-opening-arrears-row rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Remove</button>
                    </td>
                `;
                const select = row.querySelector('select');
                if (select) select.value = chargeType;
                openingArrearsEditRows.appendChild(row);
            };
            openArrearsLineModalEditButton?.addEventListener('click', openArrearsLineModalEdit);
            closeArrearsLineModalEditButton?.addEventListener('click', closeArrearsLineModalEdit);
            cancelArrearsLineModalEditButton?.addEventListener('click', closeArrearsLineModalEdit);
            saveArrearsLineModalEditButton?.addEventListener('click', () => {
                appendOpeningArrearsEditRow(
                    (arrearsLineEditChargeType && 'value' in arrearsLineEditChargeType) ? arrearsLineEditChargeType.value : 'water',
                    arrearsLineEditSpecificCharge?.value || '',
                    arrearsLineEditPeriod?.value || '',
                    arrearsLineEditAmount?.value || ''
                );
                closeArrearsLineModalEdit();
            });
            openingArrearsEditRows?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-opening-arrears-row')) return;
                const row = target.closest('.opening-arrears-row');
                if (row) row.remove();
            });

            // Utility defaults are display-only in lease edit.

            propertySelect.addEventListener('change', () => {
                filterUnits();
                refreshUtilityTypeSources();
                syncOptionalSectionState();
                syncDepositRules();
            });
            unitSelect.addEventListener('change', syncMonthlyRentFromUnit);
            filterUnits();
            syncMonthlyRentFromUnit();
            refreshUtilityTypeSources();
            syncOptionalSectionState();
            syncDepositRules();
        })();
    </script>
</x-property.workspace>

