<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class RentDueDayResolver
{
    public const FALLBACK_DUE_DAY = 5;

    /**
     * Resolved calendar day (1–31) for rent due on recurring invoices.
     */
    public function resolveDueDay(PmLease $lease): int
    {
        if ($lease->rent_due_day !== null && (int) $lease->rent_due_day > 0) {
            return self::normalizeDueDay((int) $lease->rent_due_day);
        }

        $property = $this->resolveProperty($lease);
        if ($property?->rent_due_day !== null && (int) $property->rent_due_day > 0) {
            return self::normalizeDueDay((int) $property->rent_due_day);
        }

        return self::normalizeDueDay($this->systemDefaultDueDay());
    }

    public function systemDefaultDueDay(): int
    {
        $stored = trim((string) PropertyPortalSetting::getValue('property_rent_due_day_default', ''));
        if ($stored !== '' && ctype_digit($stored)) {
            return self::normalizeDueDay((int) $stored);
        }

        $configured = config('property.rent_due_day_default', self::FALLBACK_DUE_DAY);

        return self::normalizeDueDay((int) $configured);
    }

    /**
     * Due date string (Y-m-d) for a billing-month period start.
     */
    public function dueDateForBillingMonth(PmLease $lease, CarbonInterface $periodStart): string
    {
        $period = Carbon::parse($periodStart)->startOfDay();

        return self::dueDateForDayInMonth($period, $this->resolveDueDay($lease));
    }

    public static function dueDateForDayInMonth(CarbonInterface $periodStart, int $dueDay): string
    {
        $period = Carbon::parse($periodStart)->startOfDay();
        $day = min(self::normalizeDueDay($dueDay), (int) $period->daysInMonth);

        return $period->copy()->day($day)->toDateString();
    }

    public static function normalizeDueDay(int $day): int
    {
        return max(1, min(31, $day));
    }

    private function resolveProperty(PmLease $lease): ?Property
    {
        if ($lease->relationLoaded('units')) {
            $unit = $lease->units->first();
            if ($unit?->relationLoaded('property')) {
                return $unit->property;
            }
            if ($unit?->property_id) {
                return Property::query()->find((int) $unit->property_id);
            }
        }

        $propertyId = $lease->units()->value('property_id');

        return $propertyId ? Property::query()->find((int) $propertyId) : null;
    }
}
