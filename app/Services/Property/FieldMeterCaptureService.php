<?php

namespace App\Services\Property;

use App\Exceptions\Property\UtilityPeriodClosedException;
use App\Models\Employee;
use App\Models\ExpenseDefinition;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmWaterReading;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FieldMeterCaptureService
{
    public function __construct(
        private readonly PropertyHrEmployeeService $hr,
        private readonly WaterBillingService $water,
    ) {}

    public function canCapture(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }
        if (($actor->is_super_admin ?? false) === true) {
            return true;
        }
        if ($actor->hasPmPermission('revenue.utilities.manage') || $actor->hasPmPermission('utilities.readings.capture')) {
            return true;
        }

        $employee = $this->employeeFor($actor);

        return $employee !== null
            && ! $employee->isOffboarded()
            && $this->hr->isFieldOfficerEmployee($employee);
    }

    /**
     * @return array{billing_month: string, properties: list<array<string, mixed>>}
     */
    public function pack(User $actor, ?string $billingMonth = null): array
    {
        $month = $this->month($billingMonth);
        $propertyIds = $this->propertyIdsFor($actor);
        if ($propertyIds === []) {
            return ['billing_month' => $month, 'properties' => []];
        }

        $properties = Property::query()
            ->whereIn('id', $propertyIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $units = PropertyUnit::query()
            ->whereIn('property_id', $propertyIds)
            ->orderBy('label')
            ->get(['id', 'property_id', 'label', 'water_meter', 'electricity_meter']);

        $unitIds = $units->pluck('id')->map(fn ($id) => (int) $id)->all();
        $water = $this->waterSnapshots($unitIds, $month);
        $charges = $this->chargeSnapshots($unitIds, $month);
        $billed = $this->billedMeterKinds($propertyIds, $units, $water, $charges);

        $grouped = $units->groupBy('property_id');
        $rows = [];
        foreach ($properties as $property) {
            $propertyUnits = [];
            foreach ($grouped->get($property->id, collect()) as $unit) {
                $unitId = (int) $unit->id;
                $kinds = $billed[$unitId] ?? [];
                $meters = [];
                if (isset($kinds['water'])) {
                    $meters[] = $this->meterRow('water', 'Water', (string) ($unit->water_meter ?? ''), $water[$unitId] ?? null);
                }
                if (isset($kinds['electricity'])) {
                    $meters[] = $this->meterRow('electricity', 'Electricity', (string) ($unit->electricity_meter ?? ''), $charges[$unitId]['electricity'] ?? null);
                }
                if (isset($kinds['other'])) {
                    $meters[] = $this->meterRow('other', 'Other utility', '', $charges[$unitId]['other'] ?? null);
                }
                if ($meters === []) {
                    continue;
                }
                $propertyUnits[] = [
                    'id' => $unitId,
                    'label' => (string) $unit->label,
                    'meters' => $meters,
                ];
            }
            if ($propertyUnits === []) {
                continue;
            }
            $rows[] = [
                'id' => (int) $property->id,
                'name' => (string) $property->name,
                'units' => $propertyUnits,
            ];
        }

        return ['billing_month' => $month, 'properties' => $rows];
    }

    /**
     * @param  list<array<string, mixed>>  $readings
     * @return list<array{client_id: string, status: string, message: string}>
     */
    public function sync(User $actor, array $readings): array
    {
        $allowed = array_flip($this->allowedUnitIds($actor));
        $results = [];
        foreach ($readings as $row) {
            $clientId = (string) ($row['client_id'] ?? '');
            $unitId = (int) ($row['property_unit_id'] ?? 0);
            if (! isset($allowed[$unitId])) {
                $results[] = ['client_id' => $clientId, 'status' => 'error', 'message' => 'This unit is not on your field list.'];

                continue;
            }

            try {
                $message = $this->saveOne($actor, $row);
                $results[] = ['client_id' => $clientId, 'status' => 'saved', 'message' => $message];
            } catch (UtilityPeriodClosedException $e) {
                $results[] = ['client_id' => $clientId, 'status' => 'error', 'message' => $e->getMessage()];
            } catch (ValidationException $e) {
                $message = (string) (collect($e->errors())->flatten()->first() ?: 'Could not save this reading.');
                $status = str_contains(strtolower($message), 'already') ? 'saved' : 'error';
                $results[] = ['client_id' => $clientId, 'status' => $status, 'message' => $message];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function saveOne(User $actor, array $row): string
    {
        $meter = (string) ($row['meter'] ?? '');
        $month = $this->month((string) ($row['billing_month'] ?? ''));
        $unitId = (int) $row['property_unit_id'];
        $previous = round(max(0, (float) ($row['previous_reading'] ?? 0)), 3);
        $current = round(max(0, (float) ($row['current_reading'] ?? 0)), 3);
        $rate = round(max(0, (float) ($row['rate_per_unit'] ?? 0)), 2);
        $fixed = round(max(0, (float) ($row['fixed_charge'] ?? 0)), 2);
        $reset = (bool) ($row['is_meter_reset'] ?? false);
        $note = 'Recorded in the field by '.$actor->name;

        if ($meter === 'water') {
            $this->water->recordReading([
                'property_unit_id' => $unitId,
                'billing_month' => $month,
                'previous_reading' => $previous,
                'current_reading' => $current,
                'rate_per_unit' => $rate,
                'fixed_charge' => $fixed,
                'is_meter_reset' => $reset,
                'notes' => $note,
            ], $actor);

            return 'Water reading saved.';
        }

        if (! in_array($meter, ['electricity', 'other'], true)) {
            throw ValidationException::withMessages(['meter' => 'Choose water, electricity, or other.']);
        }

        app(UtilityPeriodGuardService::class)->assertMutable(
            $month,
            UtilityPeriodGuardService::ACTION_EDIT_READING,
            $actor,
            null,
            'pm_unit_utility_charge',
            null,
        );

        $type = $meter === 'electricity' ? 'electricity' : 'other';
        $exists = PmUnitUtilityCharge::query()
            ->where('property_unit_id', $unitId)
            ->where('billing_month', $month)
            ->where('charge_type', $type)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'billing_month' => 'A '.$type.' reading already exists for this unit and month.',
            ]);
        }

        if (! $reset && $current + 0.0005 < $previous) {
            throw ValidationException::withMessages([
                'current_reading' => 'Current reading cannot be less than the previous reading ('.$previous.'). Mark meter reset if the meter was replaced.',
            ]);
        }

        $units = $reset ? $current : round($current - $previous, 3);
        $label = $type === 'electricity'
            ? 'Electricity'
            : (trim((string) ($row['label'] ?? '')) ?: 'Other utility');

        $payload = [
            'property_unit_id' => $unitId,
            'charge_type' => $type,
            'billing_month' => $month,
            'label' => mb_substr($label, 0, 80),
            'units_consumed' => max(0, $units),
            'rate_per_unit' => $rate,
            'fixed_charge' => $fixed,
            'amount' => round((max(0, $units) * $rate) + $fixed, 2),
            'notes' => $note.' · meter '.$previous.' → '.$current,
            'is_invoiced' => false,
        ];
        if (Schema::hasColumn('pm_unit_utility_charges', 'previous_reading')) {
            $payload['previous_reading'] = $previous;
            $payload['current_reading'] = $current;
        }

        PmUnitUtilityCharge::query()->create($payload);

        return ucfirst($type).' reading saved.';
    }

    private function employeeFor(User $actor): ?Employee
    {
        if (! Schema::hasTable('employees')) {
            return null;
        }

        return Employee::query()->where('user_id', $actor->id)->first();
    }

    /**
     * @return list<int>
     */
    private function propertyIdsFor(User $actor): array
    {
        $employee = $this->employeeFor($actor);
        if ($employee && ! $employee->isOffboarded()) {
            $assigned = $this->hr->propertyIdsForEmployee($employee);
            if ($assigned !== []) {
                return $assigned;
            }
            if ($this->hr->isFieldOfficerEmployee($employee)) {
                return [];
            }
        }

        return Property::query()->operational()->orderBy('name')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return list<int>
     */
    private function allowedUnitIds(User $actor): array
    {
        $propertyIds = $this->propertyIdsFor($actor);
        if ($propertyIds === []) {
            return [];
        }

        return PropertyUnit::query()
            ->whereIn('property_id', $propertyIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function month(?string $billingMonth): string
    {
        $billingMonth = trim((string) $billingMonth);
        if (preg_match('/^\d{4}-\d{2}$/', $billingMonth) === 1) {
            return $billingMonth;
        }

        return now()->format('Y-m');
    }

    /**
     * @param  array{previous?: float, current?: float, rate?: float, fixed?: float, recorded?: bool}|null  $snap
     * @return array<string, mixed>
     */
    private function meterRow(string $kind, string $label, string $meterNo, ?array $snap): array
    {
        $recorded = (bool) ($snap['recorded'] ?? false);

        return [
            'kind' => $kind,
            'label' => $label,
            'meter_no' => $meterNo,
            'previous' => round((float) ($snap['previous'] ?? 0), 3),
            'current' => $recorded ? round((float) ($snap['current'] ?? 0), 3) : null,
            'rate' => round((float) ($snap['rate'] ?? 0), 2),
            'fixed' => round((float) ($snap['fixed'] ?? 0), 2),
            'already_recorded' => $recorded,
        ];
    }

    /**
     * @param  list<int>  $unitIds
     * @return array<int, array{previous: float, current: float, rate: float, fixed: float, recorded: bool, has_history: bool}>
     */
    private function waterSnapshots(array $unitIds, string $month): array
    {
        if ($unitIds === [] || ! Schema::hasTable('pm_water_readings')) {
            return [];
        }

        $rows = PmWaterReading::query()
            ->whereIn('property_unit_id', $unitIds)
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->get(['property_unit_id', 'billing_month', 'previous_reading', 'current_reading', 'rate_per_unit', 'fixed_charge']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->property_unit_id][] = $row;
        }

        $snapshots = [];
        foreach ($grouped as $unitId => $list) {
            $thisMonth = null;
            $prior = null;
            foreach ($list as $row) {
                $rowMonth = (string) $row->billing_month;
                if ($rowMonth === $month && $thisMonth === null) {
                    $thisMonth = $row;
                } elseif ($rowMonth < $month && $prior === null) {
                    $prior = $row;
                }
            }
            $source = $thisMonth ?? $list[0];
            $snapshots[$unitId] = [
                'previous' => $thisMonth
                    ? (float) ($prior !== null ? $prior->current_reading : ($thisMonth->previous_reading ?? 0))
                    : (float) $source->current_reading,
                'current' => $thisMonth ? (float) $thisMonth->current_reading : 0.0,
                'rate' => (float) $source->rate_per_unit,
                'fixed' => (float) $source->fixed_charge,
                'recorded' => $thisMonth !== null,
                'has_history' => true,
            ];
        }

        return $snapshots;
    }

    /**
     * @param  list<int>  $unitIds
     * @return array<int, array<string, array{previous: float, current: float, rate: float, fixed: float, recorded: bool, has_history: bool}>>
     */
    private function chargeSnapshots(array $unitIds, string $month): array
    {
        if ($unitIds === [] || ! Schema::hasTable('pm_unit_utility_charges')) {
            return [];
        }

        $hasCurrent = Schema::hasColumn('pm_unit_utility_charges', 'current_reading');
        $hasPrevious = Schema::hasColumn('pm_unit_utility_charges', 'previous_reading');
        $columns = ['property_unit_id', 'charge_type', 'billing_month', 'rate_per_unit', 'fixed_charge', 'notes'];
        if ($hasCurrent) {
            $columns[] = 'current_reading';
        }
        if ($hasPrevious) {
            $columns[] = 'previous_reading';
        }

        $rows = PmUnitUtilityCharge::query()
            ->whereIn('property_unit_id', $unitIds)
            ->whereIn('charge_type', ['electricity', 'other'])
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->get($columns);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->property_unit_id][(string) $row->charge_type][] = $row;
        }

        $snapshots = [];
        foreach ($grouped as $unitId => $byType) {
            foreach ($byType as $type => $list) {
                $thisMonth = null;
                $prior = null;
                $hasHistory = false;
                foreach ($list as $row) {
                    $parsed = $this->chargeReading($row, $hasCurrent, $hasPrevious);
                    if ($parsed['current'] > 0 || $parsed['previous'] > 0) {
                        $hasHistory = true;
                    }
                    $rowMonth = (string) $row->billing_month;
                    if ($rowMonth === $month && $thisMonth === null) {
                        $thisMonth = [$row, $parsed];
                    } elseif ($rowMonth < $month && $prior === null && ($parsed['current'] > 0 || $parsed['previous'] > 0)) {
                        $prior = $parsed;
                    }
                }
                $source = $thisMonth[0] ?? $list[0];
                $currentParsed = $thisMonth[1] ?? $this->chargeReading($source, $hasCurrent, $hasPrevious);
                $snapshots[$unitId][$type] = [
                    'previous' => $thisMonth
                        ? (float) (is_array($prior) ? $prior['current'] : $currentParsed['previous'])
                        : (float) $currentParsed['current'],
                    'current' => $thisMonth ? (float) $currentParsed['current'] : 0.0,
                    'rate' => (float) $source->rate_per_unit,
                    'fixed' => (float) $source->fixed_charge,
                    'recorded' => $thisMonth !== null && ((float) $currentParsed['current'] > 0 || (float) $currentParsed['previous'] > 0),
                    'has_history' => $hasHistory,
                ];
            }
        }

        return $snapshots;
    }

    /**
     * @return array{previous: float, current: float}
     */
    private function chargeReading(PmUnitUtilityCharge $row, bool $hasCurrent, bool $hasPrevious): array
    {
        $current = $hasCurrent ? (float) ($row->current_reading ?? 0) : 0.0;
        $previous = $hasPrevious ? (float) ($row->previous_reading ?? 0) : 0.0;
        if ($current <= 0 && preg_match('/meter\s+([0-9.]+)\s+→\s+([0-9.]+)/', (string) $row->notes, $match) === 1) {
            $previous = $previous > 0 ? $previous : (float) $match[1];
            $current = (float) $match[2];
        }

        return ['previous' => $previous, 'current' => $current];
    }

    /**
     * @param  list<int>  $propertyIds
     * @param  iterable<int, PropertyUnit>  $units
     * @param  array<int, array{has_history?: bool}>  $water
     * @param  array<int, array<string, array{has_history?: bool}>>  $charges
     * @return array<int, array<string, true>>
     */
    private function billedMeterKinds(array $propertyIds, iterable $units, array $water, array $charges): array
    {
        $rules = $this->meterRulesByProperty($propertyIds);
        $kinds = [];
        foreach ($units as $unit) {
            $unitId = (int) $unit->id;
            $show = [];
            foreach ($rules[(int) $unit->property_id] ?? [] as $rule) {
                if ($rule['unit_id'] !== null && $rule['unit_id'] !== $unitId) {
                    continue;
                }
                $show[$rule['kind']] = true;
            }
            $kinds[$unitId] = $show;
        }

        return $kinds;
    }

    /**
     * Meter fields only: water, and electricity/other when the rule is a per-unit meter rate.
     *
     * @param  list<int>  $propertyIds
     * @return array<int, list<array{kind: string, unit_id: ?int}>>
     */
    private function meterRulesByProperty(array $propertyIds): array
    {
        $rules = [];
        $wanted = array_fill_keys(array_map('strval', $propertyIds), true);

        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $templates = json_decode($raw, true);
        if (is_array($templates)) {
            foreach ($templates as $propertyId => $rows) {
                if (! isset($wanted[(string) $propertyId]) || ! is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $kind = $this->meterKind((string) ($row['charge_type'] ?? ''), (float) ($row['rate_per_unit'] ?? 0));
                    if ($kind === null) {
                        continue;
                    }
                    $unitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' && $row['property_unit_id'] !== null
                        ? (int) $row['property_unit_id']
                        : null;
                    $rules[(int) $propertyId][] = ['kind' => $kind, 'unit_id' => $unitId];
                }
            }
        }

        if (Schema::hasTable('expense_definitions')) {
            $definitions = ExpenseDefinition::query()
                ->where('is_active', true)
                ->whereIn('property_id', $propertyIds)
                ->get(['property_id', 'property_unit_id', 'charge_key', 'amount_mode', 'amount_value']);
            foreach ($definitions as $definition) {
                $rate = (string) $definition->amount_mode === ExpenseDefinition::MODE_RATE_PER_UNIT
                    ? (float) $definition->amount_value
                    : 0.0;
                $kind = $this->meterKind((string) $definition->charge_key, $rate);
                if ($kind === null) {
                    continue;
                }
                $rules[(int) $definition->property_id][] = [
                    'kind' => $kind,
                    'unit_id' => $definition->property_unit_id ? (int) $definition->property_unit_id : null,
                ];
            }
        }

        return $rules;
    }

    private function meterKind(string $key, float $ratePerUnit): ?string
    {
        $key = strtolower(trim($key));
        $key = (string) preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        if (in_array($key, ['water', 'utility_water', 'water_meter'], true) || str_contains($key, 'water')) {
            return 'water';
        }
        if (in_array($key, ['electricity', 'utility_electricity', 'electric', 'power'], true) || str_contains($key, 'electric')) {
            return $ratePerUnit > 0 ? 'electricity' : null;
        }
        if (in_array($key, ['other', 'utility_other'], true)) {
            return $ratePerUnit > 0 ? 'other' : null;
        }

        return null;
    }
}
