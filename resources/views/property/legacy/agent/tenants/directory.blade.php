<x-property.workspace
    :title="$pageTitle"
    :subtitle="$pageSubtitle"
    :show-search="false"
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No tenants on file"
    empty-hint="Create tenants here, then add leases and invoices against them."
>
    @php
        $tenantCfg = $tenantFields ?? [];
        $tenantRequired = fn (string $k, bool $d = false) => (bool) (($tenantCfg[$k]['required'] ?? $d) && ($tenantCfg[$k]['enabled'] ?? true));
    @endphp
    <x-slot name="above">
        <div
            x-data="{ showTenantForm: @js($errors->any()), showImportForm: @js((bool) ($openImportModal ?? false) || !empty($lastImportStats ?? null) || (is_array($lastImportErrors ?? null) && count($lastImportErrors) > 0)) }"
            class="space-y-4"
        >
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm max-w-3xl">
            <p class="text-lg font-semibold text-slate-900">Tenant onboarding flow</p>
            <p class="mt-1 text-sm text-slate-600">Step 1: Add tenant → Step 2: Allocate unit (Lease) → Step 3: Create rent bill (Invoice) → Step 4: Collect payment.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Allocate unit (Lease)
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.invoices', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Create rent bill
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                </a>
                <a href="{{ route('property.revenue.payments', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Collect payment
                    <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="max-w-3xl flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-4 text-base font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 sm:w-auto"
                @click="showTenantForm = !showTenantForm"
            >
                <i class="fa-solid fa-user-plus text-lg" aria-hidden="true"></i>
                <span x-text="showTenantForm ? 'Hide add tenant form' : 'Add tenant'"></span>
            </button>
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-3 rounded-2xl border border-slate-300 bg-white px-6 py-4 text-base font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                @click="showImportForm = true"
            >
                <i class="fa-solid fa-file-import text-lg" aria-hidden="true"></i>
                Import tenants (CSV)
            </button>
        </div>

        @if ($showTenantForm ?? true)
        <form
            method="post"
            action="{{ route('property.tenants.store') }}"
            x-show="showTenantForm"
            x-cloak
            x-data="{
                showOpeningArrearsSection: @js($errors->hasAny(['opening_arrears_items','opening_arrears_items.*.type','opening_arrears_items.*.period','opening_arrears_items.*.amount','opening_arrears_amount','opening_arrears_as_of','opening_arrears_notes']) || count((array) old('opening_arrears_items', [])) > 0 || (float) old('opening_arrears_amount', 0) > 0 || trim((string) old('opening_arrears_notes', '')) !== ''),
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
            class="property-attention-card rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-2xl"
        >
            @csrf
            <h3 class="property-attention-title dark:text-white">Add Tenant</h3>
            <p class="property-attention-hint dark:text-slate-300">Create the tenant profile first, then move to lease allocation in the next step.</p>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" @required($tenantRequired('name', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" @required($tenantRequired('phone', true)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" @required($tenantRequired('email', false)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">National ID / ref</label>
                    <input type="text" name="national_id" value="{{ old('national_id') }}" @required($tenantRequired('id_number', false)) class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Risk</label>
                    <select name="risk_level" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="normal" @selected(old('risk_level', 'normal') === 'normal')>Normal</option>
                        <option value="medium" @selected(old('risk_level') === 'medium')>Medium</option>
                        <option value="high" @selected(old('risk_level') === 'high')>High</option>
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
                <span x-text="showOpeningArrearsSection ? 'Hide previous debt / opening arrears' : 'Add previous debt / opening arrears'"></span>
            </button>
            <div x-show="showOpeningArrearsSection" x-cloak class="rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-3">
                <p class="text-xs font-semibold text-amber-900">Previous debt / opening arrears (optional)</p>
                <p class="mt-1 text-xs text-amber-800/90">Add each carried-forward charge with a specific type, billing period, and demanded balance.</p>
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
                        <input type="number" name="opening_arrears_amount" value="{{ old('opening_arrears_amount') }}" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Auto-sums category values if left blank" />
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
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
                @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/40 p-3 cursor-pointer">
                <input type="checkbox" name="create_portal_login" value="1" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500" @checked(old('create_portal_login')) />
                <span class="text-sm text-slate-700 dark:text-slate-300">
                    <span class="font-medium text-slate-900 dark:text-white">Create portal login &amp; email credentials</span>
                    <span class="block text-slate-500 dark:text-slate-400 mt-0.5">Requires a unique email. The tenant receives sign-in instructions and a temporary password.</span>
                </span>
            </label>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save tenant</button>
        </form>
        @endif
            <div
                x-show="showImportForm"
                x-cloak
                class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4"
                @keydown.escape.window="showImportForm = false"
            >
                <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Import tenants</h3>
                            <p class="mt-1 text-sm text-slate-600">Upload a CSV to bulk add or update tenants (matched by email when provided).</p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" @click="showImportForm = false" aria-label="Close import modal">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="max-h-[75vh] overflow-y-auto p-5 space-y-4">
                        @if (! empty($lastImportStats))
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                                <div class="font-semibold">Last import</div>
                                <div class="text-sm mt-1">
                                    Created: <span class="font-semibold tabular-nums">{{ $lastImportStats['created'] ?? 0 }}</span>,
                                    Updated: <span class="font-semibold tabular-nums">{{ $lastImportStats['updated'] ?? 0 }}</span>,
                                    Skipped: <span class="font-semibold tabular-nums">{{ $lastImportStats['skipped'] ?? 0 }}</span>,
                                    Portal logins created: <span class="font-semibold tabular-nums">{{ $lastImportStats['portal_logins_created'] ?? 0 }}</span>,
                                    Errors: <span class="font-semibold tabular-nums">{{ $lastImportStats['errors'] ?? 0 }}</span>
                                </div>
                            </div>
                        @endif

                        @if (is_array($lastImportErrors ?? null) && count($lastImportErrors) > 0)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                                <div class="font-semibold">Import errors (showing up to {{ count($lastImportErrors) }})</div>
                                <ul class="mt-2 list-disc pl-5 text-sm space-y-1">
                                    @foreach ($lastImportErrors as $err)
                                        <li class="break-words">{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="text-sm text-slate-600">
                            Required columns:
                            <span class="font-semibold text-slate-900">{{ implode(', ', $expectedColumns ?? []) }}</span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ route('property.tenants.import.template') }}"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Download CSV template
                            </a>
                        </div>

                        <form
                            method="post"
                            action="{{ route('property.tenants.import.store') }}"
                            enctype="multipart/form-data"
                            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3"
                        >
                            @csrf
                            <h4 class="text-sm font-semibold text-slate-900">Upload CSV</h4>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">CSV file</label>
                                <input
                                    type="file"
                                    name="file"
                                    accept=".csv,text/csv,text/plain"
                                    required
                                    class="mt-1 block w-full text-sm"
                                />
                                @error('file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Import tenants
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.tenants.directory', absolute: false) }}" class="w-full flex flex-wrap items-end gap-2">
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                autocomplete="off"
                placeholder="Search name, phone, email, ID…"
                class="w-full min-w-0 sm:max-w-md rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2"
            />
            <select name="risk" class="w-full sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">All risk</option>
                <option value="normal" @selected(($filters['risk'] ?? '') === 'normal')>Normal</option>
                <option value="medium" @selected(($filters['risk'] ?? '') === 'medium')>Medium</option>
                <option value="high" @selected(($filters['risk'] ?? '') === 'high')>High</option>
            </select>
            <select name="portal" class="w-full sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                <option value="">Portal login: all</option>
                <option value="with" @selected(($filters['portal'] ?? '') === 'with')>With portal login</option>
                <option value="without" @selected(($filters['portal'] ?? '') === 'without')>Without portal login</option>
            </select>
            <select name="per_page" class="w-full sm:w-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2">
                @foreach ([10, 20, 50, 100] as $pageSize)
                    <option value="{{ $pageSize }}" @selected((int) ($filters['per_page'] ?? 20) === $pageSize)>{{ $pageSize }} / page</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.tenants.directory', absolute: false) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            <a href="{{ route('property.tenants.directory.export', request()->query()) }}" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">Export CSV</a>
        </form>
    </x-slot>

    @if (isset($tenantPager))
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $tenantPager->firstItem() ?? 0 }}-{{ $tenantPager->lastItem() ?? 0 }} of {{ $tenantPager->total() }} tenants.
                </p>
                <div>
                    {{ $tenantPager->onEachSide(1)->links() }}
                </div>
            </div>
        </x-slot>
    @endif

    @if (session()->has('next_steps') && ($ns = session('next_steps')) && is_array($ns))
        <div
            x-data="{ open: true }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40"
        >
            <div class="mx-4 max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-slate-900/10">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                        Tenant onboarding
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $ns['title'] ?? 'Tenant saved' }}
                    </h2>
                    @php
                        $tenantSummary = $ns['tenant'] ?? null;
                    @endphp
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $ns['message'] ?? 'Next, continue with the onboarding steps below.' }}
                    </p>
                    @if (is_array($tenantSummary))
                        <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3">
                                <dt class="font-medium text-slate-700">Tenant</dt>
                                <dd class="text-right">{{ $tenantSummary['name'] ?? '' }}</dd>
                            </div>
                            @if (!empty($tenantSummary['phone']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Phone</dt>
                                    <dd class="text-right">{{ $tenantSummary['phone'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($tenantSummary['email']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">Email</dt>
                                    <dd class="text-right">{{ $tenantSummary['email'] }}</dd>
                                </div>
                            @endif
                            @if (!empty($tenantSummary['national_id']))
                                <div class="flex justify-between gap-3">
                                    <dt class="font-medium text-slate-700">ID / ref</dt>
                                    <dd class="text-right">{{ $tenantSummary['national_id'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif
                </div>
                <div class="px-5 py-4 space-y-3">
                    <p class="text-xs font-medium text-slate-500">
                        Choose where to go next:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($ns['actions'] ?? [] as $action)
                            @php
                                $kind = $action['kind'] ?? 'secondary';
                                $isPrimary = $kind === 'primary';
                                $frame = $action['turbo_frame'] ?? null;
                            @endphp
                            <a
                                href="{{ $action['href'] ?? '#' }}"
                                @if ($frame) data-turbo-frame="{{ $frame }}" @endif
                                class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold
                                    {{ $isPrimary ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-slate-300 text-slate-700 bg-white hover:bg-slate-50' }}"
                                @click="open = false"
                            >
                                @if (!empty($action['icon']))
                                    <i class="{{ $action['icon'] }}" aria-hidden="true"></i>
                                @endif
                                <span>{{ $action['label'] ?? 'Continue' }}</span>
                            </a>
                        @endforeach
                    </div>
                    <button
                        type="button"
                        class="mt-1 text-xs font-medium text-slate-500 hover:text-slate-700"
                        @click="open = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-property.workspace>
