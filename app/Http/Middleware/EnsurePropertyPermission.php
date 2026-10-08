<?php

namespace App\Http\Middleware;

use App\Support\Property\PropertyCrudPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePropertyPermission
{
    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $slice = PropertyCrudPermissions::sliceFor($permissionKey, $request);
        if ($slice !== null && $user->directPmPermissionEffect($slice) === 'deny') {
            abort(403, 'You do not have permission to perform this action.');
        }

        if ($user->hasPmPermission($permissionKey) || ($slice !== null && $user->hasPmPermission($slice))) {
            return $next($request);
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}

