<?php

namespace App\Http\Middleware;

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

        if (! $user->hasPmPermission($permissionKey)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}

