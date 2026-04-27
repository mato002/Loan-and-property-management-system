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
                'utility_expense_amount',
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
                    <select name="status" @required($leaseRequired('status', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
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
                        <option value="{{ $u->id }}" data-property-id="{{ $u->property_id }}" data-rent="{{ (float) ($u->rent_amount ?? 0) }}" @selected(collect(old('property_unit_ids', []))->contains($u->id))>{{ $u->property->name }} / {{ $u->label }}</option>
                    @empty
                        <option value="" disabled>No vacant units</option>
                    @endforelse
                </select>
                <p class="mt-1 text-xs text-slate-500">A tenant can only be assigned one unit.</p>
                @error('property_unit_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('property_unit_ids.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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
                    <input type="number" name="deposit_amount" value="{{ old('deposit_amount', 0) }}" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('deposit_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            @php($additionalDeposits = old('additional_deposits', []))
            <div>
                <div class="flex items-center justify-between gap-3">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Other deposits paid (optional)</label>
                    <button type="button" id="add-deposit-row" class="rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60">
                        + Add deposit field
                    </button>
                </div>
                <div id="additional-deposits-rows" class="mt-2 space-y-2">
                    @foreach ($additionalDeposits as $idx => $row)
                        <div class="grid gap-2 sm:grid-cols-[1fr_180px_auto] additional-deposit-row">
                            <input type="text" name="additional_deposits[{{ $idx }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="e.g. Water deposit" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            <input type="number" name="additional_deposits[{{ $idx }}][amount]" value="{{ $row['amount'] ?? '' }}" step="0.01" min="0" placeholder="Amount" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                            <button type="button" class="remove-deposit-row rounded-lg border border-red-200 px-2.5 py-2 text-xs font-medium text-red-700 hover:bg-red-50">Remove</button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button" class="quick-add-deposit rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-xs text-slate-700 dark:text-slate-200" data-label="Water deposit">+ Water deposit</button>
                    <button type="button" class="quick-add-deposit rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-xs text-slate-700 dark:text-slate-200" data-label="Electricity deposit">+ Electricity deposit</button>
                </div>
                @error('additional_deposits')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('additional_deposits.*.label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('additional_deposits.*.amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Terms summary</label>
                <textarea name="terms_summary" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('terms_summary', $leaseTemplate ?? '') }}</textarea>
                @error('terms_summary')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button
                type="button"
                @click="showOpeningArrearsSection = !showOpeningArrearsSection"
                class="inline-flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100"
            >
                <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                <span x-text="showOpeningArrearsSection ? 'Hide opening arrears for this tenant' : 'Add opening arrears for this tenant'"></span>
            </button>
            <div x-show="showOpeningArrearsSection" x-cloak class="rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-3">
                <p class="text-xs font-semibold text-amber-900">Opening arrears at lease setup (optional)</p>
                <p class="mt-1 text-xs text-amber-800/90">Use this when tenant starts lease with brought-forward balance.</p>
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
                        <input type="number" name="opening_arrears_amount" value="{{ old('opening_arrears_amount') }}" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Auto-sums charge lines if left blank" />
                        @error('opening_arrears_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">As of date</label>
                        <input type="date" name="opening_arrears_as_of" value="{{ old('opening_arrears_as_of') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        @error('opening_arrears_as_of')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Arrears note (optional)</label>
                    <input type="text" name="opening_arrears_notes" value="{{ old('opening_arrears_notes') }}" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Source / reason for brought-forward debt" />
                    @error('opening_arrears_notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save lease</button>
        </form>
        </details>
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
            const propertySelect = document.getElementById('lease-property-select');
            const unitSelect = document.getElementById('lease-unit-select');
            const monthlyRentInput = document.getElementById('lease-monthly-rent');
            const additionalDepositsWrap = document.getElementById('additional-deposits-rows');
            const addDepositRowButton = document.getElementById('add-deposit-row');

            if (!propertySelect || !unitSelect || !monthlyRentInput) return;

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
            };

            const createDepositRow = (label = '', amount = '') => {
                if (!additionalDepositsWrap) return;
                const index = additionalDepositsWrap.querySelectorAll('.additional-deposit-row').length;
                const row = document.createElement('div');
                row.className = 'grid gap-2 sm:grid-cols-[1fr_180px_auto] additional-deposit-row';
                row.innerHTML = `
                    <input type="text" name="additional_deposits[${index}][label]" value="${label}" placeholder="e.g. Water deposit" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <input type="number" name="additional_deposits[${index}][amount]" value="${amount}" step="0.01" min="0" placeholder="Amount" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    <button type="button" class="remove-deposit-row rounded-lg border border-red-200 px-2.5 py-2 text-xs font-medium text-red-700 hover:bg-red-50">Remove</button>
                `;
                additionalDepositsWrap.appendChild(row);
            };

            addDepositRowButton?.addEventListener('click', () => createDepositRow());
            document.querySelectorAll('.quick-add-deposit').forEach((btn) => {
                btn.addEventListener('click', () => createDepositRow(btn.getAttribute('data-label') || ''));
            });
            additionalDepositsWrap?.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-deposit-row')) return;
                const row = target.closest('.additional-deposit-row');
                if (row) row.remove();
            });

            propertySelect.addEventListener('change', filterUnits);
            unitSelect.addEventListener('change', syncMonthlyRentFromUnit);
            filterUnits();
            syncMonthlyRentFromUnit();
        })();
    </script>
</x-property.workspace>
