<?php

namespace App\Http\Middleware;

use App\Models\UserModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (($user->is_super_admin ?? false) === true) {
            return $next($request);
        }

        // Legacy-safe: if migrations haven't been run yet, don't block.
        if (! Schema::hasTable('user_module_accesses')) {
            return $next($request);
        }

        $approved = $user->moduleAccesses()
            ->where('module', $module)
            ->where('status', UserModuleAccess::STATUS_APPROVED)
            ->exists();

        if ($approved) {
            return $next($request);
        }

        $request->session()->forget(['active_system', 'url.intended']);

        return redirect()
            ->route('login')
            ->withErrors([
                'module' => "Your account is not approved for {$module} module access.",
            ]);
    }
}

