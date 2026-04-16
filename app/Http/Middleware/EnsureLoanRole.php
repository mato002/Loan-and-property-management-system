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

        // Role can be explicit (users.loan_role) or derived from employee job title setup.
        $userRole = (string) ($user->effectiveLoanRole() ?? '');
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

