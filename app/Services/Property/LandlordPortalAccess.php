<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPortalAction;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Support\Collection;

final class LandlordPortalAccess
{
    /** @return Collection<int, int> */
    public static function propertyIds(User $user): Collection
    {
        return $user->landlordProperties()->pluck('properties.id');
    }

    /** @return Collection<int, int> */
    public static function unitIds(User $user): Collection
    {
        $propertyIds = self::propertyIds($user);
        if ($propertyIds->isEmpty()) {
            return collect();
        }

        return PropertyUnit::query()->whereIn('property_id', $propertyIds)->pluck('id');
    }

    public static function ownsProperty(User $user, int $propertyId): bool
    {
        return self::propertyIds($user)->contains($propertyId);
    }

    public static function ownsUnit(User $user, int $unitId): bool
    {
        return self::unitIds($user)->contains($unitId);
    }

    public static function authorizeProperty(User $user, Property $property): void
    {
        if (! self::ownsProperty($user, (int) $property->id)) {
            abort(403);
        }
    }

    public static function authorizeInvoice(User $user, PmInvoice $invoice): void
    {
        $unitId = (int) ($invoice->property_unit_id ?? 0);
        if ($unitId <= 0 || ! self::ownsUnit($user, $unitId)) {
            abort(403);
        }
    }

    public static function authorizeMaintenanceJob(User $user, PmMaintenanceJob $job): void
    {
        $unitId = (int) ($job->request?->property_unit_id ?? 0);
        if ($unitId <= 0 || ! self::ownsUnit($user, $unitId)) {
            abort(403);
        }
    }

    public static function authorizeMaintenanceRequest(User $user, PmMaintenanceRequest $request): void
    {
        $unitId = (int) ($request->property_unit_id ?? 0);
        if ($unitId <= 0 || ! self::ownsUnit($user, $unitId)) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function latestPreferenceContext(User $user, string $actionKey): array
    {
        return (array) (PmPortalAction::query()
            ->where('user_id', $user->id)
            ->where('portal_role', 'landlord')
            ->where('action_key', $actionKey)
            ->latest('id')
            ->value('context') ?? []);
    }
}
