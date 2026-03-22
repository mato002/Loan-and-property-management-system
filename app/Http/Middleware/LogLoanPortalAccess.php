<?php

namespace App\Http\Middleware;

use App\Models\LoanAccessLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogLoanPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || ! str_starts_with($request->path(), 'loan/')) {
            return $response;
        }

        try {
            LoanAccessLog::query()->create([
                'user_id' => $request->user()->id,
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never block the portal if logging fails (e.g. migration not run yet).
        }

        return $response;
    }
}
