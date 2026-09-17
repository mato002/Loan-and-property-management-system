<?php

namespace App\Support\Property;

use Illuminate\Http\Request;

final class PropertyTurboFrames
{
    public const MAIN = 'property-main';

    public const LIST_RESULTS = 'property-list-results';

    public static function current(?Request $request = null): string
    {
        return trim((string) ($request ?? request())->header('Turbo-Frame', ''));
    }

    public static function isListResults(?Request $request = null): bool
    {
        return self::current($request) === self::LIST_RESULTS;
    }

    public static function usesFrameLayout(?Request $request = null): bool
    {
        return in_array(self::current($request), [self::MAIN, self::LIST_RESULTS], true);
    }
}
