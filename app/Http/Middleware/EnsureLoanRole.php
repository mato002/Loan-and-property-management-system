<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoanRole
{
    /**
     * Usage: ->middleware('loan.role:accountant,admin')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (($user->is_super_admin ?? false) === true) {
            return $next($request);
        }

        // Legacy-safe: if role column isn't populated, don't block existing installs.
        $userRole = (string) ($user->loan_role ?? '');
        if ($userRole === '') {
            return $next($request);
        }

        $allowed = array_values(array_filter(array_map('trim', $roles)));
        if ($allowed !== [] && in_array($userRole, $allowed, true)) {
            return $next($request);
        }

        abort(403);
    }
}

