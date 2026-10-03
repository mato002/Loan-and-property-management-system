<?php

namespace App\Services\Property;

use App\Exceptions\Property\UtilityPeriodClosedException;
use App\Models\Employee;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmWaterReading;
use App\Models\Property;
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
        $waterLatest = $this->latestWater($unitIds);
        $waterThisMonth = $this->waterRecordedThisMonth($unitIds, $month);
        $chargeLatest = $this->latestCharges($unitIds, $month);

        $grouped = $units->groupBy('property_id');
        $rows = [];
        foreach ($properties as $property) {
            $propertyUnits = [];
            foreach ($grouped->get($property->id, collect()) as $unit) {
                $unitId = (int) $unit->id;
                $water = $waterLatest[$unitId] ?? null;
                $electric = $chargeLatest[$unitId]['electricity'] ?? null;
                $other = $chargeLatest[$unitId]['other'] ?? null;
                $propertyUnits[] = [
                    'id' => $unitId,
                    'label' => (string) $unit->label,
                    'meters' => [
                        $this->meterRow('water', 'Water', (string) ($unit->water_meter ?? ''), $water, isset($waterThisMonth[$unitId])),
                        $this->meterRow('electricity', 'Electricity', (string) ($unit->electricity_meter ?? ''), $electric, (bool) ($electric['recorded_this_month'] ?? false)),
                        $this->meterRow('other', 'Other utility', '', $other, (bool) ($other['recorded_this_month'] ?? false)),
                    ],
                ];
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
     * @param  array{previous?: float, current?: float, rate?: float, fixed?: float}|null  $latest
     * @return array<string, mixed>
     */
    private function meterRow(string $kind, string $label, string $meterNo, ?array $latest, bool $recorded): array
    {
        return [
            'kind' => $kind,
            'label' => $label,
            'meter_no' => $meterNo,
            'previous' => round((float) ($latest['current'] ?? 0), 3),
            'rate' => round((float) ($latest['rate'] ?? 0), 2),
            'fixed' => round((float) ($latest['fixed'] ?? 0), 2),
            'already_recorded' => $recorded,
        ];
    }

    /**
     * @param  list<int>  $unitIds
     * @return array<int, array{current: float, rate: float, fixed: float}>
     */
    private function latestWater(array $unitIds): array
    {
        if ($unitIds === [] || ! Schema::hasTable('pm_water_readings')) {
            return [];
        }

        $rows = PmWaterReading::query()
            ->whereIn('property_unit_id', $unitIds)
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->get(['property_unit_id', 'current_reading', 'rate_per_unit', 'fixed_charge', 'billing_month']);

        $latest = [];
        foreach ($rows as $row) {
            $unitId = (int) $row->property_unit_id;
            if (isset($latest[$unitId])) {
                continue;
            }
            $latest[$unitId] = [
                'current' => (float) $row->current_reading,
                'rate' => (float) $row->rate_per_unit,
                'fixed' => (float) $row->fixed_charge,
            ];
        }

        return $latest;
    }

    /**
     * @param  list<int>  $unitIds
     * @return array<int, true>
     */
    private function waterRecordedThisMonth(array $unitIds, string $month): array
    {
        if ($unitIds === [] || ! Schema::hasTable('pm_water_readings')) {
            return [];
        }

        return PmWaterReading::query()
            ->whereIn('property_unit_id', $unitIds)
            ->where('billing_month', $month)
            ->pluck('property_unit_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  list<int>  $unitIds
     * @return array<int, array<string, array{current: float, rate: float, fixed: float, recorded_this_month: bool}>>
     */
    private function latestCharges(array $unitIds, string $month): array
    {
        if ($unitIds === [] || ! Schema::hasTable('pm_unit_utility_charges')) {
            return [];
        }

        $hasCurrent = Schema::hasColumn('pm_unit_utility_charges', 'current_reading');
        $columns = ['property_unit_id', 'charge_type', 'billing_month', 'rate_per_unit', 'fixed_charge', 'notes'];
        if ($hasCurrent) {
            $columns[] = 'current_reading';
        }

        $rows = PmUnitUtilityCharge::query()
            ->whereIn('property_unit_id', $unitIds)
            ->whereIn('charge_type', ['electricity', 'other'])
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->get($columns);

        $latest = [];
        foreach ($rows as $row) {
            $unitId = (int) $row->property_unit_id;
            $type = (string) $row->charge_type;
            if (isset($latest[$unitId][$type])) {
                if ((string) $row->billing_month === ($latest[$unitId][$type]['month'] ?? '')) {
                    $latest[$unitId][$type]['recorded_this_month'] = true;
                }

                continue;
            }
            $current = $hasCurrent ? (float) ($row->current_reading ?? 0) : 0.0;
            if ($current <= 0 && preg_match('/meter\s+([0-9.]+)\s+→\s+([0-9.]+)/', (string) $row->notes, $match) === 1) {
                $current = (float) $match[2];
            }
            $latest[$unitId][$type] = [
                'current' => $current,
                'rate' => (float) $row->rate_per_unit,
                'fixed' => (float) $row->fixed_charge,
                'month' => (string) $row->billing_month,
                'recorded_this_month' => (string) $row->billing_month === $month,
            ];
        }

        return $latest;
    }
}
