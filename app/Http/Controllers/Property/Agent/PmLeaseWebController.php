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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PmLeaseWebController extends Controller
{
    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{label:string,amount:string}>
     */
    private function normalizeAdditionalDeposits(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $amountRaw = $row['amount'] ?? null;

            if ($label === '' && ($amountRaw === null || $amountRaw === '')) {
                continue;
            }
            if ($label === '') {
                continue;
            }

            $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0.0;
            $normalized[] = [
                'label' => $label,
                'amount' => number_format(max(0, $amount), 2, '.', ''),
            ];
        }

        return $normalized;
    }

    private function selectableTenants(?PmLease $lease = null)
    {
        return PmTenant::query()
            ->where(function ($query) use ($lease) {
                $query->whereDoesntHave('leases', function ($leaseQuery) {
                    $leaseQuery->where('status', PmLease::STATUS_ACTIVE);
                });

                if ($lease && $lease->pm_tenant_id) {
                    $query->orWhere('id', $lease->pm_tenant_id);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int,int|string>  $unitIds
     * @return array<int,int>
     */
    private function normalizeSingleUnitSelection(array $unitIds): array
    {
        $normalized = array_values(array_unique(array_map('intval', $unitIds)));

        return array_slice($normalized, 0, 1);
    }

    /**
     * @param array<int,int|string> $unitIds
     *
     * @throws ValidationException
     */
    private function assertActiveLeaseRules(int $tenantId, array $unitIds, ?int $excludeLeaseId = null): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));

        $tenantHasActiveLease = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->where('pm_tenant_id', $tenantId)
            ->when($excludeLeaseId !== null, fn ($q) => $q->where('id', '!=', $excludeLeaseId))
            ->exists();
        if ($tenantHasActiveLease) {
            throw ValidationException::withMessages([
                'pm_tenant_id' => 'This tenant already has an active unit/lease. End the current lease first.',
            ]);
        }

        if ($unitIds === []) {
            return;
        }

        $busyUnits = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->when($excludeLeaseId !== null, fn ($q) => $q->where('id', '!=', $excludeLeaseId))
            ->whereHas('units', fn ($q) => $q->whereIn('property_units.id', $unitIds))
            ->with('units:id,label')
            ->get()
            ->flatMap(fn (PmLease $l) => $l->units->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($busyUnits !== []) {
            throw ValidationException::withMessages([
                'property_unit_ids' => 'One or more selected units are already assigned to an active lease.',
            ]);
        }
    }

    /**
     * Units that are truly available: status vacant and not in any active lease.
     */
    private function trulyVacantUnits()
    {
        return PropertyUnit::query()
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->whereDoesntHave('leases', fn ($q) => $q->where('pm_leases.status', PmLease::STATUS_ACTIVE))
            ->with('property')
            ->orderBy('property_id');
    }

    private function leaseAssignableUnits(?PmLease $lease = null)
    {
        return PropertyUnit::query()
            ->where(function ($q) use ($lease) {
                $q->where(function ($vacant) {
                    $vacant->where('status', PropertyUnit::STATUS_VACANT)
                        ->whereDoesntHave('leases', fn ($lq) => $lq->where('pm_leases.status', PmLease::STATUS_ACTIVE));
                });

                if ($lease) {
                    $selectedIds = $lease->units->pluck('id')->all();
                    if ($selectedIds !== []) {
                        $q->orWhereIn('id', $selectedIds);
                    }
                }
            })
            ->with('property')
            ->orderBy('property_id')
            ->orderBy('label');
    }

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
            ['label' => 'Ending ≤60d', 'value' => (string) $leases->filter(fn (PmLease $l) => $l->status === PmLease::STATUS_ACTIVE && $l->end_date && $l->end_date->isFuture() && $l->end_date->lte(now()->addDays(60)))->count(), 'hint' => ''],
            ['label' => 'Draft', 'value' => (string) $leases->where('status', PmLease::STATUS_DRAFT)->count(), 'hint' => ''],
        ];

        $rows = $leases->map(function (PmLease $l) {
            $units = $l->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ');
            $tenantName = $l->pmTenant?->name ?? '—';
            $expenseType = match ((string) ($l->utility_expense_type ?? '')) {
                'water' => 'Water',
                'electricity' => 'Electricity',
                'other' => 'Other',
                default => '',
            };
            $expenseAmount = (float) ($l->utility_expense_amount ?? 0);
            $expenseLabel = ($expenseType !== '' && $expenseAmount > 0)
                ? $expenseType.' '.PropertyMoney::kes($expenseAmount)
                : '—';
            $actions = new HtmlString(
                '<div class="flex flex-wrap gap-2">'.
                '<a href="'.route('property.leases.show', $l).'" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>'.
                '<span class="text-slate-300">|</span>'.
                '<a href="'.route('property.leases.edit', $l).'" class="text-indigo-600 hover:text-indigo-700 font-medium">Edit</a>'.
                '</div>'
            );


            
            return [
                '#'.$l->id,
                $tenantName,
                $units !== '' ? $units : '—',
                $l->start_date->format('Y-m-d'),
                $l->end_date?->format('Y-m-d') ?? 'Open-ended',
                number_format((float) $l->monthly_rent, 2),
                number_format((float) $l->deposit_amount, 2),
                $expenseLabel,
                ucfirst($l->status),
                $actions,
            ];
        })->all();

        $vacantUnits = $this->leaseAssignableUnits()->get();

        return view('property.agent.tenants.leases', [
            'stats' => $stats,
            'columns' => ['Lease #', 'Tenant', 'Unit(s)', 'Start', 'End', 'Rent', 'Deposit held', 'Expense paid', 'Status', 'Actions'],
            'tableRows' => $rows,
            'tenants' => $this->selectableTenants(),
            'vacantUnits' => $vacantUnits,
            'vacantProperties' => $vacantUnits
                ->pluck('property')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'leaseTemplate' => $leaseTemplate,
            'leaseFields' => $this->leaseFieldConfig(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cfg = $this->leaseFieldConfig();
        $data = $request->validate([
            'pm_tenant_id' => [Rule::requiredIf($this->isFieldRequired($cfg, 'tenant_id')), 'nullable', 'exists:pm_tenants,id'],
            'start_date' => [Rule::requiredIf($this->isFieldRequired($cfg, 'start_date')), 'nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => [Rule::requiredIf($this->isFieldRequired($cfg, 'rent_amount')), 'nullable', 'numeric', 'min:0'],
            'deposit_amount' => [Rule::requiredIf($this->isFieldRequired($cfg, 'deposit_amount')), 'nullable', 'numeric', 'min:0'],
            'utility_expense_type' => ['nullable', 'in:water,electricity,other'],
            'utility_expense_amount' => ['nullable', 'numeric', 'min:0', 'required_with:utility_expense_type'],
            'status' => [Rule::requiredIf($this->isFieldRequired($cfg, 'status')), 'nullable', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => [Rule::requiredIf($this->isFieldRequired($cfg, 'property_unit_id')), 'nullable', 'array', 'max:1'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
            'additional_deposits' => ['nullable', 'array', 'max:20'],
            'additional_deposits.*.label' => ['nullable', 'string', 'max:100'],
            'additional_deposits.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['status'] = (string) ($data['status'] ?? PmLease::STATUS_DRAFT);

        DB::transaction(function () use ($data) {
            $unitIds = $this->normalizeSingleUnitSelection((array) ($data['property_unit_ids'] ?? []));

            if ($data['status'] === PmLease::STATUS_ACTIVE) {
                $this->assertActiveLeaseRules((int) $data['pm_tenant_id'], $unitIds, null);
            }

            $lease = PmLease::query()->create([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'utility_expense_type' => $data['utility_expense_type'] ?? null,
                'utility_expense_amount' => $data['utility_expense_amount'] ?? null,
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
                'additional_deposits' => Schema::hasColumn('pm_leases', 'additional_deposits')
                    ? $this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? []))
                    : null,
            ]);

            $lease->load('pmTenant');
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

    /**
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function leaseFieldConfig(): array
    {
        $defaults = [
            'tenant_id' => ['enabled' => true, 'required' => true],
            'property_unit_id' => ['enabled' => true, 'required' => true],
            'start_date' => ['enabled' => true, 'required' => true],
            'end_date' => ['enabled' => true, 'required' => false],
            'rent_amount' => ['enabled' => true, 'required' => true],
            'deposit_amount' => ['enabled' => true, 'required' => false],
            'status' => ['enabled' => true, 'required' => true],
        ];
        $raw = PropertyPortalSetting::getValue('system_setup_lease_fields_json', '');
        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! array_key_exists($key, $defaults)) {
                continue;
            }
            $defaults[$key]['enabled'] = ! array_key_exists('enabled', $row) || (bool) $row['enabled'];
            $defaults[$key]['required'] = (bool) ($row['required'] ?? false);
        }

        return $defaults;
    }

    /**
     * @param  array<string,array{enabled:bool,required:bool}>  $config
     */
    private function isFieldRequired(array $config, string $field): bool
    {
        return (bool) (($config[$field]['enabled'] ?? false) && ($config[$field]['required'] ?? false));
    }

    public function show(PmLease $lease): View
    {
        $lease->load([
            'pmTenant',
            'units.property',
        ]);

        $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');
        $daysLeft = $lease->end_date
            ? ($lease->end_date->isBefore(today()) ? 0 : (int) today()->diffInDays($lease->end_date))
            : null;
        $isEndingSoon = $lease->status === PmLease::STATUS_ACTIVE
            && $lease->end_date
            && $lease->end_date->lte(now()->addDays(60));

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
            'tenants' => $this->selectableTenants($lease),
            'units' => $this->leaseAssignableUnits($lease)->get(),
            'vacantProperties' => $this->leaseAssignableUnits($lease)->get()
                ->pluck('property')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'leaseTemplate' => PropertyPortalSetting::getValue('template_lease_text', ''),
        ]);
    }

    public function update(Request $request, PmLease $lease): RedirectResponse
    {
        $lease->load(['units:id', 'pmTenant']);

        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_expense_type' => ['nullable', 'in:water,electricity,other'],
            'utility_expense_amount' => ['nullable', 'numeric', 'min:0', 'required_with:utility_expense_type'],
            'status' => ['required', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => ['nullable', 'array', 'max:1'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
            'additional_deposits' => ['nullable', 'array', 'max:20'],
            'additional_deposits.*.label' => ['nullable', 'string', 'max:100'],
            'additional_deposits.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $lease) {
            $prevStatus = $lease->status;
            $prevUnitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
            $unitIds = $this->normalizeSingleUnitSelection((array) ($data['property_unit_ids'] ?? []));
            if ($data['status'] === PmLease::STATUS_ACTIVE) {
                $this->assertActiveLeaseRules((int) $data['pm_tenant_id'], $unitIds, (int) $lease->id);
            }

            $lease->update([
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'utility_expense_type' => $data['utility_expense_type'] ?? null,
                'utility_expense_amount' => $data['utility_expense_amount'] ?? null,
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
                'additional_deposits' => Schema::hasColumn('pm_leases', 'additional_deposits')
                    ? $this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? []))
                    : null,
            ]);

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
            $tenantName = $l->pmTenant?->name ?? '—';

            $filterParts = [
                mb_strtolower($tenantName),
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
                    $tenantName,
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
