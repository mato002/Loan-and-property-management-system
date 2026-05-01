<x-property.workspace
    :title="($activeTab ?? 'leases') === 'expiry' ? 'Lease expiry tracking' : 'Lease agreements'"
    :subtitle="($activeTab ?? 'leases') === 'expiry'
        ? 'Active leases ending within the next 90 days. Use the window filter to focus renewals.'
        : 'Terms, deposits, rent, and linked units.'"
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="($activeTab ?? 'leases') === 'expiry' ? ($expiryFilterTexts ?? []) : []"
    :empty-title="($activeTab ?? 'leases') === 'expiry' ? 'No upcoming expiries' : 'No leases'"
    :empty-hint="($activeTab ?? 'leases') === 'expiry'
        ? 'When leases have end dates in the next 90 days, they appear here.'
        : 'Create a lease and select vacant units; active leases mark units occupied.'"
>
    @php
        $leaseCfg = $leaseFields ?? [];
        $leaseRequired = fn (string $k, bool $d = false) => (bool) (($leaseCfg[$k]['required'] ?? $d) && ($leaseCfg[$k]['enabled'] ?? true));
    @endphp
    <x-slot name="above">
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('property.tenants.leases', absolute: false) }}"
                data-turbo-frame="property-main"
                class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium {{ ($activeTab ?? 'leases') === 'leases' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
            >
                All leases
            </a>
            <a
                href="{{ route('property.tenants.leases', ['tab' => 'expiry'], false) }}"
                data-turbo-frame="property-main"
                class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium {{ ($activeTab ?? 'leases') === 'expiry' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
            >
                Expiring soon
            </a>
        </div>

        @if (($activeTab ?? 'leases') === 'leases')
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

        @php
            $showLeaseFormByDefault = $errors->hasAny([
                'pm_tenant_id',
                'start_date',
                'end_date',
                'utility_expense_type',
                'utility_expense_rate',
                'status',
                'property_unit_ids',
                'property_unit_ids.*',
                'monthly_rent',
                'deposit_amount',
                'additional_deposits',
                'additional_deposits.*.label',
                'additional_deposits.*.amount',
                'terms_summary',
                'opening_arrears_items',
                'opening_arrears_items.*.type',
                'opening_arrears_items.*.label',
                'opening_arrears_items.*.period',
                'opening_arrears_items.*.amount',
                'opening_arrears_amount',
                'opening_arrears_as_of',
                'opening_arrears_notes',
            ]);
        @endphp
        <details class="space-y-3 group" @if($showLeaseFormByDefault) open @endif>
        <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
            <span class="group-open:hidden">Create lease</span>
            <span class="hidden group-open:inline">Hide lease form</span>
        </summary>
        <form
            method="post"
            action="{{ route('property.leases.store') }}"
            id="lease-form-wrapper"
            x-data="{
                showOpeningArrearsSection: @js($errors->hasAny(['opening_arrears_items','opening_arrears_items.*.type','opening_arrears_items.*.label','opening_arrears_items.*.period','opening_arrears_items.*.amount','opening_arrears_amount','opening_arrears_as_of','opening_arrears_notes']) || count((array) old('opening_arrears_items', [])) > 0 || (float) old('opening_arrears_amount', 0) > 0 || trim((string) old('opening_arrears_notes', '')) !== ''),
                arrearsItems: @js(array_values((array) old('opening_arrears_items', []))),
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
            class="property-attention-card mt-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl"
        >
            @csrf
            <h3 class="property-attention-title dark:text-white">New Lease</h3>
            <p class="property-attention-hint dark:text-slate-300">Allocate one vacant unit to a tenant to activate tenancy and unlock monthly billing.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                    <x-property.quick-create-select
                        name="pm_tenant_id"
                        :required="$leaseRequired('tenant_id', true)"
                        :options="collect($tenants)->map(fn($t) => ['value' => $t->id, 'label' => $t->name, 'selected' => (string) old('pm_tenant_id', request('pm_tenant_id')) === (string) $t->id])->all()"
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
                    <input type="date" name="start_date" value="{{ old('start_date') }}" @required($leaseRequired('start_date', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('start_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">End</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('end_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">Optional for open-ended leases.</p>
                </div>
            </div>
            <div id="optional-fields-create-modal" class="fixed inset-0 z-[120] hidden items-start justify-center bg-slate-900/40 px-2 pb-2 pt-24 sm:px-4 sm:pb-4 sm:pt-28">
                <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xl h-[76vh] overflow-y-scroll">
                    <div class="sticky top-0 z-10 mb-3 flex items-center justify-between gap-2 border-b border-emerald-100 bg-white px-1 py-2 sm:px-2">
                        <h4 class="text-sm font-semibold text-emerald-900">Utilities, deposits &amp; terms</h4>
                        <button type="button" id="close-optional-fields-create-modal" class="rounded-md border border-slate-300 px-2 py-1 text-xs">Close</button>
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
                        <p id="utility-defaults-empty" class="px-3 py-4 text-xs text-slate-500 hidden">Select a property and unit to load configured utility types.</p>
                    </div>
                    @error('utility_expenses')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('utility_expense_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('utility_expense_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" @required($leaseRequired('status', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                        <option value="active" @selected(old('status') === 'active')>Active</option>
                        <option value="expired" @selected(old('status') === 'expired')>Expired</option>
                        <option value="terminated" @selected(old('status') === 'terminated')>Terminated</option>
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                @php($additionalDeposits = old('additional_deposits', []))
                <div class="sm:col-span-2">
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
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Terms summary</label>
                    <textarea name="terms_summary" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('terms_summary', $leaseTemplate ?? '') }}</textarea>
                    @error('terms_summary')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            </div>
            </div>
            @php($selectedUnitId = (int) collect(old('property_unit_ids', []))->first())
            @php($selectedUnit = $vacantUnits->firstWhere('id', $selectedUnitId))
            @php($selectedPropertyId = (string) old('property_id', $selectedUnit?->property_id))
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (with vacant units)</label>
                <select id="lease-property-select" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">All properties</option>
                    @foreach (($vacantProperties ?? []) as $property)
                        <option value="{{ $property->id }}" @selected($selectedPropertyId !== '' && (string) $property->id === (string) $selectedPropertyId)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit (vacant)</label>
                <select id="lease-unit-select" name="property_unit_ids[]" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select unit...</option>
                    @forelse ($vacantUnits as $u)
                        <option value="{{ $u->id }}" data-property-id="{{ $u->property_id }}" data-rent="{{ (float) ($u->rent_amount ?? 0) }}" @selected(collect(old('property_unit_ids', []))->contains($u->id))>{{ $u->property->name }} / {{ $u->label }}</option>
                    @empty
                        <option value="" disabled>No vacant units</option>
                    @endforelse
                </select>
                <p class="mt-1 text-xs text-slate-500">A tenant can only be assigned one unit.</p>
                @error('property_unit_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('property_unit_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <button type="button" id="open-optional-fields-create-modal" class="inline-flex items-center gap-2 rounded-lg border border-emerald-700 bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:border-emerald-300 disabled:bg-emerald-400/90 disabled:text-white/95 disabled:shadow-none dark:border-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500 dark:disabled:bg-emerald-800/80" disabled>
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                    Utilities, deposits &amp; terms
                </button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Monthly rent</label>
                    <input id="lease-monthly-rent" type="number" name="monthly_rent" value="{{ old('monthly_rent') }}" step="0.01" min="0" @required($leaseRequired('rent_amount', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p class="mt-1 text-xs text-slate-500">Auto-fills from selected unit rent.</p>
                    @error('monthly_rent')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent deposit</label>
                    <input id="lease-rent-deposit" type="number" name="deposit_amount" value="{{ old('deposit_amount', 0) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <p id="rent-deposit-meta" class="mt-1 text-xs text-slate-500">—</p>
                    @error('deposit_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            @php($openingArrearsRows = old('opening_arrears', []))
            @php($openingDepositArrearsRows = old('opening_deposit_arrears', []))
            <div class="rounded-xl border border-amber-200 bg-amber-50/40 dark:border-amber-700/40 dark:bg-amber-900/10 p-3 space-y-2">
                <button type="button" id="toggle-opening-arrears-create" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-700 px-3 py-2 text-xs font-medium text-amber-800 dark:text-amber-300 hover:bg-amber-100/70 dark:hover:bg-amber-800/20">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                    <span>Add previous carry-forward details for this tenant</span>
                </button>
            </div>
            <div id="opening-arrears-create-modal" class="fixed inset-0 z-[121] hidden items-start justify-center bg-slate-900/40 px-2 pb-2 pt-24 sm:px-4 sm:pb-4 sm:pt-28">
                <div class="w-full max-w-3xl rounded-2xl border border-amber-200 bg-white p-3 sm:p-4 shadow-xl h-[76vh] overflow-y-scroll">
                    <div class="sticky top-0 z-10 mb-3 flex items-center justify-between gap-2 border-b border-amber-200 bg-white px-1 py-2 sm:px-2">
                        <h4 class="text-sm font-semibold text-amber-900">Carry-forward details</h4>
                        <button type="button" id="close-opening-arrears-create-modal" class="rounded-md border border-slate-300 px-2 py-1 text-xs">Close</button>
                    </div>
                    <div id="opening-arrears-create-wrap" class="space-y-3">
                    <button type="button" id="open-arrears-line-modal-create" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-100/70 px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-800/20 dark:text-amber-300">
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
                            <tbody id="opening-arrears-create-rows">
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
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent arrears (KES)</label>
                            <input type="number" step="0.01" min="0" name="opening_rent_arrears" value="{{ old('opening_rent_arrears') }}" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('opening_rent_arrears')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent arrears period</label>
                            <input type="month" name="opening_rent_arrears_period" value="{{ old('opening_rent_arrears_period') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('opening_rent_arrears_period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Rent arrears details</label>
                            <input type="text" name="opening_rent_arrears_details" value="{{ old('opening_rent_arrears_details') }}" placeholder="e.g. Jan-Mar unpaid rent balance" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            @error('opening_rent_arrears_details')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-white/80 p-3 space-y-2">
                        <div class="overflow-x-auto rounded-xl border border-amber-200/80 bg-white/70">
                            <table class="w-full text-sm">
                                <thead class="bg-amber-50 text-left text-xs font-semibold text-amber-900">
                                    <tr>
                                        <th class="px-3 py-2">Deposit type</th>
                                        <th class="px-3 py-2">Amount (KES)</th>
                                    </tr>
                                </thead>
                                <tbody id="opening-deposit-arrears-create-rows"></tbody>
                            </table>
                        </div>
                        <p id="opening-deposit-arrears-create-empty" class="hidden text-xs text-slate-500">No configured deposit rules for this property/unit.</p>
                        @error('opening_deposit_arrears')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        @error('opening_deposit_arrears.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Manual total override (optional)</label>
                            <input type="number" step="0.01" min="0" name="opening_arrears_manual_total" value="{{ old('opening_arrears_manual_total') }}" placeholder="Auto-sums charge lines if left blank" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">As of date</label>
                            <input type="date" name="opening_arrears_as_of_date" value="{{ old('opening_arrears_as_of_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Arrears note (optional)</label>
                        <input type="text" name="opening_arrears_note" value="{{ old('opening_arrears_note') }}" placeholder="Source / reason for brought-forward debt" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                </div>
            </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save lease</button>
        </form>
        </details>
        <div id="arrears-line-modal-create" class="fixed inset-0 z-[130] hidden items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800 p-5 shadow-xl space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Add charge line</h4>
                    <button type="button" id="close-arrears-line-modal-create" class="rounded-md border border-slate-300 dark:border-slate-600 px-2 py-1 text-xs">Close</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
                        <select id="arrears-line-create-charge-type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            <option value="water">Water</option>
                            <option value="electricity">Electricity</option>
                            <option value="service">Service</option>
                            <option value="garbage">Garbage</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Period (YYYY-MM)</label>
                        <input id="arrears-line-create-period" type="month" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Specific charge</label>
                        <input id="arrears-line-create-specific-charge" type="text" placeholder="e.g. Water meter bill" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
                        <input id="arrears-line-create-amount" type="number" step="0.01" min="0" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" id="cancel-arrears-line-modal-create" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium">Cancel</button>
                    <button type="button" id="save-arrears-line-modal-create" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700">Add line</button>
                </div>
            </div>
        </div>
        @endif
    </x-slot>

    @if (($activeTab ?? 'leases') === 'expiry')
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'tenants-renewal-email') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Email renewals</a>
    </x-slot>
    @endif

    <x-slot name="toolbar">
        @if (($activeTab ?? 'leases') === 'expiry')
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Window: All (90d)</option>
            <option value="within30">≤ 30 days</option>
            <option value="within60">≤ 60 days</option>
            <option value="within90">≤ 90 days</option>
        </select>
        @else
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Status: All</option>
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="terminated">Terminated</option>
        </select>
        @endif
    </x-slot>
        <script>
            (function () {
            const leaseUtilityExpenseFormOld = @json(collect(old('utility_expenses', []))->values()->all());
            const openingDepositArrearsOld = @json((array) ($openingDepositArrearsRows ?? []));
            const utilityTemplatesByProperty = @json($utilityChargeTemplatesByProperty ?? []);
            const depositDefinitionsByProperty = @json($depositDefinitionsByProperty ?? []);
            const canCustomDepositOverride = @js((bool) ((auth()->user()?->is_super_admin ?? false) || (auth()->user()?->hasPmPermission('settings.manage') ?? false) || (\App\Models\PropertyPortalSetting::getValue('lease_deposit_allow_custom_types', '0') === '1')));
            const leaseForm = document.getElementById('lease-form-wrapper');
            const leaseFormToggleButton = document.getElementById('toggle-lease-form-button');
            const leaseFormToggleLabel = document.getElementById('toggle-lease-form-label');
            const propertySelect = document.getElementById('lease-property-select');
            const unitSelect = document.getElementById('lease-unit-select');
            const monthlyRentInput = document.getElementById('lease-monthly-rent');
            const additionalDepositsWrap = document.getElementById('additional-deposits-rows');
            const rentDepositInput = document.getElementById('lease-rent-deposit');
            const rentDepositMeta = document.getElementById('rent-deposit-meta');
            const optionalFieldsCreateModal = document.getElementById('optional-fields-create-modal');
            const openOptionalFieldsCreateModalButton = document.getElementById('open-optional-fields-create-modal');
            const closeOptionalFieldsCreateModalButton = document.getElementById('close-optional-fields-create-modal');
            const utilityDefaultsTbody = document.getElementById('utility-defaults-tbody');
            const utilityDefaultsEmptyHint = document.getElementById('utility-defaults-empty');
            const openingArrearsCreateModal = document.getElementById('opening-arrears-create-modal');
            const openingArrearsCreateWrap = document.getElementById('opening-arrears-create-wrap');
            const closeOpeningArrearsCreateModalButton = document.getElementById('close-opening-arrears-create-modal');
            const openingArrearsCreateRows = document.getElementById('opening-arrears-create-rows');
            const openArrearsLineModalCreateButton = document.getElementById('open-arrears-line-modal-create');
            const arrearsLineModalCreate = document.getElementById('arrears-line-modal-create');
            const closeArrearsLineModalCreateButton = document.getElementById('close-arrears-line-modal-create');
            const cancelArrearsLineModalCreateButton = document.getElementById('cancel-arrears-line-modal-create');
            const saveArrearsLineModalCreateButton = document.getElementById('save-arrears-line-modal-create');
            const arrearsLineCreateChargeType = document.getElementById('arrears-line-create-charge-type');
            const arrearsLineCreateSpecificCharge = document.getElementById('arrears-line-create-specific-charge');
            const arrearsLineCreatePeriod = document.getElementById('arrears-line-create-period');
            const arrearsLineCreateAmount = document.getElementById('arrears-line-create-amount');
            const toggleOpeningArrearsCreateButton = document.getElementById('toggle-opening-arrears-create');
            const openingDepositArrearsCreateRows = document.getElementById('opening-deposit-arrears-create-rows');
            const openingDepositArrearsCreateEmpty = document.getElementById('opening-deposit-arrears-create-empty');
            if (leaseForm && leaseFormToggleButton && leaseFormToggleLabel) {
                leaseFormToggleButton.addEventListener('click', () => {
                    const isHidden = leaseForm.classList.toggle('hidden');
                    leaseFormToggleButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                    leaseFormToggleLabel.textContent = isHidden ? 'Create lease' : 'Hide lease form';
                });
            }

            if (!propertySelect || !unitSelect || !monthlyRentInput) return;

            const normalizeTypeValue = (value) => (value || '').toString().trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            const toMoney = (value) => {
                const num = Number(value);
                return Number.isFinite(num) ? num.toFixed(2) : '';
            };
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
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
            const buildArrearsChargeTypeOptionsHtml = (rows) => rows
                .map((row) => `<option value="${row.value}">${row.label}</option>`)
                .join('');
            const syncOptionalSectionState = () => {
                const selectedUnit = getSelectedUnitOption();
                const enabled = !!selectedUnit;
                if (openOptionalFieldsCreateModalButton) {
                    openOptionalFieldsCreateModalButton.disabled = !enabled;
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
                        utilityDefaultsEmptyHint.textContent = 'Select a property and unit first.';
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
            const refreshOpeningArrearsChargeTypes = () => {
                const templateRows = getTemplateTypeRows(getCurrentPropertyId());
                const optionsHtml = templateRows.length > 0
                    ? buildArrearsChargeTypeOptionsHtml(templateRows)
                    : '<option value="">No property charge types configured</option>';

                if (arrearsLineCreateChargeType) {
                    const prev = arrearsLineCreateChargeType.value;
                    arrearsLineCreateChargeType.innerHTML = optionsHtml;
                    arrearsLineCreateChargeType.disabled = templateRows.length === 0;
                    arrearsLineCreateChargeType.value = templateRows.some((row) => row.value === prev)
                        ? prev
                        : (templateRows[0]?.value || '');
                }

                document.querySelectorAll('#opening-arrears-create-rows select[name$="[charge_type]"]').forEach((selectEl) => {
                    const select = selectEl;
                    if (!(select instanceof HTMLSelectElement)) return;
                    const prev = select.value;
                    select.innerHTML = optionsHtml;
                    select.disabled = templateRows.length === 0;
                    select.value = templateRows.some((row) => row.value === prev)
                        ? prev
                        : (templateRows[0]?.value || '');
                });
            };
            const renderOpeningDepositArrearsRows = () => {
                if (!openingDepositArrearsCreateRows) return;
                const defs = getEffectiveDepositDefinitions();
                const rows = defs
                    .filter((d) => String(d.deposit_key || '').trim() !== '')
                    .map((d) => ({
                        key: String(d.deposit_key || '').trim(),
                        label: String(d.label || d.deposit_key || 'Deposit').trim(),
                    }));
                openingDepositArrearsCreateRows.innerHTML = '';
                if (rows.length === 0) {
                    openingDepositArrearsCreateEmpty?.classList.remove('hidden');
                    return;
                }
                openingDepositArrearsCreateEmpty?.classList.add('hidden');
                rows.forEach((row) => {
                    const currentValue = openingDepositArrearsOld[row.key] ?? '';
                    const tr = document.createElement('tr');
                    tr.className = 'border-t border-amber-100';
                    tr.innerHTML = `
                        <td class="px-3 py-2 text-slate-700">${escapeHtml(row.label)}</td>
                        <td class="px-3 py-2">
                            <input type="number" name="opening_deposit_arrears[${escapeHtml(row.key)}]" value="${escapeHtml(currentValue)}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                        </td>
                    `;
                    openingDepositArrearsCreateRows.appendChild(tr);
                });
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
                refreshOpeningArrearsChargeTypes();
                renderOpeningDepositArrearsRows();
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
            openOptionalFieldsCreateModalButton?.addEventListener('click', () => {
                if (openOptionalFieldsCreateModalButton.disabled) return;
                optionalFieldsCreateModal?.classList.remove('hidden');
                optionalFieldsCreateModal?.classList.add('flex');
                renderUtilityDefaultsTable();
            });
            closeOptionalFieldsCreateModalButton?.addEventListener('click', () => {
                optionalFieldsCreateModal?.classList.add('hidden');
                optionalFieldsCreateModal?.classList.remove('flex');
            });
            optionalFieldsCreateModal?.addEventListener('click', (event) => {
                if (event.target !== optionalFieldsCreateModal) return;
                optionalFieldsCreateModal.classList.add('hidden');
                optionalFieldsCreateModal.classList.remove('flex');
            });
            additionalDepositsWrap?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-deposit-row')) return;
                const row = target.closest('.additional-deposit-row');
                if (row) row.remove();
                reindexDepositRows();
            });
            toggleOpeningArrearsCreateButton?.addEventListener('click', () => {
                if (!openingArrearsCreateModal) return;
                openingArrearsCreateModal.classList.remove('hidden');
                openingArrearsCreateModal.classList.add('flex');
            });
            closeOpeningArrearsCreateModalButton?.addEventListener('click', () => {
                openingArrearsCreateModal?.classList.add('hidden');
                openingArrearsCreateModal?.classList.remove('flex');
            });
            openingArrearsCreateModal?.addEventListener('click', (event) => {
                if (event.target !== openingArrearsCreateModal) return;
                openingArrearsCreateModal.classList.add('hidden');
                openingArrearsCreateModal.classList.remove('flex');
            });
            const openArrearsLineModalCreate = () => {
                if (!arrearsLineModalCreate) return;
                arrearsLineModalCreate.classList.remove('hidden');
                arrearsLineModalCreate.classList.add('flex');
            };
            const closeArrearsLineModalCreate = () => {
                if (!arrearsLineModalCreate) return;
                arrearsLineModalCreate.classList.add('hidden');
                arrearsLineModalCreate.classList.remove('flex');
            };
            const appendOpeningArrearsCreateRow = (chargeType = 'water', specificCharge = '', period = '', amount = '') => {
                if (!openingArrearsCreateRows) return;
                const templateRows = getTemplateTypeRows(getCurrentPropertyId());
                if (templateRows.length === 0) {
                    const msg = 'No charge types configured for the selected property.';
                    if (window.Swal) window.Swal.fire({ icon: 'info', title: 'No charge types', text: msg });
                    else alert(msg);
                    return;
                }
                const index = openingArrearsCreateRows.querySelectorAll('.opening-arrears-row').length;
                const row = document.createElement('tr');
                row.className = 'opening-arrears-row border-t border-amber-100';
                row.innerHTML = `
                    <td class="px-3 py-2">
                        <select name="opening_arrears[${index}][charge_type]" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                            ${buildArrearsChargeTypeOptionsHtml(templateRows)}
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
                if (select) {
                    select.value = templateRows.some((row) => row.value === chargeType)
                        ? chargeType
                        : (templateRows[0]?.value || '');
                }
                openingArrearsCreateRows.appendChild(row);
            };
            openArrearsLineModalCreateButton?.addEventListener('click', openArrearsLineModalCreate);
            closeArrearsLineModalCreateButton?.addEventListener('click', closeArrearsLineModalCreate);
            cancelArrearsLineModalCreateButton?.addEventListener('click', closeArrearsLineModalCreate);
            saveArrearsLineModalCreateButton?.addEventListener('click', () => {
                appendOpeningArrearsCreateRow(
                    (arrearsLineCreateChargeType && 'value' in arrearsLineCreateChargeType) ? arrearsLineCreateChargeType.value : 'water',
                    arrearsLineCreateSpecificCharge?.value || '',
                    arrearsLineCreatePeriod?.value || '',
                    arrearsLineCreateAmount?.value || ''
                );
                closeArrearsLineModalCreate();
            });
            openingArrearsCreateRows?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-opening-arrears-row')) return;
                const row = target.closest('.opening-arrears-row');
                if (row) row.remove();
            });

            

            propertySelect.addEventListener('change', () => {
                filterUnits();
                refreshUtilityTypeSources();
                refreshOpeningArrearsChargeTypes();
                renderOpeningDepositArrearsRows();
                syncOptionalSectionState();
                syncDepositRules();
            });
            unitSelect.addEventListener('change', syncMonthlyRentFromUnit);
            filterUnits();
            syncMonthlyRentFromUnit();
            refreshUtilityTypeSources();
            refreshOpeningArrearsChargeTypes();
            renderOpeningDepositArrearsRows();
            syncOptionalSectionState();
            syncDepositRules();
        })();
    </script>
</x-property.workspace>
