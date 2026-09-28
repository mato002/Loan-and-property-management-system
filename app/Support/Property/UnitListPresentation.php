<?php

namespace App\Support\Property;

use App\Models\PropertyUnit;
use Illuminate\Support\HtmlString;

final class UnitListPresentation
{
    public const TONE_OCCUPIED = WorkspaceRowAlert::TONE_OCCUPIED;

    public const TONE_VACANT = WorkspaceRowAlert::TONE_VACANT;

    public const TONE_VACANT_LONG = WorkspaceRowAlert::TONE_VACANT_LONG;

    public const TONE_OWNER_OCCUPIED = WorkspaceRowAlert::TONE_OWNER_OCCUPIED;

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
            self::TONE_OWNER_OCCUPIED => 'owner-occupied',
            self::TONE_OCCUPIED => 'occupied',
            default => 'occupied',
        };

        return new HtmlString(
            '<span class="property-status-pill property-status-pill--'.$modifier.'">'.e(PropertyUnit::statusLabel((string) $unit->status)).'</span>'
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

    public static function hasActiveLease(PropertyUnit $unit): bool
    {
        if (! $unit->relationLoaded('leases')) {
            return false;
        }

        return $unit->leases->isNotEmpty();
    }

    public static function canAssignLease(PropertyUnit $unit, bool $hasActiveLease): bool
    {
        if ($hasActiveLease || $unit->status === PropertyUnit::STATUS_OWNER_OCCUPIED) {
            return false;
        }

        // Occupied units should always have an active lease; link/fix via Open lease, not Add lease.
        if ($unit->status === PropertyUnit::STATUS_OCCUPIED) {
            return false;
        }

        return in_array($unit->status, [
            PropertyUnit::STATUS_VACANT,
            PropertyUnit::STATUS_NOTICE,
        ], true);
    }

    public static function canOpenLease(bool $hasActiveLease): bool
    {
        return $hasActiveLease;
    }

    public static function canPublishListing(PropertyUnit $unit, bool $hasActiveLease): bool
    {
        return $unit->status === PropertyUnit::STATUS_VACANT && ! $hasActiveLease;
    }

    public static function shouldShowMissingLeaseWarning(PropertyUnit $unit, bool $hasActiveLease): bool
    {
        return $unit->status === PropertyUnit::STATUS_OCCUPIED && ! $hasActiveLease;
    }

    public static function canDeleteUnit(PropertyUnit $unit, bool $hasActiveLease): bool
    {
        return ! $hasActiveLease && $unit->status === PropertyUnit::STATUS_VACANT;
    }

    /**
     * @return list<string>
     */
    public static function allowedStatusTransitions(PropertyUnit $unit, bool $hasActiveLease): array
    {
        return match ((string) $unit->status) {
            PropertyUnit::STATUS_VACANT => $hasActiveLease
                ? [PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_NOTICE]
                : [PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_OWNER_OCCUPIED],
            PropertyUnit::STATUS_OCCUPIED => $hasActiveLease
                ? [PropertyUnit::STATUS_NOTICE, PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_OWNER_OCCUPIED]
                : [PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_NOTICE, PropertyUnit::STATUS_OWNER_OCCUPIED],
            PropertyUnit::STATUS_NOTICE => $hasActiveLease
                ? [PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_OCCUPIED]
                : [PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_OCCUPIED],
            PropertyUnit::STATUS_OWNER_OCCUPIED => [PropertyUnit::STATUS_VACANT],
            default => [],
        };
    }
}
