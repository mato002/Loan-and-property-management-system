<?php

namespace App\Support\Property;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Return to property 360 after actions started from the hub.
 */
final class PropertyHubRedirect
{
    public static function wantsPropertyShow(Request $request): bool
    {
        return (string) $request->input('return_to') === 'property_show';
    }

    public static function propertyId(Request $request, ?int $fallback = null): ?int
    {
        $id = (int) ($request->input('return_property_id') ?: $request->input('property_id') ?: $fallback ?: 0);

        return $id > 0 ? $id : null;
    }

    public static function tab(Request $request, string $default = 'overview'): string
    {
        $tab = trim((string) $request->input('return_tab', $default));

        return PropertyEntityHub::normalizeTab('property', $tab !== '' ? $tab : $default);
    }

    /**
     * @return array<string, mixed>
     */
    public static function periodQuery(Request $request): array
    {
        $query = [];
        $month = (string) ($request->input('return_month') ?: $request->input('month') ?: '');
        $fy = $request->input('return_fy', $request->input('fy'));
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $query['month'] = $month;
        }
        if ($fy !== null && $fy !== '' && (int) $fy > 0) {
            $query['fy'] = (int) $fy;
        }

        return $query;
    }

    public static function toShow(Request $request, ?int $fallbackPropertyId = null, string $defaultTab = 'overview', ?string $success = null): ?RedirectResponse
    {
        if (! self::wantsPropertyShow($request)) {
            return null;
        }

        $propertyId = self::propertyId($request, $fallbackPropertyId);
        if ($propertyId === null) {
            return null;
        }

        $redirect = redirect()->route('property.properties.show', array_merge([
            'property' => $propertyId,
            'tab' => self::tab($request, $defaultTab),
        ], self::periodQuery($request)));

        if ($success !== null && $success !== '') {
            $redirect->with('success', $success);
        }

        return $redirect;
    }

    /**
     * @return array{return_to: string, return_property_id: int, return_tab: string}
     */
    public static function hiddenFields(int $propertyId, string $tab, ?string $month = null, ?int $fy = null): array
    {
        $fields = [
            'return_to' => 'property_show',
            'return_property_id' => $propertyId,
            'return_tab' => PropertyEntityHub::normalizeTab('property', $tab),
        ];
        if ($month !== null && $month !== '') {
            $fields['return_month'] = $month;
        }
        if ($fy !== null && $fy > 0) {
            $fields['return_fy'] = $fy;
        }

        return $fields;
    }
}
