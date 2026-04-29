<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\DepositDefinition;
use App\Models\ExpenseDefinition;
use App\Models\LeaseDepositLine;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmUnitMovement;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PmLeaseWebController extends Controller
{
    private const AUTO_ARREARS_PREFIX = '[Lease Opening Arrears]';

    private const AUTO_UTILITY_PREFIX = '[Lease Utility Expense]';

    private const AUTO_DEPOSIT_PREFIX = '[Lease Deposit Charge]';

    /**
     * @return array{
     *   stats: array<int,array{label:string,value:string,hint:string}>,
     *   columns: array<int,string>,
     *   tableRows: array<int,array<int,mixed>>,
     *   expiryFilterTexts: array<int,string>
     * }
     */
    private function expiryTablePayload(): array
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

        return [
            'stats' => [
                ['label' => 'Expiring ≤30d', 'value' => (string) $in30, 'hint' => 'Urgent'],
                ['label' => 'Expiring ≤60d', 'value' => (string) $in60, 'hint' => 'Outreach'],
                ['label' => 'Expiring ≤90d', 'value' => (string) $in90, 'hint' => 'This list'],
                ['label' => 'Rent at risk (mo)', 'value' => PropertyMoney::kes($rentAtRisk), 'hint' => 'If not renewed'],
            ],
            'columns' => ['Tenant', 'Unit', 'End date', 'Days left', 'Current rent', 'Renewal offer', 'Status', 'Owner'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'expiryFilterTexts' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
        ];
    }

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

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{charge_type:string,specific_charge:string,period:?string,amount:string}>
     */
    private function normalizeOpeningArrears(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $chargeType = trim((string) ($row['charge_type'] ?? ''));
            $specificCharge = trim((string) ($row['specific_charge'] ?? ''));
            $period = trim((string) ($row['period'] ?? ''));
            $amountRaw = $row['amount'] ?? null;

            if ($chargeType === '' && $specificCharge === '' && $period === '' && ($amountRaw === null || $amountRaw === '')) {
                continue;
            }

            if (! is_numeric($amountRaw)) {
                continue;
            }

            $amount = max(0, (float) $amountRaw);
            if ($amount <= 0) {
                continue;
            }

            if ($chargeType === '') {
                $chargeType = 'other';
            }

            $normalized[] = [
                'charge_type' => mb_substr($chargeType, 0, 50),
                'specific_charge' => mb_substr($specificCharge, 0, 100),
                'period' => $period !== '' ? $period : null,
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $normalized;
    }

    private function utilityExpenseTypeLabel(?string $value): string
    {
        $type = trim((string) $value);
        if ($type === '') {
            return '';
        }

        return ucwords(str_replace('_', ' ', $type));
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{type:string,amount:string}>
     */
    private function normalizeUtilityExpenses(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $type = trim((string) ($row['type'] ?? ''));
            if ($type === '') {
                $type = 'other';
            }
            $rateRaw = $row['rate_per_unit'] ?? null;
            $fixedRaw = $row['fixed_charge'] ?? $row['fixed'] ?? null;
            $amountRaw = $row['amount'] ?? null;

            $rate = is_numeric($rateRaw) ? max(0.0, (float) $rateRaw) : 0.0;
            $fixed = is_numeric($fixedRaw) ? max(0.0, (float) $fixedRaw) : 0.0;

            $amount = 0.0;
            if (is_numeric($amountRaw) && (float) $amountRaw > 0) {
                $amount = max(0.0, (float) $amountRaw);
            } elseif ($fixed > 0) {
                $amount = $fixed;
            } elseif ($rate > 0) {
                $amount = $rate;
            }

            if ($amount <= 0) {
                continue;
            }

            $normalized[] = [
                'type' => mb_substr($type, 0, 50),
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int,array{type:string,amount:string}>  $utilityExpenses
     * @param  array<int,int>  $unitIds
     */
    private function assertUtilityExpenseTypesAllowed(array $utilityExpenses, array $unitIds): void
    {
        $allowedTypes = $this->allowedUtilityTypesForUnit($unitIds);
        if ($allowedTypes === []) {
            return;
        }

        $invalid = collect($utilityExpenses)
            ->map(fn (array $row): string => $this->normalizeUtilityType((string) ($row['type'] ?? '')))
            ->filter(fn (string $type): bool => $type !== '' && ! in_array($type, $allowedTypes, true))
            ->unique()
            ->values()
            ->all();

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'utility_expenses' => 'Only configured utility types are allowed for the selected unit/property: '.implode(', ', $invalid),
            ]);
        }
    }

    /**
     * @param  array<int,int>  $unitIds
     * @return array<int,string>
     */
    private function allowedUtilityTypesForUnit(array $unitIds): array
    {
        $unitId = (int) ($unitIds[0] ?? 0);
        if ($unitId <= 0) {
            return [];
        }

        $unit = PropertyUnit::query()->select(['id', 'property_id'])->find($unitId);
        if (! $unit) {
            return [];
        }

        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];
        $rows = $all[(string) $unit->property_id] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $types = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $scopeUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' ? (int) $row['property_unit_id'] : null;
            if ($scopeUnitId !== null && $scopeUnitId !== $unitId) {
                continue;
            }

            $type = $this->normalizeUtilityType((string) ($row['charge_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $types[$type] = $type;
        }

        foreach (ExpenseDefinition::query()
            ->where('is_active', true)
            ->where('property_id', $unit->property_id)
            ->orderBy('sort_order')
            ->orderBy('charge_key')
            ->get() as $def) {
            $scopeUnitId = $def->property_unit_id ? (int) $def->property_unit_id : null;
            if ($scopeUnitId !== null && $scopeUnitId !== $unitId) {
                continue;
            }
            $type = $this->normalizeUtilityType((string) $def->charge_key);
            if ($type === '') {
                continue;
            }
            $types[$type] = $type;
        }

        return array_values($types);
    }

    /**
     * Merge legacy JSON templates with active ExpenseDefinition rows so lease UI stays aligned with Settings rules.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function utilityChargeTemplatesByPropertyMerged(): array
    {
        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $decoded = json_decode($raw, true);
        $byProperty = is_array($decoded) ? $decoded : [];

        foreach (ExpenseDefinition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('charge_key')
            ->get() as $def) {
            $pid = (int) $def->property_id;
            if ($pid <= 0) {
                continue;
            }
            $key = trim((string) $def->charge_key);
            if ($key === '') {
                continue;
            }
            $mode = (string) $def->amount_mode;
            $amount = is_numeric($def->amount_value) ? max(0.0, (float) $def->amount_value) : 0.0;
            $row = [
                'property_unit_id' => $def->property_unit_id ? (int) $def->property_unit_id : null,
                'charge_type' => $key,
                'label' => (string) ($def->label ?? $key),
                'rate_per_unit' => $mode === ExpenseDefinition::MODE_RATE_PER_UNIT ? round($amount, 2) : 0.0,
                'fixed_charge' => $mode === ExpenseDefinition::MODE_RATE_PER_UNIT ? 0.0 : round($amount, 2),
                'notes' => '',
                'is_required' => (bool) $def->is_required,
            ];

            $list = $byProperty[(string) $pid] ?? [];
            if (! is_array($list)) {
                $list = [];
            }
            $replaceIdx = null;
            foreach ($list as $i => $existing) {
                if (! is_array($existing)) {
                    continue;
                }
                $eType = $this->normalizeUtilityType((string) ($existing['charge_type'] ?? ''));
                $eUnit = isset($existing['property_unit_id']) && $existing['property_unit_id'] !== null && $existing['property_unit_id'] !== ''
                    ? (int) $existing['property_unit_id']
                    : null;
                $dUnit = $row['property_unit_id'];
                if ($eType === $this->normalizeUtilityType($key) && $eUnit === $dUnit) {
                    $replaceIdx = $i;
                    break;
                }
            }
            if ($replaceIdx !== null) {
                $list[$replaceIdx] = array_merge($list[$replaceIdx], $row);
            } else {
                $list[] = $row;
            }
            $byProperty[(string) $pid] = array_values($list);
        }

        return $byProperty;
    }

    private function normalizeUtilityType(string $type): string
    {
        return (string) Str::of($type)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
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

    /**
     * Mirror lease optional arrears/deposits into revenue modules.
     *
     * Utilities are usage-based recurring charges and should be posted from
     * utility billing runs (meter readings / periodic billing), not lease save.
     */
    private function syncLeaseRevenuePostings(PmLease $lease): void
    {
        $lease->loadMissing(['units:id,property_id,label']);
        $unit = $lease->units->first();
        if (! $unit) {
            return;
        }

        $unitId = (int) $unit->id;
        $tenantId = (int) $lease->pm_tenant_id;
        $billingMonth = ($lease->start_date?->format('Y-m')) ?: now()->format('Y-m');

        // Reset previous auto-generated rows for idempotent updates.
        PmInvoice::query()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', self::AUTO_ARREARS_PREFIX.'%')
            ->delete();

        PmUnitUtilityCharge::query()
            ->where('property_unit_id', $unitId)
            ->where('notes', 'like', self::AUTO_UTILITY_PREFIX.'%')
            ->delete();

        PmInvoice::query()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', self::AUTO_DEPOSIT_PREFIX.'%')
            ->delete();

        $openingArrears = collect($lease->opening_arrears ?? [])
            ->filter(fn ($row) => is_array($row) && (float) ($row['amount'] ?? 0) > 0)
            ->values();

        foreach ($openingArrears as $row) {
            $chargeType = mb_strtolower(trim((string) ($row['charge_type'] ?? 'other')));
            $specific = trim((string) ($row['specific_charge'] ?? ''));
            $period = trim((string) ($row['period'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $baseDate = $lease->opening_arrears_as_of_date
                ? $lease->opening_arrears_as_of_date->toDateString()
                : ($lease->start_date?->toDateString() ?? now()->toDateString());

            if (preg_match('/^\d{4}\-\d{2}$/', $period) === 1) {
                $baseDate = $period.'-01';
            }

            $issueDate = $baseDate;
            $dueDate = $baseDate;
            if ($dueDate >= now()->toDateString()) {
                $dueDate = now()->subDay()->toDateString();
                $issueDate = $dueDate;
            }

            $descParts = array_filter([
                self::AUTO_ARREARS_PREFIX,
                ucfirst($chargeType),
                $specific !== '' ? $specific : null,
                $period !== '' ? "Period {$period}" : null,
                $lease->opening_arrears_note ? 'Note: '.$lease->opening_arrears_note : null,
            ]);

            PmInvoice::query()->create([
                'pm_lease_id' => $lease->id,
                'property_unit_id' => $unitId,
                'pm_tenant_id' => $tenantId,
                'invoice_no' => PmInvoice::nextInvoiceNumber(),
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'amount' => $amount,
                'amount_paid' => 0,
                'status' => PmInvoice::STATUS_OVERDUE,
                'invoice_type' => $chargeType === PmInvoice::TYPE_WATER ? PmInvoice::TYPE_WATER : PmInvoice::TYPE_MIXED,
                'billing_period' => $period !== '' ? $period : $billingMonth,
                'description' => implode(' | ', $descParts),
            ]);
        }

        // Intentionally no auto-utility posting from lease save.

        if (Schema::hasTable('lease_deposit_lines')) {
            $depositLines = $lease->depositLines()
                ->where('expected_amount', '>', 0)
                ->get();
            foreach ($depositLines as $line) {
                $expected = (float) $line->expected_amount;
                if ($expected <= 0) {
                    continue;
                }
                PmInvoice::query()->create([
                    'pm_lease_id' => $lease->id,
                    'property_unit_id' => $unitId,
                    'pm_tenant_id' => $tenantId,
                    'invoice_no' => PmInvoice::nextInvoiceNumber(),
                    'issue_date' => $lease->start_date?->toDateString() ?? now()->toDateString(),
                    'due_date' => $lease->start_date?->toDateString() ?? now()->toDateString(),
                    'amount' => $expected,
                    'amount_paid' => (float) $line->paid_amount,
                    'status' => ((float) $line->balance_amount) <= 0 ? PmInvoice::STATUS_PAID : PmInvoice::STATUS_SENT,
                    'invoice_type' => PmInvoice::TYPE_MIXED,
                    'billing_period' => $billingMonth,
                    'description' => self::AUTO_DEPOSIT_PREFIX.' '.$line->label,
                ]);
            }
        }
    }

    public function leases(): View
    {
        $activeTab = request()->string('tab')->toString() === 'expiry' ? 'expiry' : 'leases';
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
            $isTerminated = $l->status === PmLease::STATUS_TERMINATED;
            $utilityExpenses = collect($l->utility_expenses ?? [])
                ->filter(fn ($row) => is_array($row))
                ->values();
            if ($utilityExpenses->isNotEmpty()) {
                $expenseLines = $utilityExpenses
                    ->filter(fn ($row) => trim((string) ($row['type'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0)
                    ->map(function ($row): string {
                        $type = $this->utilityExpenseTypeLabel((string) ($row['type'] ?? ''));
                        if ($type === '') {
                            $type = ucfirst(str_replace('_', ' ', (string) ($row['type'] ?? 'Other')));
                        }
                        return $type.': '.PropertyMoney::kes((float) ($row['amount'] ?? 0));
                    })
                    ->values()
                    ->all();
                $expenseLabel = $expenseLines !== []
                    ? new HtmlString(implode('<br>', array_map(static fn ($line) => e($line), $expenseLines)))
                    : '—';
            } else {
                $expenseType = $this->utilityExpenseTypeLabel($l->utility_expense_type);
                $expenseAmount = (float) ($l->utility_expense_amount ?? 0);
                $expenseLabel = ($expenseType !== '' && $expenseAmount > 0)
                    ? new HtmlString(e($expenseType.': '.PropertyMoney::kes($expenseAmount)))
                    : '—';
            }

            $additionalDeposits = collect($l->additional_deposits ?? [])
                ->filter(fn ($row) => is_array($row) && trim((string) ($row['label'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0)
                ->values();
            $depositLines = [
                'Rent deposit: '.PropertyMoney::kes((float) $l->deposit_amount),
            ];
            foreach ($additionalDeposits as $row) {
                $depositLines[] = trim((string) ($row['label'] ?? 'Deposit')).': '.PropertyMoney::kes((float) ($row['amount'] ?? 0));
            }
            $depositBreakdown = new HtmlString(implode('<br>', array_map(static fn ($line) => e($line), $depositLines)));

            $statusAction = $isTerminated
                ? '<form method="post" action="'.route('property.leases.restore', $l, false).'" onsubmit="return confirm(\'Restore this lease to active?\');" class="mt-1 border-t border-slate-100 pt-1">'.
                    '<input type="hidden" name="_token" value="'.csrf_token().'" />'.
                    '<button type="submit" class="block w-full rounded px-2 py-1.5 text-left text-xs font-medium text-emerald-700 hover:bg-emerald-50">Restore</button>'.
                    '</form>'
                : '<form method="post" action="'.route('property.leases.terminate', $l, false).'" onsubmit="return confirm(\'Terminate this lease now?\');" class="mt-1 border-t border-slate-100 pt-1">'.
                    '<input type="hidden" name="_token" value="'.csrf_token().'" />'.
                    '<button type="submit" class="block w-full rounded px-2 py-1.5 text-left text-xs font-medium text-amber-700 hover:bg-amber-50">Terminate</button>'.
                    '</form>';

            $actions = new HtmlString(
                '<details class="relative">'.
                '<summary class="list-none cursor-pointer rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">'.
                'Actions <span class="text-slate-400">▼</span>'.
                '</summary>'.
                '<div class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">'.
                '<a href="'.route('property.leases.show', $l).'" class="block rounded px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">View</a>'.
                '<a href="'.route('property.leases.edit', $l).'" class="block rounded px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Edit</a>'.
                '<a href="'.route('property.revenue.invoices', ['q' => $tenantName], false).'" data-turbo-frame="property-main" class="block rounded px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Invoices</a>'.
                '<a href="'.route('property.revenue.payments', ['q' => $tenantName], false).'" data-turbo-frame="property-main" class="block rounded px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Payments</a>'.
                '<a href="'.route('property.tenants.notices', ['tenant_id' => $l->pm_tenant_id], false).'" data-turbo-frame="property-main" class="block rounded px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Notices</a>'.
                '<a href="'.route('property.tenants.statement', ['tenant' => $l->pm_tenant_id], false).'" data-turbo-frame="property-main" class="block rounded px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Statement</a>'.
                $statusAction.
                '<form method="post" action="'.route('property.leases.destroy', $l, false).'" onsubmit="return confirm(\'Delete this lease permanently?\');" class="mt-1">'.
                '<input type="hidden" name="_token" value="'.csrf_token().'" />'.
                '<input type="hidden" name="_method" value="DELETE" />'.
                '<button type="submit" class="block w-full rounded px-2 py-1.5 text-left text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>'.
                '</form>'.
                '</div>'.
                '</details>'
            );


            
            return [
                '#'.$l->id,
                $tenantName,
                $units !== '' ? $units : '—',
                $l->start_date->format('Y-m-d'),
                $l->end_date?->format('Y-m-d') ?? 'Open-ended',
                number_format((float) $l->monthly_rent, 2),
                $depositBreakdown,
                $expenseLabel,
                ucfirst($l->status),
                $actions,
            ];
        })->all();

        $expiryPayload = $this->expiryTablePayload();
        $vacantUnits = $this->leaseAssignableUnits()->get();
        $utilityChargeTemplatesByProperty = $this->utilityChargeTemplatesByPropertyMerged();

        return view('property.agent.tenants.leases', [
            'activeTab' => $activeTab,
            'stats' => $activeTab === 'expiry' ? $expiryPayload['stats'] : $stats,
            'columns' => $activeTab === 'expiry'
                ? $expiryPayload['columns']
                : ['Lease #', 'Tenant', 'Unit(s)', 'Start', 'End', 'Rent', 'Deposit held', 'Expense paid', 'Status', 'Actions'],
            'tableRows' => $activeTab === 'expiry' ? $expiryPayload['tableRows'] : $rows,
            'expiryFilterTexts' => $activeTab === 'expiry' ? $expiryPayload['expiryFilterTexts'] : [],
            'tenants' => $this->selectableTenants(),
            'vacantUnits' => $vacantUnits,
            'vacantProperties' => $vacantUnits
                ->pluck('property')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'utilityChargeTemplatesByProperty' => $utilityChargeTemplatesByProperty,
            'depositDefinitionsByProperty' => $this->depositDefinitionsByProperty(),
            'leaseTemplate' => $leaseTemplate,
            'leaseFields' => $this->leaseFieldConfig(),
            'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
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
            'utility_expense_type' => ['nullable', 'string', 'max:50'],
            'utility_expense_rate' => ['nullable', 'numeric', 'min:0', 'required_with:utility_expense_type'],
            'utility_expenses' => ['nullable', 'array', 'max:20'],
            'utility_expenses.*.type' => ['nullable', 'string', 'max:50'],
            'utility_expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'status' => [Rule::requiredIf($this->isFieldRequired($cfg, 'status')), 'nullable', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => [Rule::requiredIf($this->isFieldRequired($cfg, 'property_unit_id')), 'nullable', 'array', 'max:1'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
            'additional_deposits' => ['nullable', 'array', 'max:20'],
            'additional_deposits.*.label' => ['nullable', 'string', 'max:100'],
            'additional_deposits.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears' => ['nullable', 'array', 'max:20'],
            'opening_arrears.*.charge_type' => ['nullable', 'string', 'max:50'],
            'opening_arrears.*.specific_charge' => ['nullable', 'string', 'max:100'],
            'opening_arrears.*.period' => ['nullable', 'date_format:Y-m'],
            'opening_arrears.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_manual_total' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_as_of_date' => ['nullable', 'date'],
            'opening_arrears_note' => ['nullable', 'string', 'max:500'],
        ]);

        $data['status'] = (string) ($data['status'] ?? PmLease::STATUS_DRAFT);
        $unitIds = $this->normalizeSingleUnitSelection((array) ($data['property_unit_ids'] ?? []));
        $depositLines = $this->prepareLeaseDepositLines($data, $unitIds);
        $utilityExpenses = $this->normalizeUtilityExpenses((array) ($data['utility_expenses'] ?? []));
        $legacyType = trim((string) ($data['utility_expense_type'] ?? ''));
        $legacyAmountRaw = $data['utility_expense_rate'] ?? ($data['utility_expense_amount'] ?? null);
        if ($legacyType !== '' && is_numeric($legacyAmountRaw) && (float) $legacyAmountRaw > 0) {
            array_unshift($utilityExpenses, [
                'type' => mb_substr($legacyType, 0, 50),
                'amount' => number_format((float) $legacyAmountRaw, 2, '.', ''),
            ]);
        }
        $this->assertUtilityExpenseTypesAllowed($utilityExpenses, $unitIds);
        $firstUtility = $utilityExpenses[0] ?? null;

        DB::transaction(function () use ($data, $utilityExpenses, $firstUtility, $unitIds, $depositLines) {
            if ($data['status'] === PmLease::STATUS_ACTIVE) {
                $this->assertActiveLeaseRules((int) $data['pm_tenant_id'], $unitIds, null);
            }

            $payload = [
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'utility_expense_type' => $firstUtility['type'] ?? ($data['utility_expense_type'] ?? null),
                'utility_expense_amount' => isset($firstUtility['amount']) ? (float) $firstUtility['amount'] : ($data['utility_expense_rate'] ?? null),
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ];

            if (Schema::hasColumn('pm_leases', 'additional_deposits')) {
                $payload['additional_deposits'] = $this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? []));
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears')) {
                $payload['opening_arrears'] = $this->normalizeOpeningArrears((array) ($data['opening_arrears'] ?? []));
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_manual_total')) {
                $payload['opening_arrears_manual_total'] = isset($data['opening_arrears_manual_total'])
                    ? (float) $data['opening_arrears_manual_total']
                    : null;
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_as_of_date')) {
                $payload['opening_arrears_as_of_date'] = $data['opening_arrears_as_of_date'] ?? null;
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_note')) {
                $payload['opening_arrears_note'] = $data['opening_arrears_note'] ?? null;
            }
            if (Schema::hasColumn('pm_leases', 'utility_expenses')) {
                $payload['utility_expenses'] = $utilityExpenses;
            }

            $lease = PmLease::query()->create($payload);

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
            $this->syncLeaseDepositLines($lease, $depositLines);

            $this->syncLeaseRevenuePostings($lease);
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

    /**
     * @return array<string,string>
     */
    private function openingArrearsTypeOptions(): array
    {
        return [
            'rent' => 'Rent',
            'water' => 'Water',
            'electricity' => 'Electricity',
            'service_charge' => 'Service charge',
            'garbage' => 'Garbage',
            'internet' => 'Internet',
            'parking' => 'Parking',
            'utility_other' => 'Other utility',
            'penalty' => 'Penalty',
            'other' => 'Other charge',
            'custom_charge' => 'Custom charge',
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function buildOpeningArrearsPayload(array $data): array
    {
        $items = collect((array) ($data['opening_arrears_items'] ?? []))
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): array {
                return [
                    'type' => (string) ($item['type'] ?? ''),
                    'period' => (string) ($item['period'] ?? ''),
                    'amount' => round((float) ($item['amount'] ?? 0), 2),
                    'label' => trim((string) ($item['label'] ?? '')),
                    'reference' => trim((string) ($item['reference'] ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['type'] !== '' && $item['period'] !== '' && $item['amount'] > 0)
            ->values();

        $categories = [
            'opening_arrears_rent' => 0.0,
            'opening_arrears_utilities' => 0.0,
            'opening_arrears_penalties' => 0.0,
            'opening_arrears_other' => 0.0,
        ];
        $utilityTypes = ['water', 'electricity', 'service_charge', 'garbage', 'internet', 'parking', 'utility_other'];
        foreach ($items as $item) {
            $type = (string) $item['type'];
            $amount = (float) $item['amount'];
            if ($type === 'rent') {
                $categories['opening_arrears_rent'] += $amount;
            } elseif ($type === 'penalty') {
                $categories['opening_arrears_penalties'] += $amount;
            } elseif (in_array($type, $utilityTypes, true)) {
                $categories['opening_arrears_utilities'] += $amount;
            } else {
                $categories['opening_arrears_other'] += $amount;
            }
        }

        $computedTotal = array_sum($categories);
        $manualTotal = (float) ($data['opening_arrears_amount'] ?? 0);
        $total = $computedTotal > 0 ? $computedTotal : $manualTotal;
        $asOf = $total > 0 ? ($data['opening_arrears_as_of'] ?? now()->toDateString()) : null;

        return [
            'opening_arrears_rent' => (float) $categories['opening_arrears_rent'],
            'opening_arrears_utilities' => (float) $categories['opening_arrears_utilities'],
            'opening_arrears_penalties' => (float) $categories['opening_arrears_penalties'],
            'opening_arrears_other' => (float) $categories['opening_arrears_other'],
            'opening_arrears_amount' => $total,
            'opening_arrears_as_of' => $asOf,
            'opening_arrears_notes' => $data['opening_arrears_notes'] ?? null,
            'opening_arrears_items' => $items->all(),
        ];
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
        $utilityChargeTemplatesByProperty = $this->utilityChargeTemplatesByPropertyMerged();

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
            'utilityChargeTemplatesByProperty' => $utilityChargeTemplatesByProperty,
            'depositDefinitionsByProperty' => $this->depositDefinitionsByProperty(),
            'leaseTemplate' => PropertyPortalSetting::getValue('template_lease_text', ''),
            'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
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
            'utility_expense_type' => ['nullable', 'string', 'max:50'],
            'utility_expense_rate' => ['nullable', 'numeric', 'min:0', 'required_with:utility_expense_type'],
            'utility_expenses' => ['nullable', 'array', 'max:20'],
            'utility_expenses.*.type' => ['nullable', 'string', 'max:50'],
            'utility_expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => ['nullable', 'array', 'max:1'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
            'additional_deposits' => ['nullable', 'array', 'max:20'],
            'additional_deposits.*.label' => ['nullable', 'string', 'max:100'],
            'additional_deposits.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears' => ['nullable', 'array', 'max:20'],
            'opening_arrears.*.charge_type' => ['nullable', 'string', 'max:50'],
            'opening_arrears.*.specific_charge' => ['nullable', 'string', 'max:100'],
            'opening_arrears.*.period' => ['nullable', 'date_format:Y-m'],
            'opening_arrears.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_manual_total' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_as_of_date' => ['nullable', 'date'],
            'opening_arrears_note' => ['nullable', 'string', 'max:500'],
        ]);

        $unitIds = $this->normalizeSingleUnitSelection((array) ($data['property_unit_ids'] ?? []));
        $depositLines = $this->prepareLeaseDepositLines($data, $unitIds);
        $utilityExpenses = $this->normalizeUtilityExpenses((array) ($data['utility_expenses'] ?? []));
        $legacyType = trim((string) ($data['utility_expense_type'] ?? ''));
        $legacyAmountRaw = $data['utility_expense_rate'] ?? ($data['utility_expense_amount'] ?? null);
        if ($legacyType !== '' && is_numeric($legacyAmountRaw) && (float) $legacyAmountRaw > 0) {
            array_unshift($utilityExpenses, [
                'type' => mb_substr($legacyType, 0, 50),
                'amount' => number_format((float) $legacyAmountRaw, 2, '.', ''),
            ]);
        }
        $this->assertUtilityExpenseTypesAllowed($utilityExpenses, $unitIds);
        $firstUtility = $utilityExpenses[0] ?? null;

        DB::transaction(function () use ($data, $lease, $utilityExpenses, $firstUtility, $unitIds, $depositLines) {
            $prevStatus = $lease->status;
            $prevUnitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
            if ($data['status'] === PmLease::STATUS_ACTIVE) {
                $this->assertActiveLeaseRules((int) $data['pm_tenant_id'], $unitIds, (int) $lease->id);
            }

            $payload = [
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'utility_expense_type' => $firstUtility['type'] ?? ($data['utility_expense_type'] ?? null),
                'utility_expense_amount' => isset($firstUtility['amount']) ? (float) $firstUtility['amount'] : ($data['utility_expense_rate'] ?? null),
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ];
            if (Schema::hasColumn('pm_leases', 'additional_deposits')) {
                $payload['additional_deposits'] = $this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? []));
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears')) {
                $payload['opening_arrears'] = $this->normalizeOpeningArrears((array) ($data['opening_arrears'] ?? []));
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_manual_total')) {
                $payload['opening_arrears_manual_total'] = isset($data['opening_arrears_manual_total'])
                    ? (float) $data['opening_arrears_manual_total']
                    : null;
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_as_of_date')) {
                $payload['opening_arrears_as_of_date'] = $data['opening_arrears_as_of_date'] ?? null;
            }
            if (Schema::hasColumn('pm_leases', 'opening_arrears_note')) {
                $payload['opening_arrears_note'] = $data['opening_arrears_note'] ?? null;
            }
            if (Schema::hasColumn('pm_leases', 'utility_expenses')) {
                $payload['utility_expenses'] = $utilityExpenses;
            }

            $lease->update($payload);

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
            $this->syncLeaseDepositLines($lease, $depositLines);

            $this->syncLeaseRevenuePostings($lease);
        });

        return back()->with('success', 'Lease updated.');
    }

    public function terminate(PmLease $lease): RedirectResponse
    {
        $lease->load(['units:id', 'pmTenant']);

        DB::transaction(function () use ($lease): void {
            $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
            $today = now()->toDateString();

            $lease->update([
                'status' => PmLease::STATUS_TERMINATED,
                'end_date' => $lease->end_date ?? $today,
            ]);

            if ($unitIds !== []) {
                $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
                $this->ensureMovementLogged($lease, $unitIds, 'move_out', $today);
            }
        });

        return back()->with('success', 'Lease terminated.');
    }

    public function restore(PmLease $lease): RedirectResponse
    {
        $lease->load(['units:id', 'pmTenant']);

        DB::transaction(function () use ($lease): void {
            $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
            $startDate = $lease->start_date?->toDateString() ?? now()->toDateString();

            $lease->update([
                'status' => PmLease::STATUS_ACTIVE,
                'end_date' => null,
            ]);

            if ($unitIds !== []) {
                PropertyUnit::query()->whereIn('id', $unitIds)->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);
                $this->ensureMovementLogged($lease, $unitIds, 'move_in', $startDate);
            }
        });

        return back()->with('success', 'Lease restored to active.');
    }

    public function destroy(PmLease $lease): RedirectResponse
    {
        $lease->load(['units:id', 'pmTenant']);

        DB::transaction(function () use ($lease): void {
            $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();

            if ($unitIds !== []) {
                $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
            }

            $lease->delete();
        });

        return back()->with('success', 'Lease deleted.');
    }

    public function expiry(): View
    {
        return redirect()->route('property.tenants.leases', ['tab' => 'expiry']);
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,int>  $unitIds
     * @return array<int,array<string,mixed>>
     */
    private function prepareLeaseDepositLines(array $data, array $unitIds): array
    {
        if (! Schema::hasTable('lease_deposit_lines')) {
            return [];
        }

        $unit = null;
        if ($unitIds !== []) {
            $unit = PropertyUnit::query()->select(['id', 'property_id'])->find($unitIds[0]);
            if (! $unit) {
                throw ValidationException::withMessages([
                    'property_unit_ids' => ['Selected unit is invalid.'],
                ]);
            }
        }

        $monthlyRent = (float) ($data['monthly_rent'] ?? 0);
        $submitted = $this->submittedDepositPayload($data);
        if (! $unit) {
            return array_values($submitted);
        }

        $definitions = $this->resolveDepositDefinitions((int) $unit->property_id, (int) $unit->id);
        if ($definitions->isEmpty()) {
            return array_values($submitted);
        }

        $allowedKeys = $definitions->pluck('deposit_key')->map(fn ($v) => (string) $v)->all();
        $allowCustom = (bool) (auth()->user()?->is_super_admin)
            || PropertyPortalSetting::getValue('lease_deposit_allow_custom_types', '0') === '1';

        $unknownKeys = array_values(array_diff(array_keys($submitted), $allowedKeys));
        if ($unknownKeys !== [] && ! $allowCustom) {
            throw ValidationException::withMessages([
                'additional_deposits' => ['One or more deposit types are not allowed for the selected property/unit.'],
            ]);
        }

        $lines = [];
        foreach ($definitions as $definition) {
            $key = (string) $definition->deposit_key;
            $submittedLine = $submitted[$key] ?? null;
            $expected = $submittedLine['expected_amount'] ?? $this->definitionDefaultAmount($definition, $monthlyRent);

            if ((bool) $definition->is_required && $expected <= 0) {
                throw ValidationException::withMessages([
                    'deposit_amount' => ["Required deposit \"{$definition->label}\" is missing."],
                ]);
            }

            if ($expected <= 0 && ! $submittedLine) {
                continue;
            }

            $lines[] = [
                'deposit_definition_id' => (int) $definition->id,
                'deposit_key' => $key,
                'label' => (string) ($submittedLine['label'] ?? $definition->label),
                'expected_amount' => $expected,
                'paid_amount' => 0.0,
                'balance_amount' => $expected,
                'is_refundable' => (bool) $definition->is_refundable,
                'refund_status' => 'not_refunded',
                'meta' => [
                    'is_required' => (bool) $definition->is_required,
                    'ledger_account' => $definition->ledger_account,
                    'amount_mode' => $definition->amount_mode,
                    'amount_value' => (float) $definition->amount_value,
                ],
            ];
        }

        if ($allowCustom) {
            foreach ($unknownKeys as $key) {
                $line = $submitted[$key] ?? null;
                if (! $line) {
                    continue;
                }
                $lines[] = $line + [
                    'deposit_definition_id' => null,
                    'is_refundable' => true,
                    'refund_status' => 'not_refunded',
                    'meta' => ['custom' => true],
                ];
            }
        }

        return $lines;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,array<string,mixed>>
     */
    private function submittedDepositPayload(array $data): array
    {
        $lines = [];

        $rentDeposit = (float) ($data['deposit_amount'] ?? 0);
        if ($rentDeposit > 0) {
            $lines['rent_deposit'] = [
                'deposit_definition_id' => null,
                'deposit_key' => 'rent_deposit',
                'label' => 'Rent deposit',
                'expected_amount' => $rentDeposit,
                'paid_amount' => 0.0,
                'balance_amount' => $rentDeposit,
                'is_refundable' => true,
                'refund_status' => 'not_refunded',
                'meta' => ['source' => 'rent_deposit_input'],
            ];
        }

        foreach ($this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? [])) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($label === '' || $amount <= 0) {
                continue;
            }

            $key = $this->normalizeDepositKey($label);
            if ($key === '') {
                continue;
            }

            if (isset($lines[$key])) {
                $lines[$key]['expected_amount'] += $amount;
                $lines[$key]['balance_amount'] += $amount;
                continue;
            }

            $lines[$key] = [
                'deposit_definition_id' => null,
                'deposit_key' => $key,
                'label' => $label,
                'expected_amount' => $amount,
                'paid_amount' => 0.0,
                'balance_amount' => $amount,
                'is_refundable' => true,
                'refund_status' => 'not_refunded',
                'meta' => ['source' => 'additional_deposits'],
            ];
        }

        return $lines;
    }

    private function normalizeDepositKey(string $label): string
    {
        return (string) Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/i', '_')->trim('_');
    }

    /**
     * @return Collection<int,DepositDefinition>
     */
    private function resolveDepositDefinitions(int $propertyId, ?int $unitId = null): Collection
    {
        $query = DepositDefinition::query()
            ->where('property_id', $propertyId)
            ->where('is_active', true)
            ->where(function ($scope) use ($unitId): void {
                $scope->whereNull('property_unit_id');
                if ($unitId) {
                    $scope->orWhere('property_unit_id', $unitId);
                }
            })
            ->orderByRaw('case when property_unit_id is null then 0 else 1 end desc')
            ->orderBy('sort_order')
            ->orderBy('id');

        /** @var Collection<int,DepositDefinition> $rows */
        $rows = $query->get();

        return $rows
            ->groupBy(fn (DepositDefinition $definition) => (string) $definition->deposit_key)
            ->map(fn (Collection $bucket) => $bucket->first())
            ->values();
    }

    private function definitionDefaultAmount(DepositDefinition $definition, float $monthlyRent): float
    {
        $value = (float) $definition->amount_value;
        if ($value <= 0) {
            return 0.0;
        }

        if ($definition->amount_mode === DepositDefinition::MODE_PERCENT_RENT) {
            return round(($monthlyRent * $value) / 100, 2);
        }

        return round($value, 2);
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function depositDefinitionsByProperty(): array
    {
        if (! Schema::hasTable('deposit_definitions')) {
            return [];
        }

        $grouped = [];
        DepositDefinition::query()
            ->where('is_active', true)
            ->orderBy('property_id')
            ->orderByRaw('case when property_unit_id is null then 0 else 1 end desc')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (DepositDefinition $definition) use (&$grouped): void {
                $propertyId = (string) $definition->property_id;
                $grouped[$propertyId] ??= [];
                $grouped[$propertyId][] = [
                    'id' => (int) $definition->id,
                    'property_unit_id' => $definition->property_unit_id ? (int) $definition->property_unit_id : null,
                    'deposit_key' => (string) $definition->deposit_key,
                    'label' => (string) $definition->label,
                    'is_required' => (bool) $definition->is_required,
                    'amount_mode' => (string) $definition->amount_mode,
                    'amount_value' => (float) $definition->amount_value,
                    'is_refundable' => (bool) $definition->is_refundable,
                    'ledger_account' => $definition->ledger_account,
                    'sort_order' => (int) $definition->sort_order,
                ];
            });

        return $grouped;
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     */
    private function syncLeaseDepositLines(PmLease $lease, array $lines): void
    {
        if (! Schema::hasTable('lease_deposit_lines')) {
            return;
        }

        LeaseDepositLine::query()->where('pm_lease_id', $lease->id)->delete();
        if ($lines === []) {
            return;
        }

        $now = now();
        $payload = array_map(function (array $line) use ($lease, $now): array {
            return [
                'pm_lease_id' => (int) $lease->id,
                'deposit_definition_id' => $line['deposit_definition_id'] ?? null,
                'deposit_key' => (string) ($line['deposit_key'] ?? ''),
                'label' => (string) ($line['label'] ?? 'Deposit'),
                'expected_amount' => (float) ($line['expected_amount'] ?? 0),
                'paid_amount' => (float) ($line['paid_amount'] ?? 0),
                'balance_amount' => (float) ($line['balance_amount'] ?? 0),
                'is_refundable' => (bool) ($line['is_refundable'] ?? true),
                'refund_status' => (string) ($line['refund_status'] ?? 'not_refunded'),
                'meta' => $line['meta'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $lines);

        LeaseDepositLine::query()->insert($payload);
    }
}
