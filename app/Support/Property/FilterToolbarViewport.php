<?php

namespace App\Support\Property;

use Illuminate\Support\Facades\View;

/**
 * Request-scoped viewport for property/loan filter toolbars.
 *
 * Workspace and page shells set this around toolbar slots so
 * x-property.filter-toolbar can collapse on mobile without a second Filters button.
 */
final class FilterToolbarViewport
{
    public const KEY = '__propertyToolbarViewport';

    public static function current(): string
    {
        $shared = View::shared(self::KEY);

        return is_string($shared) && $shared !== '' ? $shared : 'all';
    }

    public static function set(string $viewport): void
    {
        View::share(self::KEY, $viewport);
    }
}
