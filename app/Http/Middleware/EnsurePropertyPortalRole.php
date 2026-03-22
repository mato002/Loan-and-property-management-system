<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePropertyPortalRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $userRole = $request->user()?->property_portal_role ?? 'agent';

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
