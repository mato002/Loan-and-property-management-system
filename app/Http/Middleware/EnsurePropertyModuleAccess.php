<?php

namespace App\Http\Middleware;

use App\Support\Property\PropertyModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePropertyModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($user && $routeName !== '' && ! PropertyModuleAccess::allows($user, $routeName)) {
            abort(403, 'You do not have permission to open this module.');
        }

        return $next($request);
    }
}
