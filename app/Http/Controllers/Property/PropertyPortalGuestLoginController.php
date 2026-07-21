<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Services\Property\LoginActivityLogger;
use App\Services\Property\PropertyPortalAuthService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PropertyPortalGuestLoginController extends Controller
{
    public function createTenant(): View
    {
        return view('auth.property-portal-login', [
            'portalRole' => 'tenant',
            'postRoute' => route('property.tenant.login.store'),
        ]);
    }

    public function createLandlord(): View
    {
        return view('auth.property-portal-login', [
            'portalRole' => 'landlord',
            'postRoute' => route('property.landlord.login.store'),
            'allowPhoneLogin' => true,
        ]);
    }

    public function storeTenant(Request $request): RedirectResponse
    {
        return $this->authenticatePortalUser($request, 'tenant', 'property.tenant.home');
    }

    public function storeLandlord(Request $request): RedirectResponse
    {
        return $this->authenticatePortalUser($request, 'landlord', 'property.landlord.portfolio');
    }

    protected function authenticatePortalUser(Request $request, string $requiredRole, string $successRoute): RedirectResponse
    {
        $allowPhoneLogin = $requiredRole === 'landlord';

        if ($allowPhoneLogin) {
            $request->validate([
                'login' => ['required_without:email', 'nullable', 'string', 'max:255'],
                'email' => ['required_without:login', 'nullable', 'string', 'max:255'],
                'password' => ['required', 'string'],
            ]);
            $loginIdentifier = trim((string) ($request->input('login') ?: $request->input('email')));
        } else {
            $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ]);
            $loginIdentifier = trim((string) $request->input('email'));
        }

        $this->ensureIsNotRateLimited($request, $requiredRole, $loginIdentifier);

        $authenticated = $allowPhoneLogin
            ? app(PropertyPortalAuthService::class)->attempt($loginIdentifier, (string) $request->input('password'), $request->boolean('remember'))
            : Auth::attempt($request->only('email', 'password'), $request->boolean('remember'));

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey($request, $requiredRole, $loginIdentifier));
            app(LoginActivityLogger::class)->log(
                null,
                'failed',
                ucfirst($requiredRole).' portal login failed',
                $loginIdentifier,
                [
                    'portal' => $requiredRole,
                    'ip' => (string) $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]
            );

            throw ValidationException::withMessages([
                $allowPhoneLogin ? 'login' : 'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $requiredRole, $loginIdentifier));

        $user = Auth::user();
        if (($user->property_portal_role ?? null) !== $requiredRole) {
            app(LoginActivityLogger::class)->log(
                (int) $user->id,
                'failed',
                ucfirst($requiredRole).' portal login blocked (wrong role)',
                (string) $user->email,
                [
                    'portal' => $requiredRole,
                    'actual_role' => (string) ($user->property_portal_role ?? 'unknown'),
                    'ip' => (string) $request->ip(),
                ]
            );
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                ($allowPhoneLogin ? 'login' : 'email') => $requiredRole === 'tenant'
                    ? __('This page is for tenants only. Use the landlord or staff sign-in if that matches your account.')
                    : __('This page is for landlords only. Use the tenant or staff sign-in if that matches your account.'),
            ]);
        }

        // Block property portal access until the user is approved for the property module.
        if (! $user->isModuleApproved('property')) {
            app(LoginActivityLogger::class)->log(
                (int) $user->id,
                'failed',
                ucfirst($requiredRole).' portal login blocked (module not approved)',
                (string) $user->email,
                [
                    'portal' => $requiredRole,
                    'ip' => (string) $request->ip(),
                ]
            );
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                ($allowPhoneLogin ? 'login' : 'email') => __('Your account is not approved for Property management access yet.'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('active_system', 'property');
        app(LoginActivityLogger::class)->log(
            (int) $user->id,
            'sent',
            ucfirst($requiredRole).' portal login successful',
            (string) $user->email,
            [
                'portal' => $requiredRole,
                'ip' => (string) $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]
        );

        return redirect()->intended(route($successRoute, absolute: false));
    }

    protected function ensureIsNotRateLimited(Request $request, string $portal, string $loginIdentifier = ''): void
    {
        $key = $this->throttleKey($request, $portal, $loginIdentifier);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        $errorField = $portal === 'landlord' ? 'login' : 'email';

        throw ValidationException::withMessages([
            $errorField => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(Request $request, string $portal, string $loginIdentifier = ''): string
    {
        $login = $loginIdentifier !== ''
            ? $loginIdentifier
            : trim((string) ($request->input('login') ?: $request->input('email')));

        return Str::transliterate(Str::lower($login).'|'.$portal.'-portal|'.$request->ip());
    }
}
