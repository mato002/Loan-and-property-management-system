<?php

namespace App\Support\Property;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Navigation shell mode resolver — sidebar/header presentation only.
 */
final class PropertyNavMode
{
    public const CLASSIC = 'classic';

    public const WORKSPACE = 'workspace';

    public const HYBRID = 'hybrid';

    public static function current(?User $user = null): string
    {
        $allowed = config('property.navigation.allowed_modes', [
            self::CLASSIC,
            self::WORKSPACE,
            self::HYBRID,
        ]);

        $userMode = self::userPreference($user);
        if ($userMode !== null && in_array($userMode, $allowed, true)) {
            return $userMode;
        }

        $configured = strtolower(trim((string) config('property.navigation.mode', self::CLASSIC)));
        if ($configured === '' || ! in_array($configured, $allowed, true)) {
            return self::CLASSIC;
        }

        return $configured;
    }

    public static function isClassic(?User $user = null): bool
    {
        return self::current($user) === self::CLASSIC;
    }

    public static function isWorkspace(?User $user = null): bool
    {
        return self::current($user) === self::WORKSPACE;
    }

    public static function isHybrid(?User $user = null): bool
    {
        return self::current($user) === self::HYBRID;
    }

    /**
     * Sidebar partial for agent portal (falls back to classic).
     */
    public static function agentSidebarPartial(?User $user = null): string
    {
        $mode = self::current($user);
        $candidate = "layouts.property.sidebar.{$mode}";

        return view()->exists($candidate)
            ? $candidate
            : 'layouts.property.sidebar.classic';
    }

    /**
     * Workspace tabs are additive — only enabled for workspace/hybrid nav modes.
     */
    public static function showShellWorkspaceTabs(?User $user = null): bool
    {
        return in_array(self::current($user), [self::WORKSPACE, self::HYBRID], true);
    }

    private static function userPreference(?User $user): ?string
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $column = trim((string) config('property.navigation.user_mode_column', 'property_nav_mode'));
        if ($column === '' || ! Schema::hasColumn($user->getTable(), $column)) {
            return null;
        }

        $value = strtolower(trim((string) ($user->{$column} ?? '')));

        return $value !== '' ? $value : null;
    }
}
