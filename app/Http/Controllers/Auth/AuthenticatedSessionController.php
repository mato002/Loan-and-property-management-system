<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoanSecurityPolicyService;
use App\Services\Property\LoginActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            app(LoginActivityLogger::class)->log(
                null,
                'failed',
                'Staff login failed',
                (string) $request->input('email'),
                [
                    'portal' => 'staff',
                    'ip' => (string) $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]
            );
            throw $e;
        }
        $user = $request->user();
        app(LoginActivityLogger::class)->log(
            (int) $user->id,
            'sent',
            'Staff login successful',
            (string) $user->email,
            [
                'portal' => 'staff',
                'ip' => (string) $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]
        );

        // Super admins should still choose which module (Property / Loan) to enter.
        // They can access the Super Admin console from the navigation.
        $approvedModules = (($user->is_super_admin ?? false) === true)
            ? ['property', 'loan']
            : ($user?->approvedModules() ?? []);

        // Ensure users don't keep a previous module selection or stale intended redirects.
        $request->session()->forget(['active_system', 'url.intended']);

        if (count($approvedModules) === 0) {
            app(LoginActivityLogger::class)->log(
                (int) $user->id,
                'failed',
                'Staff login blocked (module not approved)',
                (string) $user->email,
                [
                    'portal' => 'staff',
                    'ip' => (string) $request->ip(),
                ]
            );
            return redirect()->route('login')
                ->withErrors([
                    'module' => 'Your account is not approved for Property or Loan module access yet.',
                ])
                ->withInput($request->only('email'));
        }

        // Enforce role login windows and device governance (when enabled).
        $policyError = app(LoanSecurityPolicyService::class)->evaluateLoginPolicies($request, $user);
        if ($policyError !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            app(LoginActivityLogger::class)->log(
                (int) $user->id,
                'failed',
                'Staff login blocked by security policy',
                (string) $user->email,
                [
                    'portal' => 'staff',
                    'ip' => (string) $request->ip(),
                    'reason' => $policyError,
                ]
            );

            return redirect()->route('login')
                ->withErrors(['email' => $policyError])
                ->withInput($request->only('email'));
        }

        // Auto-redirect if only one module is approved.
        if (count($approvedModules) === 1) {
            $request->session()->put('active_system', $approvedModules[0]);
            $request->session()->regenerate();

            // Don't use intended here; it may point to a route in the other (unapproved) module.
            return redirect()->route('dashboard');
        }

        // If both modules are approved, ask which module to enter next.
        $request->session()->regenerate();

        return redirect()->route('choose_module');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $portal = in_array((string) ($user->property_portal_role ?? ''), ['tenant', 'landlord'], true)
                ? (string) $user->property_portal_role
                : 'staff';
            app(LoginActivityLogger::class)->log(
                (int) $user->id,
                'sent',
                'Logout',
                (string) $user->email,
                [
                    'portal' => $portal,
                    'ip' => (string) $request->ip(),
                ]
            );
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
