<?php

namespace App\Services\Property;

use App\Models\ExpenseDefinition;
use App\Models\PmLease;
use App\Models\PmUnitUtilityCharge;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Materializes recurring property-attached expenses (garbage, service charge, etc.)
 * into pm_unit_utility_charges. Water is excluded (meter readings path).
 */
final class AttachedUtilityChargeService
{
    private const SKIP_CHARGE_TYPES = ['water'];

    /**
     * @return array{created: int, skipped_duplicate: int, skipped_no_lease: int, skipped_no_amount: int, skipped_rate_only: int}
     */
    public function materializeForMonth(
        string $billingMonth,
        ?User $actor = null,
        ?int $propertyId = null,
        ?int $utilityOverrideRequestId = null,
    ): array {
        if (! preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
            throw new \InvalidArgumentException('billing_month must be YYYY-MM.');
        }

        app(UtilityPeriodGuardService::class)->assertMutable(
            $billingMonth,
            UtilityPeriodGuardService::ACTION_GENERATE_INVOICE,
            $actor,
            $utilityOverrideRequestId,
        );

        $stats = [
            'created' => 0,
            'skipped_duplicate' => 0,
            'skipped_no_lease' => 0,
            'skipped_no_amount' => 0,
            'skipped_rate_only' => 0,
        ];

        foreach ($this->billingRules($propertyId) as $rule) {
            $chargeType = $this->normalizeChargeType((string) $rule['charge_type']);
            if ($chargeType === '' || in_array($chargeType, self::SKIP_CHARGE_TYPES, true)) {
                continue;
            }

            $unitIds = $this->resolveUnitIds(
                (int) $rule['property_id'],
                $rule['property_unit_id'] !== null ? (int) $rule['property_unit_id'] : null,
            );

            foreach ($unitIds as $unitId) {
                if (! $this->unitHasActiveLease($unitId)) {
                    $stats['skipped_no_lease']++;

                    continue;
                }

                if ($this->chargeExists($unitId, $billingMonth, $chargeType)) {
                    $stats['skipped_duplicate']++;

                    continue;
                }

                $amounts = $this->resolveAmounts($rule);
                if ($amounts === null) {
                    if (($rule['rate_per_unit'] ?? 0) > 0 && ($rule['fixed_charge'] ?? 0) <= 0) {
                        $stats['skipped_rate_only']++;
                    } else {
                        $stats['skipped_no_amount']++;
                    }

                    continue;
                }

                PmUnitUtilityCharge::query()->create([
                    'property_unit_id' => $unitId,
                    'charge_type' => $chargeType,
                    'billing_month' => $billingMonth,
                    'label' => (string) $rule['label'],
                    'amount' => $amounts['amount'],
                    'units_consumed' => $amounts['units_consumed'],
                    'rate_per_unit' => $amounts['rate_per_unit'],
                    'fixed_charge' => $amounts['fixed_charge'],
                    'notes' => trim((string) ($rule['notes'] ?? '')) ?: 'Auto-generated from property expense rule',
                    'is_invoiced' => false,
                    'pm_invoice_id' => null,
                ]);
                $stats['created']++;
            }
        }

        return $stats;
    }

    /**
     * @return list<array{property_id:int,property_unit_id:int|null,charge_type:string,label:string,rate_per_unit:float,fixed_charge:float,notes:string}>
     */
    private function billingRules(?int $propertyId = null): array
    {
        $rules = [];
        $seen = [];

        if (Schema::hasTable('expense_definitions')) {
            $query = ExpenseDefinition::query()
                ->where('is_active', true)
                ->where('amount_mode', '!=', ExpenseDefinition::MODE_PERCENT_RENT);

            if ($propertyId !== null && $propertyId > 0) {
                $query->where('property_id', $propertyId);
            }

            foreach ($query->get() as $definition) {
                $chargeType = $this->normalizeChargeType((string) $definition->charge_key);
                if ($chargeType === '' || in_array($chargeType, self::SKIP_CHARGE_TYPES, true)) {
                    continue;
                }

                $scopeUnitId = $definition->property_unit_id ? (int) $definition->property_unit_id : null;
                $key = $this->ruleKey((int) $definition->property_id, $scopeUnitId, $chargeType);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $isRate = $definition->amount_mode === ExpenseDefinition::MODE_RATE_PER_UNIT;
                $rules[] = [
                    'property_id' => (int) $definition->property_id,
                    'property_unit_id' => $scopeUnitId,
                    'charge_type' => $chargeType,
                    'label' => trim((string) $definition->label) !== ''
                        ? (string) $definition->label
                        : Str::of($chargeType)->replace('_', ' ')->title()->toString(),
                    'rate_per_unit' => $isRate ? (float) $definition->amount_value : 0.0,
                    'fixed_charge' => $isRate ? 0.0 : (float) $definition->amount_value,
                    'notes' => '',
                ];
            }
        }

        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];

