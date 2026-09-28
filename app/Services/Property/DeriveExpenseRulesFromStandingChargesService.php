<?php

namespace App\Services\Property;

use App\Models\ExpenseDefinition;
use App\Models\PmLease;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Support\Property\LeaseStandingCharges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds Passion expense rules from migrated lease standing charges
 * (EZEN billing-schedule extras on pm_leases.utility_expenses).
 *
 * Property-wide when every occupied unit shares the same amount;
 * otherwise one rule per unit that actually has the charge.
 */
final class DeriveExpenseRulesFromStandingChargesService
{
    /** @var list<string> */
    private const SKIP_TYPES = ['water'];

    /**
     * @return array{
     *     dry_run: bool,
     *     properties: int,
     *     property_wide: int,
     *     unit_scoped: int,
     *     rules: list<array<string, mixed>>,
     *     warnings: list<string>
     * }
     */
    public function derive(int $agentUserId, bool $dryRun = false): array
    {
        $agent = User::query()->find($agentUserId);
        if (! $agent || (string) $agent->property_portal_role !== 'agent') {
            throw new \InvalidArgumentException("User {$agentUserId} is not a property agent account.");
        }

        $properties = Property::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->orderBy('code')
            ->get();

        $proposed = [];
        $warnings = [];

        foreach ($properties as $property) {
            $propertyId = (int) $property->id;
            $snapshot = $this->propertyChargeSnapshot($propertyId);
            if ($snapshot['occupied'] === []) {
                continue;
            }

            foreach ($snapshot['by_type'] as $chargeType => $byUnit) {
                $uniqueAmounts = array_values(array_unique(array_map(
                    static fn (float $amount): string => number_format($amount, 2, '.', ''),
                    array_values($byUnit),
                )));
                $chargedUnitIds = array_map('intval', array_keys($byUnit));
                $allOccupiedCharged = count($chargedUnitIds) === count($snapshot['occupied'])
                    && count(array_diff($snapshot['occupied'], $chargedUnitIds)) === 0;

                if ($allOccupiedCharged && count($uniqueAmounts) === 1) {
                    $proposed[] = $this->ruleRow(
                        $propertyId,
                        null,
                        $chargeType,
                        (float) $uniqueAmounts[0],
                        (string) $property->code,
                    );

                    continue;
                }

                if (count($uniqueAmounts) > 1) {
                    $warnings[] = sprintf(
                        '%s %s: mixed amounts (%s) — unit rules',
                        $property->code,
                        $chargeType,
                        implode(', ', $uniqueAmounts),
                    );
                } elseif (! $allOccupiedCharged) {
                    $warnings[] = sprintf(
                        '%s %s: %d of %d occupied units charged — unit rules (so vacant/zero units stay uncharged)',
                        $property->code,
                        $chargeType,
                        count($chargedUnitIds),
                        count($snapshot['occupied']),
                    );
                }

                foreach ($byUnit as $unitId => $amount) {
                    $proposed[] = $this->ruleRow(
                        $propertyId,
                        (int) $unitId,
                        $chargeType,
                        $amount,
                        (string) $property->code,
                    );
                }
            }
        }

        if (! $dryRun) {
            DB::transaction(function () use ($proposed): void {
                $this->persistRules($proposed);
            });
        }

        return [
            'dry_run' => $dryRun,
            'properties' => collect($proposed)->pluck('property_id')->unique()->count(),
            'property_wide' => collect($proposed)->whereNull('property_unit_id')->count(),
            'unit_scoped' => collect($proposed)->whereNotNull('property_unit_id')->count(),
            'rules' => $proposed,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{occupied: list<int>, by_type: array<string, array<int, float>>}
     */
    private function propertyChargeSnapshot(int $propertyId): array
    {
        $leases = PmLease::query()
            ->withoutGlobalScopes()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->where('property_units.property_id', $propertyId))
            ->with(['units' => fn ($q) => $q->where('property_units.property_id', $propertyId)])
            ->get();

        $occupied = [];
        $byType = [];

        foreach ($leases as $lease) {
            $unitIds = $lease->units->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($unitIds as $unitId) {
                $occupied[$unitId] = $unitId;
            }

            foreach (LeaseStandingCharges::lines($lease) as $line) {
                $type = $this->normalizeChargeType((string) $line['type']);
                if ($type === '' || in_array($type, self::SKIP_TYPES, true)) {
                    continue;
                }
                $amount = round((float) $line['amount'], 2);
                if ($amount <= 0.009) {
                    continue;
                }
                foreach ($unitIds as $unitId) {
                    $byType[$type][$unitId] = $amount;
                }
            }
        }

        ksort($occupied);

        return [
            'occupied' => array_values($occupied),
            'by_type' => $byType,
        ];
    }

    /**
     * @return array{
     *     property_id:int,
     *     property_unit_id:int|null,
     *     charge_key:string,
     *     label:string,
     *     amount_mode:string,
     *     amount_value:float,
     *     property_code:string,
     *     scope:string
     * }
     */
    private function ruleRow(int $propertyId, ?int $unitId, string $chargeType, float $amount, string $propertyCode): array
    {
        return [
            'property_id' => $propertyId,
            'property_unit_id' => $unitId,
            'charge_key' => $chargeType,
            'label' => Str::of($chargeType)->replace('_', ' ')->title()->toString(),
            'amount_mode' => ExpenseDefinition::MODE_FLAT_CHARGE,
            'amount_value' => round($amount, 2),
            'property_code' => $propertyCode,
            'scope' => $unitId === null ? 'property' : 'unit',
        ];
    }

    private function normalizeChargeType(string $raw): string
    {
        $key = strtolower(trim($raw));
        $key = (string) preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        if ($key === 's_charge' || $key === 'scharge' || $key === 'service') {
            return 'service_charge';
        }

        return $key;
    }

    /**
     * @param  list<array<string, mixed>>  $proposed
     */
    private function persistRules(array $proposed): void
    {
        $hasDefinitions = Schema::hasTable('expense_definitions');
        $touchedPropertyIds = collect($proposed)->pluck('property_id')->unique()->map(fn ($id) => (int) $id)->all();
        $touchedKeys = collect($proposed)
            ->map(fn (array $row): string => (string) $row['charge_key'])
            ->unique()
            ->all();

        if ($hasDefinitions && $touchedPropertyIds !== []) {
            ExpenseDefinition::query()
                ->whereIn('property_id', $touchedPropertyIds)
                ->whereIn('charge_key', $touchedKeys)
                ->delete();

            foreach ($proposed as $row) {
                ExpenseDefinition::query()->create([
                    'property_id' => $row['property_id'],
                    'property_unit_id' => $row['property_unit_id'],
                    'charge_key' => $row['charge_key'],
                    'label' => $row['label'],
                    'is_required' => false,
                    'amount_mode' => $row['amount_mode'],
                    'amount_value' => $row['amount_value'],
                    'ledger_account' => null,
                    'sort_order' => $row['charge_key'] === 'garbage' ? 10 : 20,
                    'is_active' => true,
                ]);
            }
        }

        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];

        foreach ($touchedPropertyIds as $propertyId) {
            $pid = (string) $propertyId;
            $existing = is_array($all[$pid] ?? null) ? $all[$pid] : [];
            $kept = array_values(array_filter(
                $existing,
                static function ($row) use ($touchedKeys): bool {
                    if (! is_array($row)) {
                        return false;
                    }
                    $type = strtolower(trim((string) ($row['charge_type'] ?? '')));

                    return $type !== '' && ! in_array($type, $touchedKeys, true);
                },
            ));
            $derived = collect($proposed)
                ->where('property_id', $propertyId)
                ->map(fn (array $row): array => [
                    'property_unit_id' => $row['property_unit_id'],
                    'charge_type' => $row['charge_key'],
                    'label' => $row['label'],
                    'rate_per_unit' => 0.0,
                    'fixed_charge' => $row['amount_value'],
                    'notes' => 'Derived from EZEN billing standing charges',
                ])
                ->values()
                ->all();
            $all[$pid] = array_values(array_merge($kept, $derived));
        }

        PropertyPortalSetting::setValue(
            'utility_property_charge_templates_json',
            json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
