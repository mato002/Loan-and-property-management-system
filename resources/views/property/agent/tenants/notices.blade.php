@php
    $showNoticeFormByDefault = $errors->hasAny(['pm_tenant_id', 'property_unit_id', 'notice_type', 'status', 'due_on', 'notes']);
@endphp
<div
    x-data="{ showNoticeForm: @js($showNoticeFormByDefault) }"
    class="w-full min-w-0"
    data-property-page-modals
>
<x-property.workspace
    :legacy-toolbar="false"
    title="Notices"
    subtitle="Track statutory or internal notices. Type is a short label you can align with templates later."
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No notices"
    empty-hint="Create a draft or sent notice below."
>
    @if (request()->query('view') !== '1')
        <x-slot name="actions">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                @click="showNoticeForm = true"
            >
                <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                <span>Add notice</span>
            </button>
        </x-slot>

        <x-slot name="modals">
            <x-property.modal
                show="showNoticeForm"
                close="showNoticeForm = false"
                name="notice-create"
                title="New notice"
                max-width="2xl"
            >
                <form method="post" action="{{ route('property.tenants.notices.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Tenant</label>
                            <x-property.quick-create-select
                                name="pm_tenant_id"
                                select-id="notice-tenant-select"
                                :required="true"
                                :options="collect($tenants)->map(fn($t) => ['value' => $t->id, 'label' => $t->name, 'selected' => (string) old('pm_tenant_id') === (string) $t->id])->all()"
                                :create="\App\Support\Property\PmTenantQuickCreateFields::quickCreateConfig()"
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
                                @foreach (['draft', 'pending_approval', 'approved', 'sent', 'delivered', 'acknowledged', 'disputed', 'expired', 'cancelled', 'escalated', 'closed'] as $st)
                                    <option value="{{ $st }}" @selected(old('status', 'draft') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
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
            </x-property.modal>

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
    @endif

    <x-slot name="tabs">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('property.tenants.notices', absolute: false) }}" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">All notices</a>
            <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['status' => 'draft']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Draft</a>
            <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['status' => 'sent']), absolute: false) }}" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Sent</a>
            <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['status' => 'delivered']), absolute: false) }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Delivered</a>
            <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['status' => 'closed']), absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Closed</a>
            <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['risk' => 'denied']), absolute: false) }}" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Denied actions</a>
            <a href="{{ route('property.tenants.notices', array_merge((array) ($filters ?? []), ['risk' => 'escalated']), absolute: false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Escalations</a>
            <a href="{{ route('property.tenants.notices.export', (array) ($filters ?? []), absolute: false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.tenants.notices') }}" class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 shadow-sm space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-8">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tenant, type, notes..." class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">All</option>
                        @foreach (['draft', 'pending_approval', 'approved', 'sent', 'delivered', 'acknowledged', 'disputed', 'expired', 'cancelled', 'escalated', 'closed'] as $st)
                            <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
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
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Event type</label>
                    <select name="event_type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Any</option>
                        @foreach (['created', 'status_changed', 'dispatched', 'permission_denied', 'transition_denied'] as $evt)
                            <option value="{{ $evt }}" @selected(($filters['event_type'] ?? '') === $evt)>{{ ucfirst(str_replace('_', ' ', $evt)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Risk focus</label>
                    <select name="risk" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="">Any</option>
                        <option value="denied" @selected(($filters['risk'] ?? '') === 'denied')>Denied actions</option>
                        <option value="escalated" @selected(($filters['risk'] ?? '') === 'escalated')>Escalated</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply filters</button>
                <a href="{{ route('property.tenants.notices', absolute: false) }}" class="rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</a>
            </div>
        </form>
    </x-slot>

    <x-slot name="secondary">
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

        @if ($workflowAutoReminders)
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200 max-w-3xl">
                Workflow automation is ON: when "Due / response by" is blank, a default reminder due date (today + {{ $reminderLeadDays }} day{{ (int) $reminderLeadDays === 1 ? '' : 's' }}) is applied.
            </p>
        @endif

        @if(isset($notices) && $notices->count() > 0)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm max-w-5xl">
                <h3 class="text-sm font-semibold text-slate-900">Notice audit timeline and proof of service</h3>
                <p class="mt-1 text-xs text-slate-500">Update status with proof attachment and review service evidence per notice.</p>
                <div class="mt-4 space-y-3">
                    @foreach($notices->take(20) as $notice)
                        <details class="rounded-xl border border-slate-200 p-3">
                            <summary class="cursor-pointer list-none flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-medium text-slate-800">
                                    #{{ $notice->id }}  -  {{ $notice->tenant?->name ?? 'Unknown tenant' }}  -  {{ ucfirst(str_replace('_',' ', (string) $notice->status)) }}
                                </span>
                                <span class="text-xs text-slate-500">{{ optional($notice->created_at)->format('Y-m-d H:i') }}</span>
                            </summary>
                            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-700 space-y-1">
                                    <p><span class="font-semibold">Type:</span> {{ str_replace('_',' ', (string) $notice->notice_type) }}</p>
                                    <p><span class="font-semibold">Created by:</span> {{ $notice->createdBy?->name ?? 'System' }}</p>
                                    <p><span class="font-semibold">Served by:</span> {{ $notice->servedBy?->name ?? '—' }}</p>
                                    <p><span class="font-semibold">Served at:</span> {{ optional($notice->served_at)->format('Y-m-d H:i') ?? '—' }}</p>
                                    <p><span class="font-semibold">Message ID:</span> {{ $notice->message_id ?? '—' }}</p>
                                    <p><span class="font-semibold">Delivery proof ID:</span> {{ $notice->delivery_proof_id ?? '—' }}</p>
                                    <p><span class="font-semibold">Proof file:</span>
                                        @if(!empty($notice->proof_attachment))
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($notice->proof_attachment) }}" target="_blank" rel="noopener" class="text-indigo-700 hover:underline">Open proof</a>
                                        @else
                                            —
                                        @endif
                                    </p>
                                    <div class="mt-3">
                                        <p class="font-semibold text-slate-800">Audit events</p>
                                        <div class="mt-1 space-y-1">
                                            @forelse(($notice->events ?? collect())->take(8) as $event)
                                                <div class="rounded border border-slate-200 bg-white px-2 py-1">
                                                    <p class="text-[11px] font-semibold text-slate-700">
                                                        {{ ucfirst(str_replace('_',' ', (string) $event->event_type)) }}
                                                        @if(!empty($event->from_status) || !empty($event->to_status))
                                                             -  {{ ucfirst(str_replace('_',' ', (string) ($event->from_status ?? '—'))) }} → {{ ucfirst(str_replace('_',' ', (string) ($event->to_status ?? '—'))) }}
                                                        @endif
                                                    </p>
                                                    <p class="text-[11px] text-slate-500">
                                                        {{ optional($event->created_at)->format('Y-m-d H:i') }} by {{ $event->actor?->name ?? 'System' }}
                                                    </p>
                                                </div>
                                            @empty
                                                <p class="text-[11px] text-slate-500">No audit events yet.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('property.tenants.notices.status', ['notice' => $notice->id], absolute: false) }}" class="space-y-2 rounded-lg border border-slate-200 p-3">
                                    @csrf
                                    <label class="block text-xs font-medium text-slate-600">Update status</label>
                                    <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        @foreach(($legalStatuses ?? []) as $st)
                                            <option value="{{ $st }}" @selected((string) $notice->status === (string) $st)>{{ ucfirst(str_replace('_',' ', $st)) }}</option>
                                        @endforeach
                                    </select>
                                    <label class="block text-xs font-medium text-slate-600">Proof attachment (optional)</label>
                                    <input type="file" name="proof_attachment" class="w-full text-xs" />
                                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700">Save status/proof</button>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    </x-slot>
</x-property.workspace>
</div>
