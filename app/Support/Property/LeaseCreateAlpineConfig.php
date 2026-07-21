<?php

namespace App\Support\Property;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

class LeaseCreateAlpineConfig
{
    /**
     * @param  ViewErrorBag|MessageBag|null  $errors
     * @param  array<string, string>  $arrearsTypeLabels
     * @return array<string, mixed>
     */
    public static function build($errors = null, array $arrearsTypeLabels = []): array
    {
        $bag = self::resolveErrorBag($errors);

        return [
            'openOptional' => $bag?->hasAny([
                'utility_expenses', 'utility_expense_type', 'utility_expense_rate',
                'additional_deposits', 'additional_deposits.*.label', 'additional_deposits.*.amount',
                'terms_summary',
            ]) ?? false,
            'openArrears' => $bag?->hasAny([
                'opening_arrears', 'opening_arrears.*',
                'opening_rent_arrears', 'opening_rent_arrears_period', 'opening_rent_arrears_details',
                'opening_deposit_arrears', 'opening_deposit_arrears.*',
                'opening_arrears_manual_total', 'opening_arrears_as_of_date', 'opening_arrears_note',
            ]) ?? false,
            'openArrearsSection' => $bag?->hasAny([
                'opening_arrears_items', 'opening_arrears_items.*.type', 'opening_arrears_items.*.label',
                'opening_arrears_items.*.period', 'opening_arrears_items.*.amount', 'opening_arrears_amount',
                'opening_arrears_as_of', 'opening_arrears_notes',
            ]) || count((array) old('opening_arrears_items', [])) > 0
                || (float) old('opening_arrears_amount', 0) > 0
                || trim((string) old('opening_arrears_notes', '')) !== '',
            'arrearsItems' => array_values((array) old('opening_arrears_items', [])),
            'arrearsTypeLabels' => $arrearsTypeLabels,
        ];
    }

    /**
     * @param  ViewErrorBag|MessageBag|null  $errors
     */
    private static function resolveErrorBag($errors): ?MessageBag
    {
        if ($errors instanceof MessageBag) {
            return $errors;
        }

        if ($errors instanceof ViewErrorBag) {
            return $errors->getBag('default');
        }

        return null;
    }
}
