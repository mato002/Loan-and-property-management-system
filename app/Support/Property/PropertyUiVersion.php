<?php

namespace App\Support\Property;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class PropertyUiVersion
{
    public static function isV2(): bool
    {
        return (bool) config('property.ui_v2', false);
    }

    /**
     * Resolve a property page view name for the active UI version.
     *
     * Accepts `property.agent.dashboard` or `agent.dashboard`.
     */
    public static function resolveViewName(string $name): string
    {
        $logical = self::normalizeLogicalViewName($name);
        $version = self::isV2() ? 'v2' : 'agent';
        $versioned = "property.{$version}.{$logical}";

        if (View::exists($versioned)) {
            return $versioned;
        }

        $root = "property.{$logical}";
        if (View::exists($root)) {
            return $root;
        }

        return $versioned;
    }

    public static function view(string $name, array $data = []): ViewContract
    {
        return view(self::resolveViewName($name), $data);
    }

    public static function layoutView(): string
    {
        return self::isV2() ? 'layouts.property-v2' : 'layouts.property';
    }

    public static function frameLayoutView(): string
    {
        if (self::isV2() && View::exists('layouts.property_frame_v2')) {
            return 'layouts.property_frame_v2';
        }

        if (! self::isV2() && View::exists('layouts.property_frame_legacy')) {
            return 'layouts.property_frame_legacy';
        }

        return 'layouts.property_frame';
    }

    /**
     * Blade component partial (e.g. page, workspace, hub-grid).
     */
    public static function componentView(string $name): string
    {
        $version = self::isV2() ? 'v2' : 'agent';
        $versioned = "components.property.{$version}.{$name}";

        if (View::exists($versioned)) {
            return $versioned;
        }

        $root = "components.property.{$name}";
        if (View::exists($root)) {
            return $root;
        }

        return $versioned;
    }

    public static function sidebarPartial(string $role): string
    {
        if ($role === 'agent' && ! PropertyNavMode::isClassic()) {
            return PropertyNavMode::agentSidebarPartial();
        }

        $suffix = self::isV2() ? '_v2' : '_legacy';

        return match ($role) {
            'landlord' => "layouts.property_sidebar_landlord{$suffix}",
            'tenant' => "layouts.property_sidebar_tenant{$suffix}",
            default => "layouts.property_sidebar_agent{$suffix}",
        };
    }

    public static function headerPartial(): string
    {
        return self::isV2() ? 'layouts.property_header_v2' : 'layouts.property_header';
    }

    public static function footerPartial(): string
    {
        return self::isV2() ? 'layouts.property_footer_v2' : 'layouts.property_footer';
    }

    private static function normalizeLogicalViewName(string $name): string
    {
        $name = trim($name);
        if (str_starts_with($name, 'property.')) {
            $name = substr($name, strlen('property.'));
        }

        return ltrim($name, '.');
    }
}
