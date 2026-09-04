<?php

namespace App\Support\Property;

use App\Models\PropertyUnit;
use Illuminate\Support\Collection;

final class PropertyUnitOccupancyStats
{
    /**
     * @param  list<int>  $propertyIds
     * @return Collection<int, array{
     *     units_total: int,
     *     units_occupied: int,
     *     units_vacant: int,
     *     units_owner_occupied: int,
     *     units_notice: int
     * }>
     */
    public static function byPropertyIds(array $propertyIds): Collection
    {
        if ($propertyIds === []) {
            return collect();
        }

        return PropertyUnit::query()
            ->whereIn('property_id', $propertyIds)
            ->selectRaw('property_id')
            ->selectRaw('COUNT(*) as units_total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as units_occupied', [PropertyUnit::STATUS_OCCUPIED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as units_vacant', [PropertyUnit::STATUS_VACANT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as units_owner_occupied', [PropertyUnit::STATUS_OWNER_OCCUPIED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as units_notice', [PropertyUnit::STATUS_NOTICE])
            ->groupBy('property_id')
            ->get()
            ->keyBy('property_id')
            ->map(fn ($row) => [
                'units_total' => (int) $row->units_total,
                'units_occupied' => (int) $row->units_occupied,
                'units_vacant' => (int) $row->units_vacant,
                'units_owner_occupied' => (int) $row->units_owner_occupied,
                'units_notice' => (int) $row->units_notice,
            ]);
    }

    /**
     * @return array{
     *     units_total: int,
     *     units_occupied: int,
     *     units_vacant: int,
     *     units_owner_occupied: int,
     *     units_notice: int
     * }
     */
    public static function forProperty(int $propertyId): array
    {
        return self::byPropertyIds([$propertyId])->get($propertyId) ?? [
            'units_total' => 0,
            'units_occupied' => 0,
            'units_vacant' => 0,
            'units_owner_occupied' => 0,
            'units_notice' => 0,
        ];
    }
}
