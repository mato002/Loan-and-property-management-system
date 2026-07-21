        @php
            $leaseCreateAlpineConfig = \App\Support\Property\LeaseCreateAlpineConfig::build($errors ?? null, $openingArrearsTypeOptions ?? []);
            $additionalDeposits = old('additional_deposits', []);
            $openingArrearsRows = old('opening_arrears', []);
            $openingDepositArrearsRows = old('opening_deposit_arrears', []);
            $selectedUnitId = (int) ($leaseFormSelectedUnitId ?? 0);
            $selectedPropertyId = (string) old('property_id', request('property_id', ''));
            $leaseFormAlpineOnParent = (bool) ($leaseFormAlpineOnParent ?? false);
        @endphp
        @if (! $leaseFormAlpineOnParent)
            <div
                data-lease-form-root
                x-data="leaseCreateFormAlpineState(@js($leaseCreateAlpineConfig))"
            >
        @endif
        <form
            method="post"
            action="{{ route('property.leases.store') }}"
            id="lease-form-wrapper"
            data-turbo-frame="{{ $leaseFormTurboFrame ?? 'property-main' }}"
            class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 w-full max-w-3xl"
        >
            @csrf
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New lease</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400">Allocate one vacant unit to a tenant to activate tenancy and unlock monthly billing.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                    <x-property.quick-create-select
                        name="pm_tenant_id"
                        :required="$leaseRequired('tenant_id', true)"
                        :options="[]"
                        placeholder="Loading tenants…"
                        :create="\App\Support\Property\PmTenantQuickCreateFields::quickCreateConfig()"
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
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" @required($leaseRequired('status', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="draft" @selected(old('status', 'active') === 'draft')>Draft</option>
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="expired" @selected(old('status', 'active') === 'expired')>Expired</option>
                    <option value="terminated" @selected(old('status', 'active') === 'terminated')>Terminated</option>
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <button type="button" id="open-optional-fields-create-modal" class="inline-flex items-center gap-2 rounded-lg border border-emerald-700 bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:border-emerald-300 disabled:bg-emerald-400/90 disabled:text-white/95 disabled:shadow-none dark:border-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500 dark:disabled:bg-emerald-800/80" disabled>
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                    Utilities, deposits & terms
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
            <div class="rounded-xl border border-amber-200 bg-amber-50/40 dark:border-amber-700/40 dark:bg-amber-900/10 p-3 space-y-2">
                <button type="button" id="toggle-opening-arrears-create" class="inline-flex items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-700 px-3 py-2 text-xs font-medium text-amber-800 dark:text-amber-300 hover:bg-amber-100/70 dark:hover:bg-amber-800/20">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                    <span>Add previous carry-forward details for this tenant</span>
                </button>
            </div>

            <div data-lease-carry-forward-sync class="hidden" aria-hidden="true"></div>
            <input type="hidden" name="carry_forward_submitted" value="0" />
            <input type="hidden" name="carry_forward_touched" value="0" />

            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save lease</button>
        </form>

        @include('property.agent.partials.lease_create_modals', [
            'formId' => 'lease-form-wrapper',
            'additionalDeposits' => $additionalDeposits,
            'openingArrearsRows' => $openingArrearsRows,
        ])

        @if (! $leaseFormAlpineOnParent)
            </div>
        @endif
