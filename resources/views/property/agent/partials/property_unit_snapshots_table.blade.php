@php
    use App\Models\PropertyUnit;
    use App\Support\Property\UnitListPresentation;
    use App\Support\Property\WorkspaceRowAlert;

    $unitsById = collect($units ?? [])->keyBy('id');
    $snapshots = $unitSnapshots ?? collect();
    $hasManage = auth()->check() && auth()->user()?->hasPmPermission('properties.manage');
    $hasToneRows = $snapshots->isNotEmpty();
@endphp

@if ($hasToneRows)
    <div class="property-row-alert-legend print-hide px-4 py-2 border-b border-slate-100 bg-slate-50/80" aria-label="Row color key">
        <span class="property-row-alert-legend__item">
            <span class="property-row-alert-swatch property-row-alert-swatch--occupied" aria-hidden="true"></span>
            Occupied
        </span>
        <span class="property-row-alert-legend__item">
            <span class="property-row-alert-swatch property-row-alert-swatch--vacant" aria-hidden="true"></span>
            Vacant
        </span>
        <span class="property-row-alert-legend__item">
            <span class="property-row-alert-swatch property-row-alert-swatch--vacant-long" aria-hidden="true"></span>
            Vacant 90+ days
        </span>
        <span class="property-row-alert-legend__item">
            <span class="property-row-alert-swatch property-row-alert-swatch--notice" aria-hidden="true"></span>
            Notice
        </span>
        <span class="property-row-alert-legend__item">
            <span class="property-row-alert-swatch property-row-alert-swatch--attention" aria-hidden="true"></span>
            Needs attention
        </span>
    </div>
@endif

<table class="property-erp-table min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
        <tr>
            <th class="px-4 py-3">Unit</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Tenant</th>
            <th class="px-4 py-3">Phone</th>
            <th class="px-4 py-3">Listed rent</th>
            <th class="px-4 py-3">Arrears</th>
            @if ($hasManage)
                <th class="px-4 py-3">Actions</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse($snapshots as $u)
            @php
                $unitModel = $unitsById->get((int) $u->id);
                $activeLease = $unitModel?->leases->first();
                $hasActiveLease = $activeLease !== null;
                $rowTone = $unitModel
                    ? UnitListPresentation::tone($unitModel, $hasActiveLease)
                    : WorkspaceRowAlert::forSnapshot(
                        (string) ($u->status ?? ''),
                        filled($u->tenant_name ?? null),
                        (float) ($u->arrears ?? 0),
                        $u->vacant_since ?? null,
                    );
                $statusCell = $unitModel
                    ? UnitListPresentation::statusBadge($unitModel, $hasActiveLease)
                    : new \Illuminate\Support\HtmlString(
                        '<span class="property-status-pill property-status-pill--'.e(
                            match ($rowTone) {
                                WorkspaceRowAlert::TONE_ATTENTION => 'attention',
                                WorkspaceRowAlert::TONE_VACANT_LONG => 'vacant-long',
                                WorkspaceRowAlert::TONE_VACANT => 'vacant',
                                WorkspaceRowAlert::TONE_NOTICE => 'notice',
                                WorkspaceRowAlert::TONE_OCCUPIED => 'occupied',
                                default => 'occupied',
                            }
                        ).'">'.e(ucfirst((string) ($u->status ?? '—'))).'</span>'
                    );
                $tenantCell = $unitModel
                    ? UnitListPresentation::tenantCell($unitModel, (string) ($u->tenant_name ?? ''), $hasActiveLease)
                    : ($u->tenant_name ?: '—');
                $listedRent = $unitModel
                    ? $unitModel->listedRentAmount()
                    : (float) ($u->rent_amount ?? 0);
                $cellStyle = WorkspaceRowAlert::cellStyle($rowTone);
            @endphp
            <tr class="border-t border-slate-100 {{ WorkspaceRowAlert::trClass($rowTone) }}" @if ($cellStyle !== '') data-row-tone="{{ $rowTone }}" @endif>
                <td class="px-4 py-3 font-medium text-slate-900" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>
                    @if ($unitModel && auth()->user()?->hasPmPermission('properties.manage'))
                        <a href="{{ route('property.units.edit', $unitModel, false) }}" data-turbo="false" class="text-indigo-600 hover:text-indigo-700">{{ $u->label }}</a>
                    @else
                        {{ $u->label }}
                    @endif
                </td>
                <td class="px-4 py-3" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{!! $statusCell !!}</td>
                <td class="px-4 py-3 text-slate-700" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{!! is_string($tenantCell) ? e($tenantCell) : $tenantCell !!}</td>
                <td class="px-4 py-3 text-slate-600 whitespace-nowrap" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ $u->tenant_phone ?: '—' }}</td>
                <td class="px-4 py-3 tabular-nums" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ \App\Services\Property\PropertyMoney::kes($listedRent) }}</td>
                <td class="px-4 py-3 tabular-nums" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>{{ \App\Services\Property\PropertyMoney::kes((float) $u->arrears) }}</td>
                @if ($hasManage)
                    <td class="px-4 py-3 overflow-visible" @if ($cellStyle !== '') style="{{ $cellStyle }}" @endif>
                        @if ($unitModel)
                            @include('property.agent.partials.unit_row_actions', [
                                'unit' => $unitModel,
                                'arrears' => (float) $u->arrears,
                                'propertyId' => $property->id,
                            ])
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $hasManage ? 7 : 6 }}" class="px-4 py-10 text-center text-slate-500">No units yet for this property.</td>
            </tr>
        @endforelse
    </tbody>
</table>
