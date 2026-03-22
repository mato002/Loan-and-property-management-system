<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivePropertySystem
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('active_system') !== 'property') {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
