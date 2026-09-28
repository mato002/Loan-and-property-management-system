<?php

namespace App\Support\Property;

use App\Models\DepositDefinition;
use App\Models\ExpenseDefinition;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitPublicImage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PublicListingPage
{
    /**
     * @return array{
     *     gallery: list<array{url: string, type: 'image'|'video'}>,
     *     videos: list<array{url: string, type: 'video'}>,
     *     floorPlans: list<array{url: string, type: 'image'}>,
     *     hasUploadedMedia: bool,
     *     facts: list<array{label: string, value: string}>,
     *     description: string,
     *     descriptionIsCustom: bool,
     *     amenities: list<string>,
     *     moveInLines: list<array{label: string, amount: float, hint: string}>,
     *     monthlyExtras: list<array{label: string, amount: float}>,
     *     moveInTotal: float,
     *     rent: float,
     *     availableLabel: string,
     *     neighborhood: array{address: string, city: string, maps_query: string, maps_url: string, embed_url: string},
     *     trust: list<string>,
     *     whatsappDigits: string,
     *     whatsappUrl: string,
     *     shareText: string,
     *     listingUrl: string
     * }
     */
    public static function assemble(PropertyUnit $unit, string $listingUrl, string $companyName, string $whatsapp, string $phone): array
    {
        $rent = $unit->listedRentAmount();
        $gallery = $unit->relationLoaded('publicImages')
            ? $unit->publicImages
            : $unit->publicImages()->get();

        $items = $gallery
            ->map(fn (PropertyUnitPublicImage $image) => $image->toGalleryItem())
            ->filter(fn (array $item) => ($item['url'] ?? '') !== '')
            ->values()
            ->all();

        $videos = array_values(array_filter($items, fn (array $item) => ($item['type'] ?? '') === 'video'));
        $floorPlans = $gallery
            ->filter(fn (PropertyUnitPublicImage $image) => $image->isImage() && self::looksLikeFloorPlan((string) $image->path))
            ->map(fn (PropertyUnitPublicImage $image) => $image->toGalleryItem())
            ->values()
            ->all();

        $addr = trim(collect([$unit->property?->address_line, $unit->property?->city])->filter()->implode(', '));
        $mapsQuery = $addr !== '' ? $addr : (string) ($unit->property?->name ?? '');
        $whatsappDigits = PropertyWorkspaceBranding::whatsappDigitsForWeb($whatsapp, $phone);
        $shareText = $unit->property?->name.' — Unit '.$unit->label
            .($rent > 0 ? ' · KES '.number_format($rent, 0).'/month' : '')
            .($mapsQuery !== '' ? ' in '.$mapsQuery : '')
            .'. '.$listingUrl;
        $waMessage = 'Hi '.$companyName.', I am interested in '.$unit->property?->name
            .' — Unit '.$unit->label
            .($rent > 0 ? ' (KES '.number_format($rent, 0).'/month)' : '')
            .'. Listing: '.$listingUrl
            .'. Can we schedule a viewing?';

        $moveIn = self::moveInCosts($unit, $rent);
        $customDescription = trim((string) $unit->public_listing_description);

        return [
            'gallery' => $items,
            'videos' => $videos,
            'floorPlans' => $floorPlans,
            'hasUploadedMedia' => $items !== [],
            'facts' => self::facts($unit),
            'description' => $customDescription !== '' ? $customDescription : self::generatedDescription($unit, $rent, $addr),
            'descriptionIsCustom' => $customDescription !== '',
            'amenities' => self::amenityLabels($unit),
            'moveInLines' => $moveIn['lines'],
            'monthlyExtras' => $moveIn['monthly'],
            'moveInTotal' => $moveIn['total'],
            'rent' => $rent,
            'availableLabel' => self::availableLabel($unit),
            'neighborhood' => [
                'address' => $addr,
                'city' => (string) ($unit->property?->city ?? ''),
                'maps_query' => $mapsQuery,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($mapsQuery),
                'embed_url' => 'https://maps.google.com/maps?q='.rawurlencode($mapsQuery).'&t=&z=15&ie=UTF8&iwloc=&output=embed',
            ],
            'trust' => self::trustLines($unit, $companyName),
            'whatsappDigits' => $whatsappDigits,
            'whatsappUrl' => $whatsappDigits !== ''
                ? 'https://wa.me/'.$whatsappDigits.'?text='.rawurlencode($waMessage)
                : '',
            'shareText' => $shareText,
            'listingUrl' => $listingUrl,
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function facts(PropertyUnit $unit): array
    {
        $facts = [
            ['label' => 'Type', 'value' => $unit->unitTypeLabel()],
            ['label' => 'Layout', 'value' => $unit->bedroomsLabel()],
            ['label' => 'Unit', 'value' => (string) $unit->label],
        ];

        $floor = trim((string) $unit->floor);
        if ($floor !== '') {
            $facts[] = ['label' => 'Floor', 'value' => $floor];
        }

        $area = (float) ($unit->legacy_area ?? 0);
        if ($area > 0) {
            $facts[] = ['label' => 'Size', 'value' => rtrim(rtrim(number_format($area, 1), '0'), '.').' sqm'];
        }

        if ($unit->furnished !== null) {
            $facts[] = ['label' => 'Furnished', 'value' => $unit->furnished ? 'Yes' : 'No'];
        }

        $facts[] = ['label' => 'Available', 'value' => self::availableLabel($unit)];

        return $facts;
    }

    public static function availableLabel(PropertyUnit $unit): string
    {
        if ($unit->available_from) {
            if ($unit->available_from->isFuture()) {
                return 'From '.$unit->available_from->format('d M Y');
            }

            return 'Available now';
        }

        return 'Available now';
    }

    /**
     * @return array{lines: list<array{label: string, amount: float, hint: string}>, monthly: list<array{label: string, amount: float}>, total: float}
     */
    public static function moveInCosts(PropertyUnit $unit, float $rent): array
    {
        $lines = [];
        if ($rent > 0) {
            $lines[] = [
                'label' => 'First month rent',
                'amount' => $rent,
                'hint' => 'Asking rent',
            ];
        }

        foreach (self::activeDepositDefinitions($unit) as $definition) {
            $amount = self::resolvedChargeAmount($definition->amount_mode, (float) $definition->amount_value, $rent);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = [
                'label' => (string) ($definition->label ?: Str::headline((string) $definition->deposit_key)),
                'amount' => $amount,
                'hint' => $definition->is_refundable ? 'Refundable deposit' : 'Deposit',
            ];
        }

        $monthly = [];
        foreach (self::activeExpenseDefinitions($unit) as $definition) {
            $amount = self::resolvedChargeAmount($definition->amount_mode, (float) $definition->amount_value, $rent);
            if ($amount <= 0) {
                continue;
            }
            $monthly[] = [
                'label' => (string) ($definition->label ?: Str::headline((string) $definition->charge_key)),
                'amount' => $amount,
            ];
        }

        $total = array_reduce($lines, fn (float $sum, array $line) => $sum + $line['amount'], 0.0);

        return [
            'lines' => $lines,
            'monthly' => $monthly,
            'total' => $total,
        ];
    }

    /**
     * @return list<string>
     */
    public static function amenityLabels(PropertyUnit $unit): array
    {
        $labels = [];
        foreach ($unit->amenities as $amenity) {
            $name = trim((string) $amenity->name);
            if ($name !== '') {
                $labels[] = $name;
            }
        }

        if ($unit->furnished) {
            $labels[] = 'Furnished';
        }

        $area = (float) ($unit->legacy_area ?? 0);
        if ($area > 0) {
            $labels[] = rtrim(rtrim(number_format($area, 1), '0'), '.').' sqm';
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return list<string>
     */
    public static function trustLines(PropertyUnit $unit, string $companyName): array
    {
        $lines = [];
        $property = $unit->property;
        if ($property?->created_at) {
            $lines[] = 'Managed by '.$companyName.' since '.$property->created_at->format('Y');
        } else {
            $lines[] = 'Professionally managed by '.$companyName;
        }

        $unitCount = $property?->units()->count() ?? 0;
        if ($unitCount > 1) {
            $vacant = $property->units()->where('status', PropertyUnit::STATUS_VACANT)->count();
            $lines[] = $unitCount.' units in this building'
                .($vacant > 0 ? ' · '.$vacant.' vacant now' : '');
        }

        $lines[] = 'We reply on WhatsApp or phone, typically the same day';

        return $lines;
    }

    public static function generatedDescription(PropertyUnit $unit, float $rent, string $address): string
    {
        $parts = [];
        $parts[] = $unit->property?->name.' Unit '.$unit->label.' is a vacant '.$unit->unitTypeLabel()
            .($address !== '' ? ' in '.$address : '')
            .'.';

        $layout = $unit->bedroomsLabel();
        $bits = [$layout];
        $floor = trim((string) $unit->floor);
        if ($floor !== '') {
            $bits[] = 'floor '.$floor;
        }
        $area = (float) ($unit->legacy_area ?? 0);
        if ($area > 0) {
            $bits[] = rtrim(rtrim(number_format($area, 1), '0'), '.').' sqm';
        }
        if ($unit->furnished !== null) {
            $bits[] = $unit->furnished ? 'furnished' : 'unfurnished';
        }
        $parts[] = 'This unit is '.implode(', ', $bits).'.';

        if ($rent > 0) {
            $parts[] = 'Asking rent is KES '.number_format($rent, 0).' per month.';
        }

        $parts[] = self::availableLabel($unit).'. Book a viewing to confirm the current condition of the unit.';

        return implode(' ', $parts);
    }

    public static function resolvedChargeAmount(?string $mode, float $value, float $rent): float
    {
        if (in_array((string) $mode, ['percent_rent', ExpenseDefinition::MODE_PERCENT_RENT], true)) {
            return round($rent * ($value / 100), 2);
        }

        return round($value, 2);
    }

    /**
     * @return list<DepositDefinition>
     */
    private static function activeDepositDefinitions(PropertyUnit $unit): array
    {
        if (! Schema::hasTable('deposit_definitions')) {
            return [];
        }

        $rows = DepositDefinition::query()
            ->where('is_active', true)
            ->where('property_id', $unit->property_id)
            ->where(function ($query) use ($unit) {
                $query->whereNull('property_unit_id')
                    ->orWhere('property_unit_id', $unit->id);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return self::preferUnitSpecific($rows, 'deposit_key')->all();
    }

    /**
     * @return list<ExpenseDefinition>
     */
    private static function activeExpenseDefinitions(PropertyUnit $unit): array
    {
        if (! Schema::hasTable('expense_definitions')) {
            return [];
        }

        $rows = ExpenseDefinition::query()
            ->where('is_active', true)
            ->where('property_id', $unit->property_id)
            ->where(function ($query) use ($unit) {
                $query->whereNull('property_unit_id')
                    ->orWhere('property_unit_id', $unit->id);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return self::preferUnitSpecific($rows, 'charge_key')->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function preferUnitSpecific($rows, string $keyName)
    {
        return $rows
            ->groupBy(fn ($row) => (string) ($row->{$keyName} ?: $row->id))
            ->map(function ($group) {
                $unitSpecific = $group->first(fn ($row) => $row->property_unit_id !== null);

                return $unitSpecific ?: $group->first();
            })
            ->values();
    }

    private static function looksLikeFloorPlan(string $path): bool
    {
        $haystack = strtolower($path);

        return str_contains($haystack, 'floor') || str_contains($haystack, 'plan') || str_contains($haystack, 'layout');
    }
}
