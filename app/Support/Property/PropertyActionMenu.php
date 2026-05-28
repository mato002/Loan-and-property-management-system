<?php

namespace App\Support\Property;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

final class PropertyActionMenu
{
    /**
     * Wrap pre-built menu markup in the standard action-menu shell (for controller-built rows).
     */
    public static function render(string $menuHtml, string $label = 'Actions', string $width = 'w-48'): HtmlString
    {
        return new HtmlString(Blade::render(
            <<<'BLADE'
            <x-property.action-menu :label="$label" :width="$width">
                {!! $menu !!}
            </x-property.action-menu>
            BLADE,
            [
                'label' => $label,
                'width' => $width,
                'menu' => $menuHtml,
            ]
        ));
    }
}
