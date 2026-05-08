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
use Illuminate\Support\Facades\Storage;
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

        $activeSystem = (string) $request->session()->get('active_system', 'loan');
        if (! in_array($activeSystem, ['loan', 'property'], true)) {
            $activeSystem = 'loan';
        }

        return view('profile.edit', [
            'user' => $user,
            'activeSystem' => $activeSystem,
            'roleLabel' => $this->resolveRoleLabel($user, $activeSystem),
            'activeDevices' => $activeDevices,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $user->fill(collect($validated)->except(['profile_photo', 'remove_profile_photo'])->all());

        if ($request->boolean('remove_profile_photo') && filled($user->profile_photo_path)) {
            Storage::disk('public')->delete((string) $user->profile_photo_path);
            $user->profile_photo_path = null;
        }

        if ($request->hasFile('profile_photo')) {
            if (filled($user->profile_photo_path)) {
                Storage::disk('public')->delete((string) $user->profile_photo_path);
            }

            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

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

        if (filled($user->profile_photo_path)) {
            Storage::disk('public')->delete((string) $user->profile_photo_path);
        }

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

    private function resolveRoleLabel(Model $user, string $activeSystem): string
    {
        if ((bool) ($user->is_super_admin ?? false)) {
            return 'Super Administrator';
        }

        return $activeSystem === 'property'
            ? $this->resolvePropertyRoleLabel($user)
            : $this->resolveLoanRoleLabel($user);
    }

    private function resolveLoanRoleLabel(Model $user): string
    {
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

        return 'User';
    }

    private function resolvePropertyRoleLabel(Model $user): string
    {
        $role = trim((string) ($user->property_portal_role ?? ''));
        if (! in_array($role, ['agent', 'landlord', 'tenant'], true)) {
            try {
                if (method_exists($user, 'pmTenantProfile') && $user->pmTenantProfile()->exists()) {
                    $role = 'tenant';
                } elseif (method_exists($user, 'landlordProperties') && $user->landlordProperties()->exists()) {
                    $role = 'landlord';
                } else {
                    $role = 'agent';
                }
            } catch (\Throwable) {
                $role = 'agent';
            }
        }

        if ($role === 'agent' && Schema::hasTable('pm_roles') && method_exists($user, 'pmRoles')) {
            $named = $user->pmRoles()->orderBy('pm_roles.id')->value('name');
            if (filled($named)) {
                return (string) $named;
            }
        }

        if ($role === '') {
            return 'User';
        }

        return 'Property '.Str::title(str_replace('_', ' ', $role));
    }
}
