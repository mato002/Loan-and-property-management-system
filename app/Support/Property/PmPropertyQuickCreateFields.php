<?php

namespace App\Support\Property;

use App\Models\PmFieldOfficer;

final class PmPropertyQuickCreateFields
{
    /**
     * @param  iterable<int, array{id?: mixed, label?: string, value?: mixed}>|null  $fieldOfficers
     * @return array<string, mixed>
     */
    public static function config(?iterable $fieldOfficers = null): array
    {
        $officerOptions = [];
        foreach ($fieldOfficers ?? [] as $officer) {
            if (! is_array($officer)) {
                continue;
            }
            $id = $officer['id'] ?? $officer['value'] ?? null;
            $label = $officer['label'] ?? null;
            if ($id === null || $label === null) {
                continue;
            }
            $officerOptions[] = ['value' => $id, 'label' => $label];
        }

        if ($officerOptions === [] && auth()->check()) {
            $officerOptions = PmFieldOfficer::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (PmFieldOfficer $officer) => [
                    'value' => $officer->id,
                    'label' => $officer->name,
                ])
                ->all();
        }

        $fields = [
            ['name' => 'name', 'label' => 'Property name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Prady Court'],
            ['name' => 'code', 'label' => 'Property code', 'required' => false, 'span' => '2', 'placeholder' => 'Auto if blank — e.g. A00039A'],
            ['name' => 'address_line', 'label' => 'Address', 'required' => false, 'span' => '2', 'placeholder' => 'Street / building'],
            ['name' => 'city', 'label' => 'City', 'required' => false, 'span' => '2', 'placeholder' => 'Nairobi'],
            ['name' => 'commission_percent', 'label' => 'Commission %', 'required' => false, 'type' => 'number', 'span' => '2', 'placeholder' => 'Uses default if blank'],
            ['name' => 'rent_due_day', 'label' => 'Rent due day (1–31)', 'required' => false, 'type' => 'number', 'placeholder' => 'Blank = system default'],
        ];

        if ($officerOptions !== []) {
            $fields[] = [
                'name' => 'field_officer_id',
                'label' => 'Field officer',
                'required' => false,
                'type' => 'select',
                'placeholder' => 'Unassigned',
                'options' => $officerOptions,
            ];
        }

        return [
            'mode' => 'ajax',
            'title' => 'Create property',
            'subtitle' => 'Same fields as the property register (code, commission, field officer, location).',
            'endpoint' => route('property.properties.store_json'),
            'fields' => $fields,
            'modalMaxWidth' => '2xl',
        ];
    }
}
