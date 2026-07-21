<?php

namespace App\Support\Property;

use App\Models\PropertyPortalSetting;

final class PmTenantQuickCreateFields
{
    /**
     * @return array<string, array{enabled: bool, required: bool}>
     */
    public static function fieldConfig(): array
    {
        $defaults = [
            'name' => ['enabled' => true, 'required' => true],
            'phone' => ['enabled' => true, 'required' => true],
            'email' => ['enabled' => true, 'required' => false],
            'id_number' => ['enabled' => true, 'required' => false],
            'emergency_contact' => ['enabled' => true, 'required' => false],
        ];

        $raw = PropertyPortalSetting::getValue('system_setup_tenant_fields_json', '');
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
     * @return list<array<string, mixed>>
     */
    public static function modalFields(): array
    {
        $cfg = self::fieldConfig();
        $fields = [];

        if ($cfg['name']['enabled']) {
            $fields[] = [
                'name' => 'name',
                'label' => 'Full name',
                'required' => $cfg['name']['required'],
                'span' => '2',
                'placeholder' => 'e.g. John Tenant',
            ];
        }

        if ($cfg['phone']['enabled']) {
            $fields[] = [
                'name' => 'phone',
                'label' => 'Phone',
                'required' => $cfg['phone']['required'],
                'span' => '2',
                'placeholder' => '+2547…',
            ];
        }

        if ($cfg['email']['enabled']) {
            $fields[] = [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => $cfg['email']['required'],
                'span' => '2',
                'placeholder' => 'name@example.com',
            ];
        }

        if ($cfg['id_number']['enabled']) {
            $fields[] = [
                'name' => 'national_id',
                'label' => 'National ID / reference',
                'required' => $cfg['id_number']['required'],
                'span' => '2',
                'placeholder' => 'e.g. 12345678',
            ];
        }

        $fields[] = [
            'name' => 'risk_level',
            'label' => 'Risk level',
            'type' => 'select',
            'required' => false,
            'options' => [
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'high', 'label' => 'High'],
            ],
        ];

        $fields[] = [
            'name' => 'create_portal_login',
            'label' => 'Create tenant portal login',
            'type' => 'select',
            'required' => false,
            'options' => [
                ['value' => '0', 'label' => 'No'],
                ['value' => '1', 'label' => 'Yes (requires email)'],
            ],
        ];

        $fields[] = [
            'name' => 'notes',
            'label' => 'Notes',
            'type' => 'textarea',
            'required' => false,
            'span' => '2',
            'placeholder' => 'Optional internal notes',
        ];

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function quickCreateConfig(): array
    {
        return [
            'mode' => 'ajax',
            'title' => 'Create tenant',
            'subtitle' => 'Fields match your tenant setup. Portal login requires a unique email.',
            'endpoint' => route('property.tenants.store_json'),
            'fields' => self::modalFields(),
            'modalMaxWidth' => '2xl',
        ];
    }
}
