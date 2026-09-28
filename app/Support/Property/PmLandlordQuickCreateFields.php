<?php

namespace App\Support\Property;

final class PmLandlordQuickCreateFields
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'mode' => 'ajax',
            'title' => 'Create landlord',
            'subtitle' => 'Include the legacy code and tax details used on the landlord register.',
            'endpoint' => route('property.landlords.onboard_json'),
            'modalMaxWidth' => '2xl',
            'fields' => [
                ['name' => 'name', 'label' => 'Full name', 'required' => true, 'span' => '2', 'placeholder' => 'e.g. Jane Landlord'],
                ['name' => 'email', 'label' => 'Email (optional if phone provided)', 'type' => 'email', 'required' => false, 'span' => '2', 'placeholder' => 'name@example.com'],
                ['name' => 'phone', 'label' => 'Phone (optional if email provided)', 'required' => false, 'span' => '2', 'placeholder' => 'e.g. 0712345678'],
                ['name' => 'legacy_landlord_code', 'label' => 'Legacy landlord code', 'required' => false, 'placeholder' => 'Import reference'],
                ['name' => 'id_number', 'label' => 'ID / registration number', 'required' => false],
                ['name' => 'kra_pin', 'label' => 'KRA PIN', 'required' => false],
                ['name' => 'address_line', 'label' => 'Postal / physical address', 'required' => false, 'span' => '2'],
            ],
        ];
    }
}
