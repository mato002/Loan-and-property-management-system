<?php

namespace App\Support\Property;

final class PmUnitQuickCreateFields
{
    /**
     * @param  iterable<int, array{value: mixed, label: string}>  $propertyOptions
     * @return array<string, mixed>
     */
    public static function config(iterable $propertyOptions): array
    {
        $properties = [];
        foreach ($propertyOptions as $option) {
            if (! is_array($option)) {
                continue;
            }
            $properties[] = [
                'value' => $option['value'] ?? '',
                'label' => $option['label'] ?? (string) ($option['value'] ?? ''),
            ];
        }

        return [
            'mode' => 'ajax',
            'title' => 'Add unit',
            'subtitle' => 'Capture rent, market rent, floor, and listing details from the unit register.',
            'endpoint' => route('property.units.store_json'),
            'modalMaxWidth' => '2xl',
            'fields' => [
                [
                    'name' => 'property_id',
                    'label' => 'Property',
                    'required' => true,
                    'span' => '2',
                    'type' => 'select',
                    'placeholder' => 'Select property',
                    'options' => $properties,
                ],
                ['name' => 'label', 'label' => 'Unit label', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. A1'],
                [
                    'name' => 'unit_type',
                    'label' => 'Unit type',
                    'required' => false,
                    'type' => 'select',
                    'options' => [
                        ['value' => 'apartment', 'label' => 'Apartment'],
                        ['value' => 'single_room', 'label' => 'Single room'],
                        ['value' => 'bedsitter', 'label' => 'Bedsitter'],
                        ['value' => 'studio', 'label' => 'Studio'],
                        ['value' => 'bungalow', 'label' => 'Bungalow'],
                        ['value' => 'maisonette', 'label' => 'Maisonette'],
                        ['value' => 'villa', 'label' => 'Villa'],
                        ['value' => 'townhouse', 'label' => 'Townhouse'],
                        ['value' => 'commercial', 'label' => 'Commercial'],
                    ],
                ],
                ['name' => 'bedrooms', 'label' => 'Bedrooms', 'required' => false, 'type' => 'number', 'placeholder' => '0 for bedsitter / studio'],
                ['name' => 'rent_amount', 'label' => 'Contract rent (KES)', 'required' => false, 'type' => 'number', 'placeholder' => '0.00'],
                ['name' => 'market_rent', 'label' => 'Market rent (KES)', 'required' => false, 'type' => 'number', 'placeholder' => 'Listing / target rent'],
                ['name' => 'floor', 'label' => 'Floor', 'required' => false, 'placeholder' => 'e.g. Ground, 1, 2'],
                ['name' => 'legacy_area', 'label' => 'Area (sq ft)', 'required' => false, 'type' => 'number'],
                ['name' => 'available_from', 'label' => 'Available from', 'required' => false, 'type' => 'date'],
                [
                    'name' => 'furnished',
                    'label' => 'Furnished',
                    'required' => false,
                    'type' => 'select',
                    'options' => [
                        ['value' => '0', 'label' => 'No'],
                        ['value' => '1', 'label' => 'Yes'],
                    ],
                ],
                [
                    'name' => 'status',
                    'label' => 'Status',
                    'required' => false,
                    'type' => 'select',
                    'options' => [
                        ['value' => 'vacant', 'label' => 'Vacant'],
                        ['value' => 'occupied', 'label' => 'Occupied'],
                        ['value' => 'notice', 'label' => 'Notice'],
                    ],
                ],
            ],
        ];
    }
}
