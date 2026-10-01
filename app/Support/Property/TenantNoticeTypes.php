<?php

namespace App\Support\Property;

use App\Models\PmTenantNotice;
use Illuminate\Support\Facades\Schema;

class TenantNoticeTypes
{
    /** @var array<string, string> */
    public const SAMPLES = [
        'vacate' => 'Vacate',
        'renewal' => 'Renewal',
        'warning' => 'Warning',
        'rent_increase' => 'Rent increase',
        'entry' => 'Entry',
        'arrears_reminder' => 'Arrears reminder',
        'inspection' => 'Inspection',
        'breach' => 'Breach',
        'termination' => 'Termination',
    ];

    /**
     * Sample types plus any type already saved on a notice.
     *
     * @return array<string, string>
     */
    public static function options(?string $selected = null): array
    {
        $options = self::SAMPLES;

        if (Schema::hasTable('pm_tenant_notices')) {
            $used = PmTenantNotice::query()
                ->whereNotNull('notice_type')
                ->where('notice_type', '!=', '')
                ->distinct()
                ->orderBy('notice_type')
                ->pluck('notice_type');

            foreach ($used as $type) {
                $key = self::normalize((string) $type);
                if ($key !== '') {
                    $options[$key] = self::label($key);
                }
            }
        }

        if ($selected !== null && trim($selected) !== '') {
            $key = self::normalize($selected);
            if ($key !== '') {
                $options[$key] = self::label($key);
            }
        }

        asort($options);

        return $options;
    }

    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    public static function label(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
