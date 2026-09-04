@php
    $unitColumns = ['Property', 'Unit', 'Status', 'Tenant', 'Monthly rent', 'Lease end', 'Actions'];
@endphp

<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">All units in portfolio</h3>
        <p class="text-xs text-slate-500 mt-0.5">{{ count($unitRows ?? []) }} units across linked properties</p>
    </div>
    @if (($unitRows ?? []) === [])
        <p class="p-6 text-sm text-slate-500">No units — link this landlord to properties first.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        @foreach ($unitColumns as $col)
                            <th class="px-4 py-3 font-semibold">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($unitRows ?? [] as $row)
                        @php $tone = $row['row_tone'] ?? ''; @endphp
                        <tr class="{{ \App\Support\Property\WorkspaceRowAlert::trClass($tone) }}">
                            <td class="px-4 py-3">{{ $row['property_name'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('property.properties.show', ['property' => $row['property_id'], 'tab' => 'units'], false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-700 hover:underline">{{ $row['unit_label'] ?? '—' }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $row['status_label'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['tenant_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ $row['monthly_rent'] !== null ? \App\Services\Property\PropertyMoney::kes((float) $row['monthly_rent']) : '—' }}</td>
                            <td class="px-4 py-3">{{ ! empty($row['lease_end']) ? \Illuminate\Support\Carbon::parse($row['lease_end'])->format('Y-m-d') : '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('property.units.edit', ['unit' => $row['unit_id']], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-700">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
