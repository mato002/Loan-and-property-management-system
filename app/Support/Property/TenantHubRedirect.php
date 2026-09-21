<?php

namespace App\Support\Property;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Return to tenant 360 after create/update actions started from the hub.
 */
final class TenantHubRedirect
{
    public static function wantsTenantShow(Request $request): bool
    {
        return (string) $request->input('return_to') === 'tenant_show';
    }

    public static function tenantId(Request $request, ?int $fallback = null): ?int
    {
        $id = (int) ($request->input('return_tenant_id') ?: $request->input('pm_tenant_id') ?: $fallback ?: 0);

        return $id > 0 ? $id : null;
    }

    public static function tab(Request $request, string $default = 'overview'): string
    {
        $tab = trim((string) $request->input('return_tab', $default));

        return PropertyEntityHub::normalizeTab('tenant', $tab !== '' ? $tab : $default);
    }

    public static function toShow(Request $request, ?int $fallbackTenantId = null, string $defaultTab = 'overview', ?string $success = null): ?RedirectResponse
    {
        if (! self::wantsTenantShow($request)) {
            return null;
        }

        $tenantId = self::tenantId($request, $fallbackTenantId);
        if ($tenantId === null) {
            return null;
        }

        $redirect = redirect()->route('property.tenants.show', [
            'tenant' => $tenantId,
            'tab' => self::tab($request, $defaultTab),
        ]);

        if ($success !== null && $success !== '') {
            $redirect->with('success', $success);
        }

        return $redirect;
    }

    /**
     * @return array{return_to: string, return_tenant_id: int, return_tab: string}
     */
    public static function hiddenFields(int $tenantId, string $tab): array
    {
        return [
            'return_to' => 'tenant_show',
            'return_tenant_id' => $tenantId,
            'return_tab' => PropertyEntityHub::normalizeTab('tenant', $tab),
        ];
    }
}
