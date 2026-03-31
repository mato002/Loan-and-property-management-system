<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePropertyPortalRole
{
    private function inferRole(Request $request): string
    {
        $user = $request->user();
        $role = trim((string) ($user?->property_portal_role ?? ''));
        if (in_array($role, ['agent', 'landlord', 'tenant'], true)) {
            return $role;
        }

        // Fallback for older DBs / missing column values: infer from linked portal profiles.
        try {
            if ($user && method_exists($user, 'pmTenantProfile') && $user->pmTenantProfile()->exists()) {
                return 'tenant';
            }
            if ($user && method_exists($user, 'landlordProperties') && $user->landlordProperties()->exists()) {
                return 'landlord';
            }
        } catch (\Throwable) {
            // ignore inference errors, default below
        }

        return 'agent';
    }

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $userRole = $this->inferRole($request);

        if ($userRole === $role) {
            return $next($request);
        }

        return redirect()->route(match ($userRole) {
            'landlord' => 'property.landlord.portfolio',
            'tenant' => 'property.tenant.home',
            default => 'property.dashboard',
        });
    }
}
