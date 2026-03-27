<x-property.workspace
    title="Occupancy view"
    subtitle="Structural only — vacant vs occupied vs notice, with active tenant when leased."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :show-search="false"
    empty-title="No units"
    empty-hint="Add properties and units to see occupancy across the portfolio."
>
    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('property.properties.units', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Manage units</a>
                <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Assign tenants</a>
                <a href="{{ route('property.listings.vacant', absolute: false) }}" data-turbo-frame="property-main" class="rounded-lg border border-blue-300 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">Vacant listings</a>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('property.properties.occupancy', array_merge(request()->query(), ['status' => 'vacant', 'preset' => 'vacant']), false) }}" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Vacant only</a>
                <a href="{{ route('property.properties.occupancy', array_merge(request()->query(), ['status' => 'notice', 'preset' => 'notice']), false) }}" class="rounded-lg border border-orange-300 px-3 py-1.5 text-xs font-medium text-orange-700 hover:bg-orange-50">Notice only</a>
                <a href="{{ route('property.properties.occupancy', array_merge(request()->query(), ['status' => 'vacant', 'age_bucket' => '90_plus', 'preset' => 'long_vacant']), false) }}" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Long vacant 90+ days</a>
                <a href="{{ route('property.properties.occupancy', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Clear presets</a>
            </div>
        </div>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.properties.occupancy') }}" class="w-full grid gap-2 sm:grid-cols-2 lg:grid-cols-7">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search unit or property..." class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 lg:col-span-2" />
            <select name="property_id" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">All properties</option>
                @foreach(($propertyOptions ?? []) as $p)
                    <option value="{{ $p->id }}" @selected((string) ($filters['property_id'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">All statuses</option>
                <option value="occupied" @selected(($filters['status'] ?? '') === 'occupied')>Occupied</option>
                <option value="vacant" @selected(($filters['status'] ?? '') === 'vacant')>Vacant</option>
                <option value="notice" @selected(($filters['status'] ?? '') === 'notice')>Notice</option>
            </select>
            <select name="age_bucket" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="">Vacancy age: All</option>
                <option value="0_30" @selected(($filters['age_bucket'] ?? '') === '0_30')>0-30 days</option>
                <option value="31_60" @selected(($filters['age_bucket'] ?? '') === '31_60')>31-60 days</option>
                <option value="61_90" @selected(($filters['age_bucket'] ?? '') === '61_90')>61-90 days</option>
                <option value="90_plus" @selected(($filters['age_bucket'] ?? '') === '90_plus')>90+ days</option>
            </select>
            <input type="hidden" name="preset" value="{{ $filters['preset'] ?? '' }}" />
            <div class="flex items-center gap-2 lg:col-span-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
                <a href="{{ route('property.properties.occupancy', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
                <a href="{{ route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'csv']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">CSV</a>
                <a href="{{ route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'pdf']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">PDF</a>
                <a href="{{ route('property.properties.occupancy', array_merge(request()->query(), ['export' => 'word']), false) }}" data-turbo="false" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Word</a>
            </div>
        </form>

        <form id="occupancy-bulk-form" method="post" action="{{ route('property.properties.occupancy.bulk', absolute: false) }}" class="w-full mt-2 flex flex-wrap items-center gap-2">
            @csrf
            <select name="bulk_action" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2">
                <option value="mark_vacant">Bulk: Mark vacant</option>
                <option value="mark_occupied">Bulk: Mark occupied</option>
                <option value="mark_notice">Bulk: Mark notice</option>
                <option value="open_assign">Bulk: Open assign tenant</option>
                <option value="open_publish">Bulk: Open publish listing</option>
                <option value="open_property">Bulk: Open property</option>
            </select>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Run on selected units</button>
            <span class="text-xs text-slate-500">Tick units in the table first, then run bulk action.</span>
        </form>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Vacancy aging buckets</h3>
                <p class="mt-1 text-xs text-slate-500">Prioritize long-vacant units and rent exposure.</p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Bucket</th>
                        <th class="px-4 py-3">Units</th>
                        <th class="px-4 py-3">Rent exposure / month</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($vacancyAging ?? []) as $bucketKey => $bucket)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <a href="{{ route('property.properties.occupancy', array_merge(request()->query(), ['status' => 'vacant', 'age_bucket' => $bucketKey]), false) }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
                                    {{ $bucket['label'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ (int) ($bucket['count'] ?? 0) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($bucket['rent'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-200 bg-slate-50/70">
                        <td class="px-4 py-3 font-semibold text-slate-900">Total</td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 tabular-nums font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($vacantRentExposure ?? 0)) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Occupancy activity trend (6 months)</h3>
                <p class="mt-1 text-xs text-slate-500">Move-ins vs move-outs from recorded unit movements.</p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3">Move-ins</th>
                        <th class="px-4 py-3">Move-outs</th>
                        <th class="px-4 py-3">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($activityTrend ?? []) as $m)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">{{ $m['label'] }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ (int) $m['move_in'] }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ (int) $m['move_out'] }}</td>
                            <td class="px-4 py-3 tabular-nums {{ ((int) $m['move_in'] - (int) $m['move_out']) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ ((int) $m['move_in'] - (int) $m['move_out']) >= 0 ? '+' : '' }}{{ (int) $m['move_in'] - (int) $m['move_out'] }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No move activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing {{ (int) (($unitsPage ?? null)?->firstItem() ?? 0) }}-{{ (int) (($unitsPage ?? null)?->lastItem() ?? 0) }}
                of {{ (int) (($unitsPage ?? null)?->total() ?? 0) }} units.
            </p>
            <div>
                {{ ($unitsPage ?? null)?->links() }}
            </div>
        </div>
    </x-slot>
</x-property.workspace>
