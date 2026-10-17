<?php

namespace App\Support\Property;

use App\Models\PropertyPortalSetting;

/** Global Super Admin preferences for the agent workspace's phone navigation. */
final class PropertyMobileNavigationSettings
{
    private const SETTING_KEY = 'property_mobile_navigation_json';

    /** @var array{hidden_workspaces: list<string>, hidden_tabs: array<string, list<string>>}|null */
    private static ?array $cachedSettings = null;

    /**
     * Default phone menu stays focused on the core property and collection workspaces.
     *
     * @var list<string>
     */
    private const DEFAULT_HIDDEN_WORKSPACES = [
        'hr', 'reports', 'accounting', 'settings', 'financials', 'analytics',
    ];

    /** @return array{hidden_workspaces: list<string>, hidden_tabs: array<string, list<string>>} */
    public static function settings(): array
    {
        if (self::$cachedSettings !== null) {
            return self::$cachedSettings;
        }

        $raw = PropertyPortalSetting::getGlobalValue(self::SETTING_KEY, '');
        $stored = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $stored = is_array($stored) ? $stored : [];

        return self::$cachedSettings = [
            'hidden_workspaces' => self::normalizeHiddenWorkspaces($stored['hidden_workspaces'] ?? self::DEFAULT_HIDDEN_WORKSPACES),
            'hidden_tabs' => self::normalizeHiddenTabs($stored['hidden_tabs'] ?? []),
        ];
    }

    public static function workspaceVisibleOnMobile(string $workspaceKey): bool
    {
        return ! in_array($workspaceKey, self::settings()['hidden_workspaces'], true);
    }

    public static function tabVisibleOnMobile(string $workspaceKey, string $tabKey): bool
    {
        return ! in_array($tabKey, self::settings()['hidden_tabs'][$workspaceKey] ?? [], true);
    }

    /**
     * @param  list<string>  $hiddenWorkspaces
     * @param  array<string, list<string>>  $hiddenTabs
     */
    public static function save(array $hiddenWorkspaces, array $hiddenTabs): void
    {
        self::$cachedSettings = [
            'hidden_workspaces' => self::normalizeHiddenWorkspaces($hiddenWorkspaces),
            'hidden_tabs' => self::normalizeHiddenTabs($hiddenTabs),
        ];
        PropertyPortalSetting::setGlobalValue(self::SETTING_KEY, json_encode(self::$cachedSettings, JSON_UNESCAPED_SLASHES));
    }

    /** @param mixed $keys @return list<string> */
    private static function normalizeHiddenWorkspaces(mixed $keys): array
    {
        $allowed = array_column(PropertyNavigation::agentWorkspaceDefinitions(), 'key');
        if (! is_array($keys)) {
            return self::DEFAULT_HIDDEN_WORKSPACES;
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($key): string => trim((string) $key), $keys),
            static fn (string $key): bool => in_array($key, $allowed, true),
        )));
    }

    /** @param mixed $settings @return array<string, list<string>> */
    private static function normalizeHiddenTabs(mixed $settings): array
    {
        if (! is_array($settings)) {
            return [];
        }

        $normalized = [];
        foreach (PropertyNavigation::agentWorkspaceDefinitions() as $workspace) {
            $workspaceKey = (string) ($workspace['key'] ?? '');
            if ($workspaceKey === '' || ! isset($settings[$workspaceKey]) || ! is_array($settings[$workspaceKey])) {
                continue;
            }
            $allowedTabs = array_column(PropertyWorkspaceTabs::configurationTabsFor($workspaceKey), 'key');
            $keys = array_values(array_unique(array_filter(
                array_map(static fn ($key): string => trim((string) $key), $settings[$workspaceKey]),
                static fn (string $key): bool => in_array($key, $allowedTabs, true),
            )));
            if ($keys !== []) {
                $normalized[$workspaceKey] = $keys;
            }
        }

        return $normalized;
    }
}
