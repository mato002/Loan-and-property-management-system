<x-property.workspace
    title="Notices"
    subtitle="Track statutory or internal notices. Type is a short label you can align with templates later."
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No notices"
    empty-hint="Create a draft or sent notice below."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('property.tenants.notices', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All notices</a>
                <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['status' => 'draft']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Draft</a>
                <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['status' => 'sent']), absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Sent</a>
                <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['status' => 'closed']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Closed</a>
                <a href="{{ route('property.tenants.notices.export', (array) ($filters ?? []), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
            </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Early move-out / tenancy changes</p>
            <p class="mt-1 text-sm text-slate-600">Typical flow: Log notice → agree exit date → update lease to <span class="font-semibold">Terminated</span> (unit becomes Vacant automatically) → optionally publish under Listings.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Go to Leases
                    <i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.listings.vacant', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Publish vacancy
                    <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        @if (session('success'))
            <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p class="text-sm text-rose-700 dark:text-rose-400">{{ session('error') }}</p>
        @endif
        @if ($workflowAutoReminders)
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200 max-w-3xl">
                Workflow automation is ON: when "Due / response by" is blank, a default reminder due date (today + {{ $reminderLeadDays }} day{{ (int) $reminderLeadDays === 1 ? '' : 's' }}) is applied.
            </p>
        @endif

        @if (request()->query('view') !== '1')
            @php
                $showNoticeFormByDefault = $errors->hasAny(['pm_tenant_id','property_unit_id','notice_type','status','due_on','notes']);
            @endphp
            <details class="space-y-3 group" @if($showNoticeFormByDefault) open @endif>
            <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                <span class="group-open:hidden">Add notice</span>
                <span class="hidden group-open:inline">Hide notice form</span>
            </summary>
            <form method="post" action="{{ route('property.tenants.notices.store') }}" class="mt-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
                @csrf
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">New notice</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                    <x-property.quick-create-select
                        name="pm_tenant_id"
                        select-id="notice-tenant-select"
                        :required="true"
                        :options="collect($tenants)->map(fn($t) => ['value' => $t->id, 'label' => $t->name, 'selected' => (string) old('pm_tenant_id') === (string) $t->id])->all()"
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
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Unit (optional)</label>
                    <x-property.quick-create-select
                        name="property_unit_id"
                        select-id="notice-unit-select"
                        :required="false"
                        placeholder="—"
                        :options="collect($units)->map(fn($u) => ['value' => $u->id, 'label' => $u->property->name.' / '.$u->label, 'selected' => (string) old('property_unit_id') === (string) $u->id])->all()"
                        :create="[
                            'mode' => 'ajax',
                            'title' => 'Add unit',
                            'endpoint' => route('property.units.store_json'),
                            'fields' => [
                                ['name' => 'property_id', 'label' => 'Property', 'required' => true, 'span' => '2', 'type' => 'select', 'placeholder' => 'Select property', 'options' => collect($units)->map(fn($u) => ['value' => $u->property_id, 'label' => $u->property->name])->unique('value')->values()->all()],
                                ['name' => 'label', 'label' => 'Unit label', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. A1'],
                                ['name' => 'unit_type', 'label' => 'Unit type', 'required' => false, 'type' => 'select', 'options' => [['value' => 'apartment', 'label' => 'Apartment'], ['value' => 'single_room', 'label' => 'Single room'], ['value' => 'bedsitter', 'label' => 'Bedsitter'], ['value' => 'studio', 'label' => 'Studio'], ['value' => 'bungalow', 'label' => 'Bungalow'], ['value' => 'maisonette', 'label' => 'Maisonette'], ['value' => 'villa', 'label' => 'Villa'], ['value' => 'townhouse', 'label' => 'Townhouse'], ['value' => 'commercial', 'label' => 'Commercial']]],
                                ['name' => 'status', 'label' => 'Status', 'required' => false, 'type' => 'select', 'options' => [['value' => 'vacant', 'label' => 'Vacant'], ['value' => 'occupied', 'label' => 'Occupied'], ['value' => 'notice', 'label' => 'Notice']]],
                            ],
                        ]"
                    />
                    @error('property_unit_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Type</label>
                    <input type="text" name="notice_type" value="{{ old('notice_type', 'vacate') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="vacate, rent_increase…" />
                    @error('notice_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        @foreach (['draft', 'sent', 'acknowledged', 'closed'] as $st)
                            <option value="{{ $st }}" @selected(old('status', 'draft') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Due / response by</label>
                    <input type="date" name="due_on" value="{{ old('due_on') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('due_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes', $noticeTemplate ?? '') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save notice</button>
            </form>
            </details>
        @endif

        <script>
            (function () {
                const tenantToUnit = @json($tenantUnitMap ?? []);
                const tenantSelect = document.getElementById('notice-tenant-select');
                const unitSelect = document.getElementById('notice-unit-select');
                if (!tenantSelect || !unitSelect) return;

                const syncUnitFromTenant = () => {
                    const tenantId = String(tenantSelect.value || '');
                    if (!tenantId) return;
                    const unitId = tenantToUnit[tenantId];
                    if (!unitId) return;
                    const target = String(unitId);
                    if (unitSelect.querySelector(`option[value="${target}"]`)) {
                        unitSelect.value = target;
                    }
                };

                tenantSelect.addEventListener('change', syncUnitFromTenant);
                syncUnitFromTenant();
            })();
        </script>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.tenants.notices') }}" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tenant, type, notes..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['draft', 'sent', 'acknowledged', 'closed'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                    <select name="tenant_id" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach ($tenants as $t)
                            <option value="{{ $t->id }}" @selected((string) ($filters['tenant_id'] ?? '') === (string) $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.tenants.notices', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>
    </x-slot>
</x-property.workspace>
