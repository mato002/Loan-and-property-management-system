        <form
            method="post"
            action="{{ route('property.leases.store') }}"
            id="lease-form-wrapper"
            data-turbo-frame="lease-create-modal"
            x-data="{
                showOpeningArrearsSection: @js(($errors ?? null)?->hasAny(['opening_arrears_items','opening_arrears_items.*.type','opening_arrears_items.*.label','opening_arrears_items.*.period','opening_arrears_items.*.amount','opening_arrears_amount','opening_arrears_as_of','opening_arrears_notes']) || count((array) old('opening_arrears_items', [])) > 0 || (float) old('opening_arrears_amount', 0) > 0 || trim((string) old('opening_arrears_notes', '')) !== ''),
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
                        :options="[]"
                        placeholder="Loading tenants…"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Create tenant',
                            'endpoint' => route('property.tenants.store_json'),
                            'fields' => [
                                ['name' => 'name', 'label' => 'Full name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. John Tenant'],
                                ['name' => 'phone', 'label' => 'Phone', 'required' => false, 'span' => '2', 'placeholder' => '+2547â€¦'],
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
                                        <div class="deposit-line-meta rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">â€”</div>
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
            @php($selectedUnitId = (int) ($leaseFormSelectedUnitId ?? 0))
            @php($selectedPropertyId = (string) old('property_id', request('property_id', '')))
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property (with vacant units)</label>
                <select id="lease-property-select" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">All properties</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit (vacant)</label>
                <select id="lease-unit-select" name="property_unit_ids[]" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Loading units…</option>
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
                    <p id="rent-deposit-meta" class="mt-1 text-xs text-slate-500">â€”</p>
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
