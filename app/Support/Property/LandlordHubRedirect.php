<?php

namespace App\Support\Property;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Return to landlord 360 after actions started from the hub.
 */
final class LandlordHubRedirect
{
    public static function wantsLandlordShow(Request $request): bool
    {
        return (string) $request->input('return_to') === 'landlord_show';
    }

    public static function landlordId(Request $request, ?int $fallback = null): ?int
    {
        $id = (int) ($request->input('return_landlord_id') ?: $request->input('landlord_id') ?: $request->input('user_id') ?: $fallback ?: 0);

        return $id > 0 ? $id : null;
    }

    public static function tab(Request $request, string $default = 'overview'): string
    {
        $tab = trim((string) $request->input('return_tab', $default));

        return PropertyEntityHub::normalizeTab('landlord', $tab !== '' ? $tab : $default);
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

    public static function toShow(Request $request, ?int $fallbackLandlordId = null, string $defaultTab = 'overview', ?string $success = null): ?RedirectResponse
    {
        if (! self::wantsLandlordShow($request)) {
            return null;
        }

        $landlordId = self::landlordId($request, $fallbackLandlordId);
        if ($landlordId === null) {
            return null;
        }

        $redirect = redirect()->route('property.landlords.show', array_merge([
            'landlord' => $landlordId,
            'tab' => self::tab($request, $defaultTab),
        ], self::periodQuery($request)));

        if ($success !== null && $success !== '') {
            $redirect->with('success', $success);
        }

        return $redirect;
    }

    /**
     * @return array{return_to: string, return_landlord_id: int, return_tab: string}
     */
    public static function hiddenFields(int $landlordId, string $tab, ?string $month = null, ?int $fy = null): array
    {
        $fields = [
            'return_to' => 'landlord_show',
            'return_landlord_id' => $landlordId,
            'return_tab' => PropertyEntityHub::normalizeTab('landlord', $tab),
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
