<?php

namespace App\Support\Property;

use App\Models\PmTenant;
use Illuminate\Support\HtmlString;

final class TenantCompliancePresentation
{
    /**
     * @return list<string>
     */
    public static function gaps(PmTenant $tenant): array
    {
        $gaps = [];

        if (trim((string) $tenant->national_id) === '') {
            $gaps[] = 'Missing ID';
        }
        if (trim((string) $tenant->phone) === '') {
            $gaps[] = 'Missing phone';
        }
        if (trim((string) $tenant->email) === '') {
            $gaps[] = 'Missing email';
        }
        if (trim((string) $tenant->emergency_contact) === '') {
            $gaps[] = 'Missing emergency contact';
        }
        if (strtolower((string) ($tenant->risk_level ?? 'normal')) === 'high') {
            $gaps[] = 'High risk';
        }
        if ($tenant->user_id === null) {
            $gaps[] = 'No portal login';
        }

        return $gaps;
    }

    public static function gapsCell(PmTenant $tenant): HtmlString|string
    {
        $gaps = self::gaps($tenant);
        if ($gaps === []) {
            return new HtmlString('<span class="text-emerald-700 dark:text-emerald-300">Complete</span>');
        }

        return new HtmlString(
            implode('<br>', array_map(
                static fn (string $gap): string => '<span class="text-amber-800 dark:text-amber-200">'.e($gap).'</span>',
                $gaps,
            )),
        );
    }

    public static function riskCell(PmTenant $tenant): HtmlString
    {
        $risk = strtolower(trim((string) ($tenant->risk_level ?? 'normal')));
        $label = ucfirst($risk !== '' ? $risk : 'normal');
        $modifier = match ($risk) {
            'high' => 'attention',
            'medium' => 'notice',
            default => 'occupied',
        };

        return new HtmlString(
            '<span class="property-status-pill property-status-pill--'.$modifier.'">'.e($label).'</span>'
        );
    }

    public static function portalCell(PmTenant $tenant): HtmlString|string
    {
        if ($tenant->user_id !== null) {
            return new HtmlString('<span class="text-emerald-700 dark:text-emerald-300">Yes</span>');
        }

        return 'No';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function gapFilterOptions(): array
    {
        return [
            ['value' => 'any', 'label' => 'Any gap'],
            ['value' => 'missing_id', 'label' => 'Missing ID'],
            ['value' => 'missing_phone', 'label' => 'Missing phone'],
            ['value' => 'missing_email', 'label' => 'Missing email'],
            ['value' => 'missing_emergency', 'label' => 'Missing emergency contact'],
            ['value' => 'high_risk', 'label' => 'High risk'],
            ['value' => 'no_portal', 'label' => 'No portal login'],
            ['value' => 'complete', 'label' => 'Complete profile'],
        ];
    }
}
