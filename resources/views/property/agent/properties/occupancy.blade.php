<x-property.workspace
    title="Occupancy view"
    subtitle="Structural only — vacant vs occupied vs notice, with active tenant when leased."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-tones="$tableRowTones ?? []"
    :show-search="false"
    :legacy-toolbar="false"
    empty-title="No units"
    empty-hint="Add properties and units to see occupancy across the portfolio."
>
    <x-slot name="actions">
        <a href="{{ route('property.properties.units', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Manage units</a>
        <a href="{{ route('property.tenants.leases', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Assign tenants</a>
        <a href="{{ route('property.listings.vacant', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex items-center justify-center rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">Vacant listings</a>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.occupancy', get_defined_vars())
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Vacancy aging buckets</h3>
                <p class="mt-1 text-xs text-slate-500">Prioritize long-vacant units and rent exposure.</p>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
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
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
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
