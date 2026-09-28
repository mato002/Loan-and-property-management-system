<?php

namespace App\Support\Property;

use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PublicApplyCatalog
{
    /** @var list<string> */
    private const COUNTRY_LABELS = [
        'kenya', 'ke', 'uganda', 'tanzania', 'rwanda', 'africa', 'east africa', 'n/a', 'na', 'none', 'all',
    ];

    /** @var list<int> */
    public const RENT_BANDS = [10000, 15000, 20000, 30000, 50000, 80000, 120000];

    /**
     * @param  Collection<int, PropertyUnit>  $units
     * @return list<array{
     *     city: string,
     *     city_label: string,
     *     area: string,
     *     property_id: int,
     *     property: string,
     *     unit_type: string,
     *     unit_type_label: string,
     *     bedrooms: int,
     *     rent: float
     * }>
     */
    public static function rows(Collection $units): array
    {
        $rows = [];
        foreach ($units as $unit) {
            $property = $unit->property;
            if (! $property) {
                continue;
            }

            $city = trim((string) $property->city);
            if ($city === '') {
                $city = 'Other';
            }

            $rows[] = [
                'city' => $city,
                'city_label' => self::cityLabel($city),
                'broad_city' => self::isCountryLevel($city),
                'area' => self::areaLabel($property),
                'property_id' => (int) $property->id,
                'property' => (string) $property->name,
                'property_label' => self::publicBuildingName($property),
                'unit_type' => (string) ($unit->unit_type ?: ''),
                'unit_type_label' => $unit->unitTypeLabel(),
                'bedrooms' => (int) $unit->bedrooms,
                'rent' => $unit->listedRentAmount(),
            ];
        }

        return $rows;
    }

    public static function areaLabel(Property $property): string
    {
        $city = trim((string) $property->city);
        $name = trim((string) $property->name);
        $address = trim(preg_replace('/\s+/', ' ', (string) $property->address_line) ?? '');

        $parts = $address === ''
            ? []
            : array_values(array_filter(array_map('trim', preg_split('/[,\/|]+/', $address) ?: [])));

        $skip = array_map('strtolower', array_filter([$city, ...self::COUNTRY_LABELS]));
        $parts = array_values(array_filter(
            $parts,
            fn (string $part) => $part !== '' && ! in_array(mb_strtolower($part), $skip, true)
        ));

        if ($parts !== []) {
            return $parts[0];
        }

        if ($address !== '' && strcasecmp($address, $city) !== 0 && ! self::isCountryLevel($address)) {
            return $address;
        }

        $alias = self::buildingAlias($name);
        if ($alias !== '') {
            return $alias;
        }

        return $name !== '' ? $name : 'Other listings';
    }

    public static function publicBuildingName(Property $property): string
    {
        $alias = self::buildingAlias((string) $property->name);
        if ($alias !== '') {
            return $alias;
        }

        $name = trim((string) $property->name);

        return $name !== '' ? $name : 'Listed building';
    }

    public static function buildingAlias(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        if (preg_match_all('/\(([^)]+)\)/', $name, $matches) && $matches[1] !== []) {
            $alias = trim((string) end($matches[1]));
            if ($alias !== '' && ! self::isCountryLevel($alias)) {
                return self::prettyPlace($alias);
            }
        }

        if (preg_match('/\s[-–]\s([^-–()]{2,})$/', $name, $dashMatch)) {
            $alias = trim($dashMatch[1]);
            if ($alias !== '' && ! self::isCountryLevel($alias)) {
                return self::prettyPlace($alias);
            }
        }

        return '';
    }

    public static function prettyPlace(string $value): string
    {
        $value = trim($value);
        if ($value === mb_strtoupper($value) && mb_strlen($value) > 1) {
            return Str::title(mb_strtolower($value));
        }

        return $value;
    }

    public static function cityLabel(string $city): string
    {
        $trimmed = trim($city);
        if ($trimmed === '') {
            return 'Other';
        }

        if (self::isCountryLevel($trimmed)) {
            return 'Other listed homes';
        }

        if ($trimmed === mb_strtoupper($trimmed) && mb_strlen($trimmed) > 1) {
            return Str::title(mb_strtolower($trimmed));
        }

        return $trimmed;
    }

    public static function isCountryLevel(string $city): bool
    {
        return in_array(mb_strtolower(trim($city)), self::COUNTRY_LABELS, true);
    }

    public static function layoutKey(int $bedrooms): string
    {
        if ($bedrooms <= 0) {
            return '0';
        }
        if ($bedrooms >= 3) {
            return '3plus';
        }

        return (string) $bedrooms;
    }

    public static function layoutLabel(string $key): string
    {
        return match ($key) {
            '0' => 'Studio / bedsitter',
            '1' => '1 bedroom',
            '2' => '2 bedrooms',
            '3plus' => '3+ bedrooms',
            default => 'Any layout',
        };
    }
}
