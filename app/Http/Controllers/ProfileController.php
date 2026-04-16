<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();
        $activeDevices = collect();

        if (Schema::hasTable('sessions')) {
            $activeDevices = DB::table('sessions')
                ->where('user_id', $user->getAuthIdentifier())
                ->orderByDesc('last_activity')
                ->limit(25)
                ->get()
                ->map(function ($session) use ($currentSessionId) {
                    $agent = (string) ($session->user_agent ?? '');

                    return (object) [
                        'id' => (string) $session->id,
                        'ip' => (string) ($session->ip_address ?? 'Unknown IP'),
                        'user_agent' => $agent !== '' ? Str::limit($agent, 120) : 'Unknown device',
                        'last_seen' => (int) ($session->last_activity ?? 0),
                        'is_current' => (string) $session->id === $currentSessionId,
                    ];
                });
        }

        return view('profile.edit', [
            'user' => $user,
            'roleLabel' => $this->resolveRoleLabel($user),
            'activeDevices' => $activeDevices,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function removeDevice(Request $request, string $sessionId): RedirectResponse
    {
        if (! Schema::hasTable('sessions')) {
            return Redirect::route('profile.edit')->with('status', 'device-unavailable');
        }

        $userId = $request->user()->getAuthIdentifier();
        $currentSessionId = $request->session()->getId();

        if ($sessionId === $currentSessionId) {
            return Redirect::route('profile.edit')->with('status', 'device-current');
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->delete();

        return Redirect::route('profile.edit')->with('status', 'device-removed');
    }

    public function removeOtherDevices(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('sessions')) {
            return Redirect::route('profile.edit')->with('status', 'device-unavailable');
        }

        $userId = $request->user()->getAuthIdentifier();
        $currentSessionId = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return Redirect::route('profile.edit')->with('status', 'devices-cleared');
    }

    private function resolveRoleLabel(Model $user): string
    {
        if ((bool) ($user->is_super_admin ?? false)) {
            return 'Super Administrator';
        }

        if (method_exists($user, 'activeLoanAccessRole')) {
            $assignedLoanRole = $user->activeLoanAccessRole();
            if ($assignedLoanRole && filled($assignedLoanRole->name)) {
                return (string) $assignedLoanRole->name;
            }
        }

        $loanRole = trim((string) ($user->loan_role ?? ''));
        if ($loanRole !== '') {
            return 'Loan '.Str::title(str_replace('_', ' ', $loanRole));
        }

        $propertyRole = trim((string) ($user->property_portal_role ?? ''));
        if ($propertyRole !== '') {
            return 'Property '.Str::title(str_replace('_', ' ', $propertyRole));
        }

        return 'User';
    }
}
