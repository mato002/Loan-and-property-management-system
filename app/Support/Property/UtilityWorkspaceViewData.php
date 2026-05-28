<?php

namespace App\Support\Property;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\ViewErrorBag;

class UtilityWorkspaceViewData
{
    /**
     * @param  iterable<int, mixed>  $units
     * @param  iterable<int, int>  $waterChargePropertyIds
     * @return array<string, mixed>
     */
    public static function compose(
        Request $request,
        array $filters,
        iterable $units,
        iterable $waterChargePropertyIds,
    ): array {
        /** @var ViewErrorBag $errors */
        $errors = $request->session()->get('errors') ?? new ViewErrorBag();

        $utilityCreateFormHasErrors = $errors->has('charge_type')
            || $errors->has('billing_month')
            || $errors->has('property_id')
            || $errors->has('property_unit_id')
            || $errors->has('label')
            || $errors->has('amount')
            || $errors->has('notes')
            || $errors->has('current_reading')
            || $errors->has('current_readings')
            || $errors->has('previous_reading')
            || $errors->has('previous_readings')
            || $errors->has('rate_per_unit')
            || $errors->has('fixed_charge')
            || $errors->has('due_date');

        /** @var Collection<int, array<string, mixed>> $unitOptions */
        $unitOptions = collect($units)
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'property_id' => (int) $u->property_id,
                'property_name' => (string) ($u->property->name ?? ''),
                'label' => (string) $u->label,
            ])
            ->values();

        $propertyOptions = $unitOptions
            ->unique('property_id')
            ->map(fn ($u) => ['id' => (int) $u['property_id'], 'name' => (string) $u['property_name']])
            ->sortBy('name')
            ->values()
            ->all();

        $waterPropertyIds = collect($waterChargePropertyIds)->map(fn ($id) => (int) $id)->values()->all();

        $waterUnitOptions = $unitOptions
            ->filter(fn ($u) => in_array((int) $u['property_id'], $waterPropertyIds, true))
            ->values();

        $waterPropertyOptions = $waterUnitOptions
            ->unique('property_id')
            ->map(fn ($u) => ['id' => (int) $u['property_id'], 'name' => (string) $u['property_name']])
            ->sortBy('name')
            ->values()
            ->all();

        $oldChargeUnitId = (int) old('property_unit_id', 0);
        $oldChargePropertyId = (int) ($unitOptions->firstWhere('id', $oldChargeUnitId)['property_id'] ?? 0);
        $oldWaterUnitId = (int) old('property_unit_id', 0);
        $oldWaterPropertyId = (int) old('property_id', ($waterUnitOptions->firstWhere('id', $oldWaterUnitId)['property_id'] ?? 0));

        $skipWaterPrevAutofill = false;
        foreach ($errors->keys() as $_wrErrKey) {
            if (! is_string($_wrErrKey)) {
                continue;
            }
            if (in_array($_wrErrKey, ['current_reading', 'previous_reading', 'billing_month', 'current_readings'], true)) {
                $skipWaterPrevAutofill = true;
                break;
            }
            if (str_starts_with($_wrErrKey, 'current_readings.') || str_starts_with($_wrErrKey, 'previous_readings.')) {
                $skipWaterPrevAutofill = true;
                break;
            }
        }

        $wrFilterActiveCount = collect([
            $filters['wr_q'] ?? '',
            $filters['wr_month'] ?? '',
            $filters['wr_status'] ?? '',
            (int) ($filters['wr_property_id'] ?? 0) > 0 ? '1' : '',
        ])->filter(fn ($v) => trim((string) $v) !== '')->count();

        $filterActiveCount = collect([
            $filters['q'] ?? '',
            $filters['charge_type'] ?? '',
            $filters['month'] ?? '',
            $filters['wr_q'] ?? '',
            $filters['wr_month'] ?? '',
            $filters['wr_status'] ?? '',
            (int) ($filters['wr_property_id'] ?? 0) > 0 ? '1' : '',
        ])->filter(fn ($v) => trim((string) $v) !== '')->count();

        return [
            'utilityCreateFormHasErrors' => $utilityCreateFormHasErrors,
            'unitOptions' => $unitOptions,
            'propertyOptions' => $propertyOptions,
            'waterUnitOptions' => $waterUnitOptions,
            'waterPropertyOptions' => $waterPropertyOptions,
            'oldChargeUnitId' => $oldChargeUnitId,
            'oldChargePropertyId' => $oldChargePropertyId,
            'oldWaterUnitId' => $oldWaterUnitId,
            'oldWaterPropertyId' => $oldWaterPropertyId,
            'skipWaterPrevAutofill' => $skipWaterPrevAutofill,
            'wrFilterActiveCount' => $wrFilterActiveCount,
            'filterActiveCount' => $filterActiveCount,
        ];
    }
}
