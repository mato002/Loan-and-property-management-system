<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoanSecurityPolicyService;
use App\Services\Property\LoginActivityLogger;
use App\Support\Auth\StaffModuleRedirect;
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

        // Super admins can enter either module; remember last choice via cookie.
        $approvedModules = StaffModuleRedirect::approvedModules($user);

        // Ensure users don't keep a stale intended redirect from a prior session.
        $request->session()->forget('url.intended');

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

        $request->session()->regenerate();
        $request->session()->forget('active_system');

        if (count($approvedModules) === 1) {
            StaffModuleRedirect::rememberModule($request, $approvedModules[0]);

            return redirect()->route(StaffModuleRedirect::destinationRouteName($user, $approvedModules[0]));
        }

        return redirect()
            ->route('choose_module')
            ->withCookie(cookie()->forget(StaffModuleRedirect::PREFERRED_MODULE_COOKIE));
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

        return redirect()
            ->route('login')
            ->withCookie(cookie()->forget(StaffModuleRedirect::PREFERRED_MODULE_COOKIE));
    }
}
