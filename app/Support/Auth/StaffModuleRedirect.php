<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;

final class StaffModuleRedirect
{
    public const PREFERRED_MODULE_COOKIE = 'staff_preferred_module';

    /**
     * @return list<string>
     */
    public static function approvedModules(User $user): array
    {
        if (($user->is_super_admin ?? false) === true) {
            return ['property', 'loan'];
        }

        return $user->approvedModules();
    }

    public static function isModuleAllowed(User $user, string $module): bool
    {
        return in_array($module, self::approvedModules($user), true);
    }

    public static function hasDualModuleAccess(?User $user): bool
    {
        return $user !== null && count(self::approvedModules($user)) >= 2;
    }

    public static function switchTargetModule(User $user, string $currentModule): ?string
    {
        if (! in_array($currentModule, ['property', 'loan'], true)) {
            $currentModule = (string) ($user->property_portal_role ?? '') !== '' ? 'property' : 'loan';
        }

        $target = $currentModule === 'property' ? 'loan' : 'property';

        return self::isModuleAllowed($user, $target) ? $target : null;
    }

    public static function moduleShortLabel(string $module): string
    {
        return match ($module) {
            'property' => 'Property',
            'loan' => 'Loan',
            default => ucfirst($module),
        };
    }

    public static function moduleSwitchUrl(string $targetModule): string
    {
        return route('module.switch', ['module' => $targetModule]);
    }

    public static function preferredModule(Request $request): ?string
    {
        $fromSession = (string) $request->session()->get('active_system', '');
        if (in_array($fromSession, ['property', 'loan'], true)) {
            return $fromSession;
        }

        $fromCookie = (string) $request->cookie(self::PREFERRED_MODULE_COOKIE, '');
        if (in_array($fromCookie, ['property', 'loan'], true)) {
            return $fromCookie;
        }

        return null;
    }

    public static function defaultModuleFor(User $user): ?string
    {
        $approved = self::approvedModules($user);
        if (count($approved) === 1) {
            return $approved[0];
        }

        if (in_array('property', $approved, true) && (string) ($user->property_portal_role ?? '') !== '') {
            return 'property';
        }

        if (in_array('loan', $approved, true)) {
            return 'loan';
        }

        return $approved[0] ?? null;
    }

    public static function destinationRouteName(User $user, string $activeSystem): string
    {
        if ($activeSystem === 'property') {
            $role = (string) ($user->property_portal_role ?? 'agent');

            return match ($role) {
                'landlord' => 'property.landlord.portfolio',
                'tenant' => 'property.tenant.home',
                default => 'property.dashboard',
            };
        }

        $loanRole = (string) ($user->effectiveLoanRole() ?? '');

        return match ($loanRole) {
            'accountant' => 'loan.accounting.books',
            'applicant' => 'loan.account.show',
            default => 'loan.dashboard',
        };
    }

    public static function rememberModule(Request $request, string $module): void
    {
        $request->session()->put('active_system', $module);
        $request->session()->forget('url.intended');
    }
}
