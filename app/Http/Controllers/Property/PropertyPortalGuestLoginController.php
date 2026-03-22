<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
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
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request, $requiredRole);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request, $requiredRole));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $requiredRole));

        $user = Auth::user();
        if (($user->property_portal_role ?? null) !== $requiredRole) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => $requiredRole === 'tenant'
                    ? __('This page is for tenants only. Use the landlord or staff sign-in if that matches your account.')
                    : __('This page is for landlords only. Use the tenant or staff sign-in if that matches your account.'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('active_system', 'property');

        return redirect()->intended(route($successRoute, absolute: false));
    }

    protected function ensureIsNotRateLimited(Request $request, string $portal): void
    {
        $key = $this->throttleKey($request, $portal);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(Request $request, string $portal): string
    {
        return Str::transliterate(Str::lower($request->string('email')).'|'.$portal.'-portal|'.$request->ip());
    }
}
