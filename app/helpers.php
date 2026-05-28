<?php

/**
 * Application-wide helper functions loaded via Composer autoload.
 */

use App\Support\Property\PropertyUiVersion;
use Illuminate\Contracts\View\View;

if (! function_exists('property_view')) {
    /**
     * Render a property module view for the active UI version (legacy or v2).
     *
     * @param  array<string, mixed>  $data
     */
    function property_view(string $name, array $data = []): View
    {
        return PropertyUiVersion::view($name, $data);
    }
}
