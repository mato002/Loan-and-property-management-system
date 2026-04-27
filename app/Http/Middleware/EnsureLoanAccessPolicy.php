<?php

namespace App\Http\Middleware;

use App\Services\LoanSecurityPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoanAccessPolicy
{
    public function __construct(private readonly LoanSecurityPolicyService $policies)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (! $this->policies->isIpAllowedForRequest($request, $user)) {
            abort(403, 'Access denied by IP governance policy for this sensitive module.');
        }

        return $next($request);
    }
}

