<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PmUnitMovement;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PmLeaseWebController extends Controller
{
    private function ensureMovementLogged(PmLease $lease, array $unitIds, string $movementType, ?string $date): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        if ($unitIds === [] || !$date) {
            return;
        }

        $tenantName = $lease->pmTenant?->name ?? '—';
        $needle = 'Lease #'.$lease->id;
        $notes = 'Auto: '.$needle.' (Tenant: '.$tenantName.')';

        foreach ($unitIds as $unitId) {
            $exists = PmUnitMovement::query()
                ->where('property_unit_id', $unitId)
                ->where('movement_type', $movementType)
                ->where('notes', 'like', '%'.$needle.'%')
                ->exists();
            if ($exists) {
                continue;
            }

            PmUnitMovement::query()->create([
                'property_unit_id' => $unitId,
                'movement_type' => $movementType,
                'status' => 'done',
                'scheduled_on' => $date,
                'completed_on' => $date,
                'notes' => $notes,
                'user_id' => null,
            ]);
        }
    }

    private function vacateUnitsIfNotInAnotherActiveLease(array $unitIds, ?int $excludeLeaseId = null): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        if ($unitIds === []) {
            return;
        }

        // Only vacate units that are NOT linked to any other active lease.
        $stillOccupiedUnitIds = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->when($excludeLeaseId !== null, fn ($q) => $q->where('id', '!=', $excludeLeaseId))
            ->whereHas('units', fn ($q) => $q->whereIn('property_units.id', $unitIds))
            ->with('units:id')
            ->get()
            ->flatMap(fn (PmLease $l) => $l->units->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $toVacate = array_values(array_diff($unitIds, $stillOccupiedUnitIds));
        if ($toVacate === []) {
            return;
        }

        PropertyUnit::query()->whereIn('id', $toVacate)->update([
            'status' => PropertyUnit::STATUS_VACANT,
            'vacant_since' => now()->toDateString(),
        ]);
    }

    public function leases(): View
    {
        $leaseTemplate = PropertyPortalSetting::getValue('template_lease_text', '');
        $leases = PmLease::query()->with(['pmTenant', 'units.property'])->orderByDesc('start_date')->get();

        $stats = [
            ['label' => 'All leases', 'value' => (string) $leases->count(), 'hint' => ''],
            ['label' => 'Active', 'value' => (string) $leases->where('status', PmLease::STATUS_ACTIVE)->count(), 'hint' => ''],
            ['label' => 'Ending ≤60d', 'value' => (string) $leases->filter(fn (PmLease $l) => $l->status === PmLease::STATUS_ACTIVE && $l->end_date->isFuture() && $l->end_date->lte(now()->addDays(60)))->count(), 'hint' => ''],
            ['label' => 'Draft', 'value' => (string) $leases->where('status', PmLease::STATUS_DRAFT)->count(), 'hint' => ''],
        ];

        $rows = $leases->map(function (PmLease $l) {
            $units = $l->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ');
            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-2">'.
                '<a href="'.route('property.leases.show', $l).'" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>'.
                '<span class="text-slate-300">|</span>'.
                '<a href="'.route('property.leases.edit', $l).'" class="text-indigo-600 hover:text-indigo-700 font-medium">Edit</a>'.
                '</div>'
            );


            
            return [
                '#'.$l->id,
                $l->pmTenant->name,
                $units !== '' ? $units : '—',
                $l->start_date->format('Y-m-d'),
                $l->end_date->format('Y-m-d'),
                number_format((float) $l->monthly_rent, 2),
                number_format((float) $l->deposit_amount, 2),
                ucfirst($l->status),
                $actions,
            ];
        })->all();

        return view('property.agent.tenants.leases', [
            'stats' => $stats,
            'columns' => ['Lease #', 'Tenant', 'Unit(s)', 'Start', 'End', 'Rent', 'Deposit held', 'Status', 'Actions'],
            'tableRows' => $rows,
            'tenants' => PmTenant::query()->orderBy('name')->get(),
            'vacantUnits' => PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->with('property')->orderBy('property_id')->get(),
            'leaseTemplate' => $leaseTemplate,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => ['nullable', 'array'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
        ]);

        DB::transaction(function () use ($data) {
            $lease = PmLease::query()->create([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ]);

            $lease->load('pmTenant');
            $unitIds = $data['property_unit_ids'] ?? [];
            if ($unitIds !== []) {
                $lease->units()->sync($unitIds);
                if ($data['status'] === PmLease::STATUS_ACTIVE) {
                    PropertyUnit::query()->whereIn('id', $unitIds)->update([
                        'status' => PropertyUnit::STATUS_OCCUPIED,
                        'vacant_since' => null,
                    ]);
                    $this->ensureMovementLogged($lease, $unitIds, 'move_in', $data['start_date'] ?? null);
                } elseif (in_array($data['status'], [PmLease::STATUS_EXPIRED, PmLease::STATUS_TERMINATED], true)) {
                    $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
                    $this->ensureMovementLogged($lease, $unitIds, 'move_out', $data['end_date'] ?? null);
                }
            }
        });

        return back()->with('success', 'Lease saved.');
    }

    public function show(PmLease $lease): View
    {
        $lease->load([
            'pmTenant',
            'units.property',
        ]);

        $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');
        $daysLeft = $lease->end_date->isBefore(today()) ? 0 : (int) today()->diffInDays($lease->end_date);
        $isEndingSoon = $lease->status === PmLease::STATUS_ACTIVE && $lease->end_date->lte(now()->addDays(60));

        return view('property.agent.tenants.lease_show', [
            'lease' => $lease,
            'unitsLabel' => $units !== '' ? $units : '—',
            'daysLeft' => $daysLeft,
            'isEndingSoon' => $isEndingSoon,
        ]);
    }

    public function edit(PmLease $lease): View
    {
        $lease->load(['pmTenant', 'units.property']);

        return view('property.agent.tenants.lease_edit', [
            'lease' => $lease,
            'tenants' => PmTenant::query()->orderBy('name')->get(),
            'units' => PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label')->get(),
            'leaseTemplate' => PropertyPortalSetting::getValue('template_lease_text', ''),
        ]);
    }

    public function update(Request $request, PmLease $lease): RedirectResponse
    {
        $lease->load(['units:id', 'pmTenant']);

        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => ['nullable', 'array'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
        ]);

        DB::transaction(function () use ($data, $lease) {
            $prevStatus = $lease->status;
            $prevUnitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();

            $lease->update([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ]);

            $unitIds = $data['property_unit_ids'] ?? [];
            $lease->units()->sync($unitIds);

            // If lease is active, mark linked units occupied.
            if ($data['status'] === PmLease::STATUS_ACTIVE && $unitIds !== []) {
                PropertyUnit::query()->whereIn('id', $unitIds)->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);
                // Log move-in if we just activated or attached units.
                $this->ensureMovementLogged($lease, $unitIds, 'move_in', $data['start_date'] ?? null);
            }

            // If lease is ended, vacate its units (unless another active lease also owns them).
            if (in_array($data['status'], [PmLease::STATUS_EXPIRED, PmLease::STATUS_TERMINATED], true) && $unitIds !== []) {
                $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
                $this->ensureMovementLogged($lease, $unitIds, 'move_out', $data['end_date'] ?? null);
            }

            // If an active lease had units removed, vacate those removed units (unless another active lease owns them).
            if ($prevStatus === PmLease::STATUS_ACTIVE) {
                $removed = array_values(array_diff($prevUnitIds, array_map('intval', $unitIds)));
                if ($removed !== []) {
                    $this->vacateUnitsIfNotInAnotherActiveLease($removed, excludeLeaseId: $lease->id);
                    $this->ensureMovementLogged($lease, $removed, 'move_out', now()->toDateString());
                }
            }
        });

        return back()->with('success', 'Lease updated.');
    }

    public function expiry(): View
    {
        $leases = PmLease::query()
            ->with(['pmTenant', 'units.property.landlords'])
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays(90)->toDateString())
            ->orderBy('end_date')
            ->get();

        $rentAtRisk = (float) $leases
            ->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(90)))
            ->sum('monthly_rent');

        $in30 = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(30)))->count();
        $in60 = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(60)))->count();
        $in90 = $leases->count();

        $mapped = $leases->map(function (PmLease $l) {
            $units = $l->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ') ?: '—';
            $daysLeft = $l->end_date->isBefore(today()) ? 0 : (int) today()->diffInDays($l->end_date);

            $filterParts = [
                mb_strtolower($l->pmTenant->name),
                mb_strtolower($units),
                (string) $daysLeft,
            ];
            if ($daysLeft <= 30) {
                $filterParts[] = 'within30';
            }
            if ($daysLeft <= 60) {
                $filterParts[] = 'within60';
            }
            if ($daysLeft <= 90) {
                $filterParts[] = 'within90';
            }

            return [
                'filter' => implode(' ', $filterParts),
                'cells' => [
                    $l->pmTenant->name,
                    $units,
                    $l->end_date->format('Y-m-d'),
                    (string) max(0, $daysLeft),
                    PropertyMoney::kes((float) $l->monthly_rent),
                    $daysLeft <= 30 ? 'Urgent renewal call' : ($daysLeft <= 60 ? 'Send renewal offer' : 'Monitor'),
                    ucfirst($l->status),
                    new HtmlString(
                        '<a href="'.route('property.tenants.notices').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open notices</a>'
                    ),
                ],
            ];
        });

        $tableRows = $mapped->map(fn (array $r) => $r['cells'])->values()->all();
        $filterTexts = $mapped->map(fn (array $r) => $r['filter'])->values()->all();

        return view('property.agent.tenants.expiry', [
            'stats' => [
                ['label' => 'Expiring ≤30d', 'value' => (string) $in30, 'hint' => 'Urgent'],
                ['label' => 'Expiring ≤60d', 'value' => (string) $in60, 'hint' => 'Outreach'],
                ['label' => 'Expiring ≤90d', 'value' => (string) $in90, 'hint' => 'This list'],
                ['label' => 'Rent at risk (mo)', 'value' => PropertyMoney::kes($rentAtRisk), 'hint' => 'If not renewed'],
            ],
            'columns' => ['Tenant', 'Unit', 'End date', 'Days left', 'Current rent', 'Renewal offer', 'Status', 'Owner'],
            'tableRows' => $tableRows,
            'expiryFilterTexts' => $filterTexts,
        ]);
    }
}
