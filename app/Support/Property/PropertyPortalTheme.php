<?php

namespace App\Support\Property;

use App\Models\PropertyPortalSetting;

final class PropertyPortalTheme
{
    public const LIGHT = 'light';

    public const DARK = 'dark';

    /** @var list<string> */
    public const OPTIONS = [
        self::LIGHT,
        self::DARK,
    ];

    public static function current(): string
    {
        $theme = strtolower(trim((string) PropertyPortalSetting::getValue('portal_color_theme', self::LIGHT)));

        return in_array($theme, self::OPTIONS, true) ? $theme : self::LIGHT;
    }

    public static function isDark(): bool
    {
        return self::current() === self::DARK;
    }

    public static function colorScheme(): string
    {
        return self::isDark() ? self::DARK : self::LIGHT;
    }

    public static function htmlClass(): string
    {
        return self::isDark() ? self::DARK : '';
    }

    public static function normalize(?string $theme): string
    {
        $theme = strtolower(trim((string) $theme));

        return in_array($theme, self::OPTIONS, true) ? $theme : self::LIGHT;
    }
}
