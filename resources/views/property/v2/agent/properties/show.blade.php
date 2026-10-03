<x-property.workspace :compact-list="true"
    :title="'Property: '.$property->name"
    :subtitle="'360° property workspace — '.$periodLabel"
    back-route="property.properties.list"
    :stats="[]"
    :columns="[]"
>
    @php
        $firstVacantUnit = collect($units ?? [])->firstWhere('status', \App\Models\PropertyUnit::STATUS_VACANT);
        $activeTab = $activeTab ?? 'overview';
        $preserveQuery = array_filter([
            'month' => $monthValue ?? null,
            'fy' => $fyValue ?? null,
            'unit_status' => $filters['unit_status'] ?? null,
            'unit_q' => $filters['unit_q'] ?? null,
            'unit_arrears' => $filters['unit_arrears'] ?? null,
            'collection_channel' => $filters['collection_channel'] ?? null,
            'collection_q' => $filters['collection_q'] ?? null,
            'export_report' => $filters['export_report'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
        $hubModalDefaults = [
            'addUnitOpen' => $errors->hasAny(['label', 'unit_type', 'bedrooms', 'rent_amount', 'status', 'unit_count'])
                && (string) old('property_id') === (string) $property->id,
            'showHubLinkLandlord' => $errors->hasAny(['user_id', 'ownership_percent'])
                && old('return_to') === 'property_show',
            'showHubMaintenanceForm' => $errors->hasAny(['property_unit_id', 'category', 'urgency', 'description'])
                && old('return_to') === 'property_show',
            'showLeaseCreateForm' => false,
        ];
        $hubSummaryStats = $stats ?? [];
    @endphp

    <x-slot name="pageModalsAttributes" x-data="{!! \Illuminate\Support\Js::from($hubModalDefaults) !!}"></x-slot>

    <x-slot name="actions">
        @include('property.agent.partials.hub_quick_actions', ['actions' => $quickActions ?? []])
    </x-slot>

    <x-slot name="modals">
        @include('property.agent.properties.partials.hub_modals')
        @include('property.agent.partials.lease_create_shell', [
            'openLeaseCreateModal' => false,
            'leaseCreateFormUrl' => route('property.leases.create_form', array_filter([
                'property_id' => $property->id,
                'unit_id' => $firstVacantUnit->id ?? null,
                'return_to' => 'property_show',
                'return_property_id' => $property->id,
                'return_tab' => 'occupancy',
            ]), false),
        ])
    </x-slot>

    @if ($property->isManagementReadOnly())
        <div class="mb-4 rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-800">
            <span class="font-semibold">{{ $managementStatusLabel ?? $property->managementStatusLabel() }}</span>
            — This property is read-only. Operational actions are disabled; history, statements, and accounting remain available.
            @if (auth()->user()?->hasPmPermission('properties.manage') || auth()->user()?->hasPmPermission('property.archive.view'))
                <a href="{{ route('property.properties.show', ['property' => $property->id, 'tab' => 'offboarding'], false) }}" data-turbo-frame="property-main" class="ml-2 font-medium text-indigo-700 hover:underline">Open Offboarding tab</a>
            @endif
        </div>
    @elseif ($property->isOffboarding())
        <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="font-semibold">Offboarding in progress</span>
            — New leases, tenants, and utility setup are blocked. Settle balances then archive when ready.
            <a href="{{ route('property.properties.show', ['property' => $property->id, 'tab' => 'offboarding'], false) }}" data-turbo-frame="property-main" class="ml-2 font-medium text-amber-800 hover:underline">Continue in Offboarding tab</a>
        </div>
    @endif

    <x-slot name="above">
        <div class="grid gap-2 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)] lg:items-stretch">
            <form method="get" action="{{ route('property.properties.show', ['property' => $property->id]) }}" data-turbo-frame="property-main" class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 px-2.5 py-2 shadow-sm w-full min-w-0">
                <input type="hidden" name="tab" value="{{ $activeTab }}" />
                <div class="w-[8.5rem] min-w-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500">Month</label>
                    <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 bg-white text-sm px-2" />
                </div>
                <div class="w-[5.5rem] min-w-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500">FY</label>
                    <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 bg-white text-sm px-2" />
                </div>
                @if ($activeTab !== 'units')
                <div class="w-[8rem] min-w-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500">Unit status</label>
                    <select name="unit_status" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 bg-white text-sm px-2">
                        <option value="">All</option>
                        @foreach (\App\Models\PropertyUnit::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['unit_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-[8.5rem] min-w-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500">Channel</label>
                    <select name="collection_channel" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 bg-white text-sm px-2">
                        <option value="">All</option>
                        @foreach(($availableCollectionChannels ?? []) as $channel)
                            <option value="{{ $channel }}" @selected(($filters['collection_channel'] ?? '') === $channel)>{{ strtoupper($channel) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-[10rem] min-w-0 grow sm:grow-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500">Collection search</label>
                    <input type="text" name="collection_q" value="{{ $filters['collection_q'] ?? '' }}" placeholder="Tenant or reference" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 bg-white text-sm px-2" />
                </div>
                @endif
                <div class="w-[9.5rem] min-w-0">
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500">Export type</label>
                    <select name="export_report" class="mt-0.5 w-full h-9 rounded-md border border-slate-200 bg-white text-sm px-2">
                        <option value="full" @selected(($filters['export_report'] ?? 'full') === 'full')>Full intelligence</option>
                        <option value="units" @selected(($filters['export_report'] ?? '') === 'units')>Units report</option>
                        <option value="collections" @selected(($filters['export_report'] ?? '') === 'collections')>Collections report</option>
                        <option value="channels" @selected(($filters['export_report'] ?? '') === 'channels')>Channel report</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-blue-600 px-3 text-xs font-semibold text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.properties.show', ['property' => $property->id, 'tab' => $activeTab], false) }}" data-turbo-frame="property-main" class="inline-flex h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                @include('property.agent.partials.table_export_dropdown', [
                    'current' => true,
                    'formats' => \App\Support\TableExportLinks::STANDARD_FORMATS,
                    'class' => 'h-9 rounded-md border border-indigo-300 bg-white px-3 text-xs font-semibold text-indigo-700 hover:bg-indigo-50',
                ])
            </form>

            @if (count($hubSummaryStats) > 0)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 px-2.5 py-2 shadow-sm min-w-0">
                    <x-property.collapsible-stats storage-key="property.hub.propertySummaryVisible">
                        <x-property.compact-stat-strip :stats="$hubSummaryStats" />
                    </x-property.collapsible-stats>
                </div>
            @endif
        </div>
    </x-slot>

    <x-property.entity-hub
        entity="property"
        route-name="property.properties.show"
        :route-params="['property' => $property->id]"
        :active-tab="$activeTab"
        :preserve-query="$preserveQuery"
        :alerts="$alerts ?? []"
    />

    <div>
    @if (in_array($activeTab, ['overview', 'landlords'], true))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @if ($activeTab === 'overview')
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Property profile</h3>
            <div class="mt-2 text-sm text-slate-700 space-y-1">
                <p><span class="text-slate-500">Name:</span> {{ $property->name }}</p>
                <p><span class="text-slate-500">Code:</span> {{ $property->code ?: '—' }}</p>
                <p><span class="text-slate-500">City:</span> {{ $property->city ?: '—' }}</p>
                <p><span class="text-slate-500">Address:</span> {{ $property->address_line ?: '—' }}</p>
                <p><span class="text-slate-500">LR / title:</span> {{ $property->lr_number ?: '—' }}</p>
                <p><span class="text-slate-500">Category:</span> {{ $property->category ?: '—' }}{{ $property->property_type ? ' · '.$property->property_type : '' }}</p>
                <p><span class="text-slate-500">Estate / zone:</span> {{ trim(collect([$property->estate, $property->zone])->filter()->implode(' · ')) ?: '—' }}</p>
                <p><span class="text-slate-500">Let / manage:</span> {{ $property->management_mode === 'letting' ? 'Letting' : 'Managing' }}</p>
                <p>
                    <span class="text-slate-500">Linked landlord{{ count($ownerRows) === 1 ? '' : 's' }}:</span>
                    @if (count($ownerRows) > 0)
                        @foreach ($ownerRows as $linkedOwner)
                            @if (! empty($linkedOwner['id']))
                                <a href="{{ route('property.landlords.show', $linkedOwner['id'], false) }}" class="font-medium text-indigo-600 hover:text-indigo-700 hover:underline">{{ $linkedOwner['name'] }}</a>@if (! $loop->last), @endif
                            @else
                                {{ $linkedOwner['name'] }}@if (! $loop->last), @endif
                            @endif
                        @endforeach
                    @else
                        <span class="text-amber-700">None linked</span>
                    @endif
                </p>
                <p><span class="text-slate-500">Active leases:</span> {{ (int) ($activeLeasesCount ?? 0) }} ({{ \App\Services\Property\PropertyMoney::kes((float) ($activeLeaseRent ?? 0)) }} / month)</p>
            </div>
        </div>
        @endif
        @if ($activeTab === 'landlords')
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Landlord ownership & earnings</h3>
            </div>
            <div class="mt-3 max-h-[22rem] overflow-auto">
                <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Landlord</th>
                            <th class="px-3 py-2">Share %</th>
                            <th class="px-3 py-2">Collected share</th>
                            <th class="px-3 py-2">Arrears share</th>
                            <th class="px-3 py-2">Your earnings</th>
                            <th class="px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ownerRows as $o)
                            @php $ownerArrears = (float) ($o['share_arrears'] ?? 0); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    @if (! empty($o['id']))
                                        <a href="{{ route('property.landlords.show', $o['id'], false) }}" class="font-medium text-indigo-600 hover:text-indigo-700 hover:underline">{{ $o['name'] }}</a>
                                    @else
                                        <div class="font-medium text-slate-900">{{ $o['name'] }}</div>
                                    @endif
                                    <div class="text-xs text-slate-500">{{ $o['email'] }}</div>
                                </td>
                                <td class="px-3 py-2 tabular-nums">{{ number_format((float) $o['ownership_percent'], 2) }}%</td>
                                <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $o['share_collected']) }}</td>
                                <td class="px-3 py-2 tabular-nums {{ $ownerArrears > 0.009 ? 'bg-rose-100 font-semibold text-rose-800' : '' }}">{{ \App\Services\Property\PropertyMoney::kes($ownerArrears) }}</td>
                                <td class="px-3 py-2 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $o['agent_earning_portion']) }}</td>
                                <td class="px-3 py-2">
                                    @if (! empty($o['id']))
                                        <a href="{{ route('property.landlords.show', $o['id'], false) }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View profile</a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No landlords linked.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500">Commission rate used: {{ number_format((float) ($commissionPct ?? 0), 2) }}%</p>
        </div>
        @endif
    </div>
    @endif

    @if ($activeTab === 'utilities')
        <div class="mt-5">
        @include('property.agent.properties.partials.utility_charge_templates_editor')
        </div>
    @endif

    @if ($activeTab === 'deposits')
        <div class="mt-5">
        @include('property.agent.properties.partials.deposit_rules_editor')
        </div>
    @endif

    @if (in_array($activeTab, ['overview', 'occupancy', 'performance'], true))
    <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Occupancy rate</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((float) ($reporting['occupancy_rate'] ?? 0), 1) }}%</p>
            <p class="mt-1 text-xs text-slate-500">Occupied units over total doors</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Collection rate ({{ $periodLabel }})</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((float) ($reporting['collection_rate'] ?? 0), 1) }}%</p>
            <p class="mt-1 text-xs text-slate-500">Collected amount over invoiced amount</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average arrears per unit</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($reporting['avg_arrears_per_unit'] ?? 0)) }}</p>
            <p class="mt-1 text-xs text-slate-500">Across all units in this property</p>
        </div>
    </div>
    @endif

    @if ($activeTab === 'occupancy')
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Occupancy snapshot</h3>
        </div>
        <div class="max-h-[22rem] overflow-auto">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Rent</th>
                </tr>
            </thead>
            <tbody>
                @foreach (($units ?? []) as $unitModel)
                    @php
                        $lease = $unitModel->leases->first();
                        $rowTone = \App\Support\Property\WorkspaceRowAlert::forUnit($unitModel, $lease !== null);
                        $cellStyle = \App\Support\Property\WorkspaceRowAlert::cellStyle($rowTone);
                    @endphp
                    <tr class="border-t border-slate-100 {{ $cellStyle === '' ? 'hover:bg-slate-50/70' : '' }} {{ \App\Support\Property\WorkspaceRowAlert::trClass($rowTone) }}" @if ($cellStyle !== '') data-row-tone="{{ $rowTone }}" @endif>
                        <td class="px-4 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>
                            @if (auth()->user()?->hasPmPermission('properties.manage'))
                                <a href="{{ route('property.units.edit', $unitModel, false) }}" data-turbo="false" class="font-medium text-indigo-600 hover:text-indigo-700">{{ $unitModel->label }}</a>
                            @else
                                <span class="font-medium text-slate-900">{{ $unitModel->label }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 capitalize" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $unitModel->status }}</td>
                        <td class="px-4 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $lease?->pmTenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ \App\Services\Property\PropertyMoney::kes($unitModel->listedRentAmount()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

    @if ($activeTab === 'maintenance')
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900">Maintenance requests</h3>
            <div class="flex flex-wrap gap-2">
                @if (auth()->user()?->hasPmPermission('properties.manage') && ! $property->isManagementReadOnly())
                    <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" data-property-modal-open="showHubMaintenanceForm" @click="showHubMaintenanceForm = true">New request</button>
                @endif
                <a href="{{ route('property.maintenance.requests', ['property_id' => $property->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-slate-700 hover:underline self-center">Open maintenance workspace</a>
            </div>
        </div>
        <div class="max-h-[22rem] overflow-auto">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Urgency</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($maintenanceRequests ?? []) as $requestItem)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3">{{ $requestItem->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $requestItem->unit?->label ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $requestItem->pmTenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($requestItem->category ?? 'general')) }}</td>
                        <td class="px-4 py-3 capitalize">{{ $requestItem->urgency ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ $requestItem->status ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No maintenance requests for this property.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endif

    @if ($activeTab === 'units')
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Unit status &amp; arrears</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ count($unitSnapshots ?? []) }} of {{ count($units ?? []) }} units shown</p>
                </div>
            </div>
            <form method="get" action="{{ route('property.properties.show', ['property' => $property->id]) }}" data-turbo-frame="property-main" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="tab" value="units" />
                <input type="hidden" name="month" value="{{ $monthValue ?? '' }}" />
                <input type="hidden" name="fy" value="{{ $fyValue ?? now()->year }}" />
                <div>
                    <label class="block text-xs font-medium text-slate-600">Status</label>
                    <select name="unit_status" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-w-[8rem]">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\PropertyUnit::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['unit_status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Arrears</label>
                    <select name="unit_arrears" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-w-[8rem]">
                        <option value="">All</option>
                        <option value="has_arrears" @selected(($filters['unit_arrears'] ?? '') === 'has_arrears')>Has arrears</option>
                        <option value="no_arrears" @selected(($filters['unit_arrears'] ?? '') === 'no_arrears')>No arrears</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Search</label>
                    <input type="search" name="unit_q" value="{{ $filters['unit_q'] ?? '' }}" placeholder="Unit, tenant, phone…" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 w-44" />
                </div>
                <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Filter</button>
                <a href="{{ route('property.properties.show', ['property' => $property->id, 'tab' => 'units', 'month' => $monthValue ?? null, 'fy' => $fyValue ?? null], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            </form>
        </div>
        @include('property.agent.partials.property_unit_snapshots_table', [
            'property' => $property,
            'units' => $units,
            'unitSnapshots' => $unitSnapshots,
        ])

        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            @php
                $unitFieldCfg = $unitFields ?? [];
                $unitEnabled = fn (string $k, bool $d = true) => (bool) (($unitFieldCfg[$k]['enabled'] ?? $d));
                $unitRequired = fn (string $k, bool $d = false) => (bool) (($unitFieldCfg[$k]['required'] ?? $d) && $unitEnabled($k, $d));
            @endphp
            <x-property.modal show="addUnitOpen" close="addUnitOpen = false" name="add-unit" max-width="3xl">
                <x-slot name="header">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-900">Add unit to {{ $property->name }}</h3>
                        <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="addUnitOpen = false">Close</button>
                    </div>
                </x-slot>
                    <form method="post" action="{{ route('property.units.store', absolute: false) }}" class="space-y-4" data-turbo="false">
                        @csrf
                        <input type="hidden" name="property_id" value="{{ $property->id }}" />
                        <input type="hidden" name="return_to" value="property_show" />
                        <input type="hidden" name="return_property_id" value="{{ $property->id }}" />
                        <input type="hidden" name="return_tab" value="units" />
                        @if (! empty($monthValue))
                            <input type="hidden" name="return_month" value="{{ $monthValue }}" />
                        @endif
                        @if (! empty($fyValue))
                            <input type="hidden" name="return_fy" value="{{ $fyValue }}" />
                        @endif
                        <input type="hidden" name="unit_count" value="1" />
                        <input type="hidden" name="status_mode" value="single" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Unit label</label>
                                <input type="text" name="label" value="{{ old('label') }}" required placeholder="e.g. A-12" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                                @error('label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            @if ($unitEnabled('unit_type', true))
                                <div>
                                <div class="flex items-center justify-between gap-2">
                                    <label class="block text-xs font-medium text-slate-600">Type</label>
                                    <button type="button" id="show-add-unit-type" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add type</button>
                                </div>
                                <select id="show-unit-type" name="unit_type" @required($unitRequired('unit_type', true)) class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                                    <option value="">Select unit type...</option>
                                    @foreach (($unitTypes ?? \App\Models\PropertyUnit::typeOptions()) as $key => $label)
                                        <option value="{{ $key }}" @selected(old('unit_type', \App\Models\PropertyUnit::TYPE_APARTMENT) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('unit_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            @if ($unitEnabled('bedrooms', true))
                                <div id="show-bedrooms-wrapper">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="block text-xs font-medium text-slate-600">Bedrooms / room setup</label>
                                    <button type="button" id="show-add-bedroom-count" class="text-xs font-medium text-blue-700 hover:text-blue-800">+ Add bedrooms</button>
                                </div>
                                <select id="show-bedrooms" name="bedrooms" @required($unitRequired('bedrooms')) class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                                    <option value="">Select bedroom setup...</option>
                                </select>
                                @error('bedrooms')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            @if ($unitEnabled('rent_amount', true))
                                <div>
                                <label class="block text-xs font-medium text-slate-600">Rent amount</label>
                                <input type="number" name="rent_amount" value="{{ old('rent_amount', 0) }}" min="0" step="0.01" @required($unitRequired('rent_amount', true)) class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                                @error('rent_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            @if ($unitEnabled('status', true))
                                <div>
                                <label class="block text-xs font-medium text-slate-600">Status</label>
                                <select name="status" @required($unitRequired('status', true)) class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                                    @foreach (\App\Models\PropertyUnit::statusOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'vacant') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            <div class="sm:col-span-2 lg:col-span-4">
                                <label class="block text-xs font-medium text-slate-600">Public listing description (optional)</label>
                                <textarea name="public_listing_description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" placeholder="Describe highlights seen on the public property page.">{{ old('public_listing_description') }}</textarea>
                                @error('public_listing_description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs text-slate-500">Use this for single unit add. For bulk/mixed adds, use the Units page.</p>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save unit</button>
                        </div>
                    </form>
            </x-property.modal>
        @endif
    </div>
    <script>
        (function () {
            const unitType = document.getElementById('show-unit-type');
            const bedrooms = document.getElementById('show-bedrooms');
            const bedroomsWrapper = document.getElementById('show-bedrooms-wrapper');
            const addTypeButton = document.getElementById('show-add-unit-type');
            const addBedroomsButton = document.getElementById('show-add-bedroom-count');
            if (!unitType || !bedrooms || !bedroomsWrapper) return;

            const noBedroomTypes = new Set(['single_room', 'bedsitter', 'studio']);
            const bedroomOptionsByType = @json($bedroomOptionsByType ?? []);
            const oldBedrooms = @json((string) old('bedrooms', ''));
            const storageKey = 'property_unit_meta_options_v1';

            const slugify = (text) => String(text || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');

            const readStoredMeta = () => {
                try {
                    const raw = window.localStorage ? window.localStorage.getItem(storageKey) : null;
                    const parsed = raw ? JSON.parse(raw) : null;
                    const types = Array.isArray(parsed?.types) ? parsed.types : [];
                    const bedroomsByType = parsed?.bedroomsByType && typeof parsed.bedroomsByType === 'object'
                        ? parsed.bedroomsByType
                        : {};
                    return { types, bedroomsByType };
                } catch (_) {
                    return { types: [], bedroomsByType: {} };
                }
            };

            const writeStoredMeta = (payload) => {
                try {
                    if (!window.localStorage) return;
                    window.localStorage.setItem(storageKey, JSON.stringify(payload));
                } catch (_) {
                    // Ignore storage write failures.
                }
            };

            const bedroomText = (count) => count === 0 ? 'No separate bedroom' : `${count} ${count === 1 ? 'bedroom' : 'bedrooms'}`;

            const ensureOption = (select, value, label) => {
                if (!select || !value) return;
                if ([...select.options].some((option) => option.value === String(value))) return;
                const option = document.createElement('option');
                option.value = String(value);
                option.textContent = String(label || value);
                select.appendChild(option);
            };

            const renderBedroomsOptions = (typeValue, selectedValue = '') => {
                const normalizedType = String(typeValue || '').trim();
                const currentSelected = String(selectedValue ?? '');
                bedrooms.innerHTML = '<option value="">Select bedroom setup...</option>';
                const options = bedroomOptionsByType[normalizedType] || {};
                Object.keys(options)
                    .map((value) => Number.parseInt(String(value), 10))
                    .filter((value) => Number.isFinite(value))
                    .sort((a, b) => a - b)
                    .forEach((value) => {
                        const option = document.createElement('option');
                        option.value = String(value);
                        option.textContent = options[String(value)] || bedroomText(value);
                        bedrooms.appendChild(option);
                    });
                if (currentSelected !== '' && [...bedrooms.options].some((o) => o.value === currentSelected)) {
                    bedrooms.value = currentSelected;
                }
            };

            const applyStoredOptions = () => {
                const state = readStoredMeta();
                state.types.forEach((entry) => {
                    const value = String(entry?.value || '');
                    const label = String(entry?.label || '').trim();
                    if (value !== '' && label !== '') {
                        ensureOption(unitType, value, label);
                    }
                });
                Object.entries(state.bedroomsByType || {}).forEach(([typeValue, counts]) => {
                    if (!Array.isArray(counts)) return;
                    bedroomOptionsByType[typeValue] = bedroomOptionsByType[typeValue] || {};
                    counts.forEach((count) => {
                        const numeric = Number.parseInt(String(count), 10);
                        if (!Number.isFinite(numeric) || numeric < 0 || numeric > 20) return;
                        bedroomOptionsByType[typeValue][numeric] = bedroomText(numeric);
                    });
                });
            };

            const syncBedrooms = () => {
                const requiresNoBedroom = noBedroomTypes.has(unitType.value);
                if (requiresNoBedroom) {
                    bedrooms.value = '0';
                    bedrooms.disabled = true;
                    bedrooms.required = false;
                    bedroomsWrapper.classList.add('hidden');
                } else {
                    renderBedroomsOptions(unitType.value, bedrooms.value || oldBedrooms);
                    bedrooms.disabled = false;
                    bedrooms.required = true;
                    bedroomsWrapper.classList.remove('hidden');
                }
            };

            const showErrorMessage = async (message) => {
                await window.swalAlert(message, { icon: 'warning', title: '', confirmButtonText: 'OK' });
            };

            const askTextValue = async (title, inputLabel, placeholder = '') => {
                const result = await window.Swal.fire({
                    title,
                    input: 'text',
                    inputLabel,
                    inputPlaceholder: placeholder,
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => {
                        if (!String(value || '').trim()) {
                            return 'This field is required.';
                        }
                        return null;
                    },
                });
                if (!result.isConfirmed) {
                    return null;
                }
                return String(result.value || '').trim();
            };

            const askBedroomCount = async (unitType) => {
                const result = await window.Swal.fire({
                    title: 'Add bedroom count',
                    input: 'number',
                    inputLabel: `Bedrooms for "${unitType}" (0-20)`,
                    inputAttributes: { min: '0', max: '20', step: '1' },
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: 'Cancel',
                    inputValidator: (value) => {
                        const parsed = Number.parseInt(String(value), 10);
                        if (!Number.isFinite(parsed) || parsed < 0 || parsed > 20) {
                            return 'Enter a whole number between 0 and 20.';
                        }
                        return null;
                    },
                });
                if (!result.isConfirmed) {
                    return null;
                }
                return Number.parseInt(String(result.value), 10);
            };

            applyStoredOptions();
            unitType.addEventListener('change', syncBedrooms);
            syncBedrooms();

            addTypeButton?.addEventListener('click', async () => {
                const label = await askTextValue(
                    'Add unit type',
                    'Unit type label',
                    'e.g. Penthouse Duplex'
                );
                if (!label) return;
                const normalized = slugify(label);
                if (!normalized) return;

                ensureOption(unitType, normalized, label.trim());
                unitType.value = normalized;
                const state = readStoredMeta();
                if (!state.types.some((entry) => String(entry?.value) === normalized)) {
                    state.types.push({ value: normalized, label: label.trim() });
                    writeStoredMeta(state);
                }
                syncBedrooms();
            });

            addBedroomsButton?.addEventListener('click', async () => {
                const selectedType = String(unitType.value || '').trim();
                if (!selectedType) {
                    await showErrorMessage('Select a unit type first.');
                    return;
                }

                const value = await askBedroomCount(selectedType);
                if (value === null) return;
                if (!Number.isFinite(value) || value < 0 || value > 20) {
                    await showErrorMessage('Bedrooms count must be between 0 and 20.');
                    return;
                }

                bedroomOptionsByType[selectedType] = bedroomOptionsByType[selectedType] || {};
                bedroomOptionsByType[selectedType][value] = bedroomText(value);

                const state = readStoredMeta();
                const list = Array.isArray(state.bedroomsByType?.[selectedType]) ? state.bedroomsByType[selectedType] : [];
                if (!list.includes(value)) {
                    list.push(value);
                    list.sort((a, b) => a - b);
                }
                state.bedroomsByType[selectedType] = list;
                writeStoredMeta(state);

                syncBedrooms();
                bedrooms.value = String(value);
            });
        })();
    </script>
    @endif

    @if ($activeTab === 'revenue')
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Recent collections ({{ $periodLabel }})</h3>
        </div>
        <div class="max-h-[22rem] overflow-auto">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Channel</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentCollections as $c)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $c->paid_at ? \Illuminate\Support\Carbon::parse((string) $c->paid_at)->format('Y-m-d H:i') : '—' }}</td>
                        <td class="px-4 py-3">{{ $c->tenant_name ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ $c->channel ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $c->external_ref ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($c->amount ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No collections in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Collection channel report ({{ $periodLabel }})</h3>
        </div>
        <div class="max-h-[22rem] overflow-auto">
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Channel</th>
                    <th class="px-4 py-3">Transactions</th>
                    <th class="px-4 py-3">Total collected</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($collectionByChannel ?? []) as $row)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 uppercase">{{ $row->channel !== '' ? $row->channel : 'Unspecified' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ (int) ($row->tx_count ?? 0) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row->total_amount ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">No collection channel report data for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endif

    @if ($activeTab === 'statements')
        @include('property.agent.properties.partials.tab-statements')
    @endif

    @if ($activeTab === 'offboarding')
        <div class="mt-5">
            @include('property.agent.properties.partials.offboarding_panel')
        </div>
    @endif
    </div>

</x-property.workspace>

