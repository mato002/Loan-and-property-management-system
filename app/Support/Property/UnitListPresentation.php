<?php

namespace App\Support\Property;

use App\Models\PropertyUnit;
use Illuminate\Support\HtmlString;

final class UnitListPresentation
{
    public const TONE_VACANT = WorkspaceRowAlert::TONE_VACANT;

    public const TONE_VACANT_LONG = WorkspaceRowAlert::TONE_VACANT_LONG;

    public const TONE_NOTICE = WorkspaceRowAlert::TONE_NOTICE;

    public const TONE_ATTENTION = WorkspaceRowAlert::TONE_ATTENTION;

    public const VACANT_LONG_DAYS = WorkspaceRowAlert::VACANT_LONG_DAYS;

    /**
     * @return list<string>
     */
    public static function allowedTones(): array
    {
        return WorkspaceRowAlert::allowedTones();
    }

    public static function tone(PropertyUnit $unit, bool $hasActiveLease): string
    {
        return WorkspaceRowAlert::forUnit($unit, $hasActiveLease);
    }

    public static function statusBadge(PropertyUnit $unit, bool $hasActiveLease): HtmlString
    {
        $tone = self::tone($unit, $hasActiveLease);
        $modifier = match ($tone) {
            self::TONE_ATTENTION => 'attention',
            self::TONE_VACANT_LONG => 'vacant-long',
            self::TONE_VACANT => 'vacant',
            self::TONE_NOTICE => 'notice',
            default => 'occupied',
        };

        return new HtmlString(
            '<span class="property-status-pill property-status-pill--'.$modifier.'">'.e(ucfirst((string) $unit->status)).'</span>'
        );
    }

    public static function tenantCell(PropertyUnit $unit, string $tenantName, bool $hasActiveLease): HtmlString|string
    {
        if ($tenantName !== '') {
            return $tenantName;
        }

        if ($unit->status === PropertyUnit::STATUS_OCCUPIED && ! $hasActiveLease) {
            return new HtmlString('<span class="property-row-alert-text">No active lease</span>');
        }

        return '—';
    }
}