        foreach ($all as $pid => $rows) {
            $pid = (int) $pid;
            if ($propertyId !== null && $propertyId > 0 && $pid !== $propertyId) {
                continue;
            }
            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $chargeType = $this->normalizeChargeType((string) ($row['charge_type'] ?? ''));
                if ($chargeType === '' || in_array($chargeType, self::SKIP_CHARGE_TYPES, true)) {
                    continue;
                }

                $scopeUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== ''
                    ? (int) $row['property_unit_id']
                    : null;
                $key = $this->ruleKey($pid, $scopeUnitId, $chargeType);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $label = trim((string) ($row['label'] ?? ''));
                $rules[] = [
                    'property_id' => $pid,
                    'property_unit_id' => $scopeUnitId,
                    'charge_type' => $chargeType,
                    'label' => $label !== '' ? $label : Str::of($chargeType)->replace('_', ' ')->title()->toString(),
                    'rate_per_unit' => is_numeric($row['rate_per_unit'] ?? null) ? max(0.0, (float) $row['rate_per_unit']) : 0.0,
                    'fixed_charge' => is_numeric($row['fixed_charge'] ?? null) ? max(0.0, (float) $row['fixed_charge']) : 0.0,
                    'notes' => trim((string) ($row['notes'] ?? '')),
                ];
            }
        }

        return $rules;
    }

    private function ruleKey(int $propertyId, ?int $unitId, string $chargeType): string
    {
        return $propertyId.'|'.($unitId ?? 'all').'|'.$chargeType;
    }

    /**
     * @return list<int>
     */
    private function resolveUnitIds(int $propertyId, ?int $scopedUnitId): array
    {
        if ($scopedUnitId !== null && $scopedUnitId > 0) {
            return PropertyUnit::query()
                ->where('property_id', $propertyId)
                ->whereKey($scopedUnitId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return PropertyUnit::query()
            ->where('property_id', $propertyId)
            ->orderBy('label')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function unitHasActiveLease(int $unitId): bool
    {
        return PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_units.id', $unitId))
            ->exists();
    }

    private function chargeExists(int $unitId, string $billingMonth, string $chargeType): bool
    {
        return PmUnitUtilityCharge::query()
            ->where('property_unit_id', $unitId)
            ->where('billing_month', $billingMonth)
            ->where('charge_type', $chargeType)
            ->exists();
    }

    /**
     * @param  array{rate_per_unit:float,fixed_charge:float}  $rule
     * @return array{amount:float,units_consumed:?float,rate_per_unit:?float,fixed_charge:?float}|null
     */
    private function resolveAmounts(array $rule): ?array
    {
        $fixed = max(0.0, (float) ($rule['fixed_charge'] ?? 0));
        $rate = max(0.0, (float) ($rule['rate_per_unit'] ?? 0));

        if ($fixed > 0) {
            return [
                'amount' => round($fixed, 2),
                'units_consumed' => null,
                'rate_per_unit' => $rate > 0 ? $rate : null,
                'fixed_charge' => $fixed,
            ];
        }

        // Rate-only rules need a meter reading or manual usage entry.
        return null;
    }

    private function normalizeChargeType(string $raw): string
    {
        $value = (string) Str::of($raw)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

        return $value !== '' ? $value : '';
    }
}
