<x-property.workspace
    :title="'Property: '.$property->name"
    :subtitle="'Full property intelligence view · '.$periodLabel"
    back-route="property.properties.list"
    :stats="$stats"
    :columns="[]"
>
    @php
        $firstVacantUnit = collect($units ?? [])->firstWhere('status', \App\Models\PropertyUnit::STATUS_VACANT);
    @endphp

    <x-slot name="actions">
        <a href="{{ route('property.properties.edit', ['property' => $property->id], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit property</a>
        <a href="{{ route('property.properties.units', ['property_id' => $property->id], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Units</a>
        @if (count($units ?? []) === 0)
            <a href="{{ route('property.properties.units', ['property_id' => $property->id], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Add units</a>
        @endif
        <a href="{{ route('property.tenants.leases', ['property_id' => $property->id], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Add lease</a>
        @if ($firstVacantUnit)
            <a href="{{ route('property.tenants.leases', ['property_id' => $property->id, 'unit_id' => $firstVacantUnit->id], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Assign tenant</a>
            <a href="{{ route('property.listings.vacant.public.edit', ['property_unit' => $firstVacantUnit->id], false) }}" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">Publish vacant unit</a>
        @endif
        @if (($property->landlords?->count() ?? 0) === 0)
            <a href="{{ route('property.properties.list', ['property_id' => $property->id], false) }}#link-landlord-form" data-turbo-frame="_top" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Link landlord</a>
        @endif
    </x-slot>

    <x-slot name="above">
        <form method="get" action="{{ route('property.properties.show', ['property' => $property->id]) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600">Month</label>
                <input type="month" name="month" value="{{ $monthValue ?? '' }}" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">FY</label>
                <input type="number" name="fy" value="{{ $fyValue ?? now()->year }}" min="2000" max="2100" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 w-28" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Unit status</label>
                <select name="unit_status" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="">All</option>
                    <option value="vacant" @selected(($filters['unit_status'] ?? '') === 'vacant')>Vacant</option>
                    <option value="occupied" @selected(($filters['unit_status'] ?? '') === 'occupied')>Occupied</option>
                    <option value="notice" @selected(($filters['unit_status'] ?? '') === 'notice')>Notice</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Collection channel</label>
                <select name="collection_channel" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="">All</option>
                    @foreach(($availableCollectionChannels ?? []) as $channel)
                        <option value="{{ $channel }}" @selected(($filters['collection_channel'] ?? '') === $channel)>{{ strtoupper($channel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Collection search</label>
                <input type="text" name="collection_q" value="{{ $filters['collection_q'] ?? '' }}" placeholder="Tenant or reference" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Export report</label>
                <select name="export_report" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                    <option value="full" @selected(($filters['export_report'] ?? 'full') === 'full')>Full intelligence</option>
                    <option value="units" @selected(($filters['export_report'] ?? '') === 'units')>Units report</option>
                    <option value="collections" @selected(($filters['export_report'] ?? '') === 'collections')>Collections report</option>
                    <option value="channels" @selected(($filters['export_report'] ?? '') === 'channels')>Channel report</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply period</button>
            <a href="{{ route('property.properties.show', ['property' => $property->id], false) }}" data-turbo-frame="_top" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
            <a href="{{ route('property.properties.show', array_merge(['property' => $property->id], request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Export CSV</a>
            <a href="{{ route('property.properties.show', array_merge(['property' => $property->id], request()->query(), ['export' => 'pdf']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Export PDF</a>
            <a href="{{ route('property.properties.show', array_merge(['property' => $property->id], request()->query(), ['export' => 'word']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Export Word</a>
        </form>
    </x-slot>

    <div x-data="{ addUnitOpen: false }">
    @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
        <div class="mt-1 mb-4 rounded-xl border border-slate-200 bg-white p-3 shadow-sm flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-slate-700">
                <span class="font-semibold">Units:</span> {{ count($units ?? []) }}
                <span class="text-slate-500">· Manage additions/demolitions from here.</span>
            </p>
            <button
                type="button"
                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
                @click="addUnitOpen = true"
            >
                + Add unit
            </button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Property profile</h3>
            <div class="mt-2 text-sm text-slate-700 space-y-1">
                <p><span class="text-slate-500">Name:</span> {{ $property->name }}</p>
                <p><span class="text-slate-500">Code:</span> {{ $property->code ?: '—' }}</p>
                <p><span class="text-slate-500">City:</span> {{ $property->city ?: '—' }}</p>
                <p><span class="text-slate-500">Address:</span> {{ $property->address_line ?: '—' }}</p>
                <p>
                    <span class="text-slate-500">Linked landlord{{ count($ownerRows) === 1 ? '' : 's' }}:</span>
                    @if (count($ownerRows) > 0)
                        {{ collect($ownerRows)->pluck('name')->filter()->implode(', ') }}
                    @else
                        <span class="text-amber-700">None linked</span>
                    @endif
                </p>
                <p><span class="text-slate-500">Active leases:</span> {{ (int) ($activeLeasesCount ?? 0) }} ({{ \App\Services\Property\PropertyMoney::kes((float) ($activeLeaseRent ?? 0)) }} / month)</p>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Landlord ownership & earnings</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Landlord</th>
                            <th class="px-3 py-2">Share %</th>
                            <th class="px-3 py-2">Collected share</th>
                            <th class="px-3 py-2">Arrears share</th>
                            <th class="px-3 py-2">Your earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ownerRows as $o)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">{{ $o['name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $o['email'] }}</div>
                                </td>
                                <td class="px-3 py-2 tabular-nums">{{ number_format((float) $o['ownership_percent'], 2) }}%</td>
                                <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $o['share_collected']) }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $o['share_arrears']) }}</td>
                                <td class="px-3 py-2 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $o['agent_earning_portion']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No landlords linked.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500">Commission rate used: {{ number_format((float) ($commissionPct ?? 0), 2) }}%</p>
        </div>
    </div>

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

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Unit status & arrears</h3>
                @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
                        @click="addUnitOpen = true"
                    >
                        Add unit
                    </button>
                @endif
            </div>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Listed rent</th>
                    <th class="px-4 py-3">Arrears</th>
                    @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
                        <th class="px-4 py-3">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($unitSnapshots as $u)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $u->label }}</td>
                        <td class="px-4 py-3 capitalize text-slate-700">{{ $u->status }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $u->arrears) }}</td>
                        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
                            <td class="px-4 py-3">
                                <form
                                    method="post"
                                    action="{{ route('property.units.destroy', ['unit' => $u->id], false) }}"
                                    class="inline-flex"
                                    data-swal-title="Remove this unit?"
                                    data-swal-confirm="Use this for demolished/invalid units. Deletion is blocked if the unit has lease, invoice, utility, or maintenance history."
                                    data-swal-confirm-text="Yes, remove unit"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Remove</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->check() && auth()->user()?->hasPmPermission('properties.manage') ? 5 : 4 }}" class="px-4 py-10 text-center text-slate-500">No units yet for this property.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            <div
                x-show="addUnitOpen"
                x-cloak
                @keydown.escape.window="addUnitOpen = false"
                class="fixed inset-0 z-[7000] flex items-center justify-center bg-slate-900/50 p-4"
            >
                <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200" @click.outside="addUnitOpen = false">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold text-slate-900">Add unit to {{ $property->name }}</h3>
                        <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="addUnitOpen = false">Close</button>
                    </div>
                    <form method="post" action="{{ route('property.units.store', absolute: false) }}" class="p-5 space-y-4" data-turbo="false">
                        @csrf
                        <input type="hidden" name="property_id" value="{{ $property->id }}" />
                        <input type="hidden" name="unit_count" value="1" />
                        <input type="hidden" name="status_mode" value="single" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Unit label</label>
                                <input type="text" name="label" required placeholder="e.g. A-12" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Type</label>
                                <select name="unit_type" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                                    @foreach(\App\Models\PropertyUnit::typeOptions() as $key => $label)
                                        <option value="{{ $key }}" @selected($key === \App\Models\PropertyUnit::TYPE_APARTMENT)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Bedrooms</label>
                                <input type="number" name="bedrooms" value="1" min="0" max="20" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Rent amount</label>
                                <input type="number" name="rent_amount" value="0" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Status</label>
                                <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                                    <option value="vacant">Vacant</option>
                                    <option value="occupied">Occupied</option>
                                    <option value="notice">Notice</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs text-slate-500">Use this when new units are added. For bulk additions, use the main Units page.</p>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save unit</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Recent collections ({{ $periodLabel }})</h3>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
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

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Collection channel report ({{ $periodLabel }})</h3>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
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
</x-property.workspace>

