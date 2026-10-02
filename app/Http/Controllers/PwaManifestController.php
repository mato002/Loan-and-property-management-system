<?php

namespace App\Http\Controllers;

use App\Support\Property\PropertyBrandPalette;
use App\Support\Property\PropertyWorkspaceBranding;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PwaManifestController extends Controller
{
    public function public(): JsonResponse
    {
        return $this->manifest(
            startUrl: url('/'),
            scope: url('/'),
            descriptionSuffix: 'browse listings and manage your property online.',
            shortNameSuffix: '',
            usePublicSiteBranding: true,
        );
    }

    public function portal(): JsonResponse
    {
        return $this->manifest(
            startUrl: url('/dashboard'),
            scope: url('/'),
            descriptionSuffix: 'access your property portal, payments, and reports.',
            shortNameSuffix: ' Portal',
            usePublicSiteBranding: false,
        );
    }

    public function meterIcon(string $size): BinaryFileResponse
    {
        abort_unless(in_array($size, ['192', '512'], true), 404);
        $path = public_path('pwa/meters-'.$size.'.png');
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function fieldScript(): BinaryFileResponse
    {
        $path = public_path('js/field-readings.js');
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function field(): JsonResponse
    {
        return $this->manifest(
            startUrl: url('/property/field/readings'),
            scope: rtrim(url('/property/field'), '/').'/',
            descriptionSuffix: 'record water, electricity, and other meters while in the field.',
            shortNameSuffix: '',
            usePublicSiteBranding: false,
            appName: 'Meter capture',
            icons: [
                [
                    'src' => asset('pwa/meters-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('pwa/meters-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        );
    }

    private function manifest(string $startUrl, string $scope, string $descriptionSuffix, string $shortNameSuffix, bool $usePublicSiteBranding = false, ?string $appName = null, ?array $icons = null): JsonResponse
    {
        $companyName = $usePublicSiteBranding
            ? (PropertyWorkspaceBranding::forPublicSite('company_name', config('app.name', 'Property Portal')) ?? config('app.name', 'Property Portal'))
            : (\App\Models\PropertyPortalSetting::getValue('company_name', '') ?: config('app.name', 'Property Portal'));
        $shortBase = mb_strlen($companyName) > 14
            ? mb_substr($companyName, 0, 12).'…'
            : $companyName;
        $shortName = $shortNameSuffix !== ''
            ? (mb_strlen($shortBase.$shortNameSuffix) > 14
                ? mb_substr($shortBase, 0, max(1, 12 - mb_strlen($shortNameSuffix))).$shortNameSuffix
                : $shortBase.$shortNameSuffix)
            : $shortBase;

        $logoUrl = $usePublicSiteBranding
            ? (PropertyWorkspaceBranding::forPublicSite('company_logo_url', '') ?? '')
            : (\App\Models\PropertyPortalSetting::getValue('company_logo_url', '') ?? '');
        $faviconUrl = $usePublicSiteBranding
            ? (PropertyWorkspaceBranding::forPublicSite('site_favicon_url', '') ?? '')
            : (\App\Models\PropertyPortalSetting::getValue('site_favicon_url', '') ?? '');
        $iconUrl = $logoUrl !== '' ? $logoUrl : ($faviconUrl !== '' ? $faviconUrl : asset('favicon.ico'));

        $iconType = str_ends_with(strtolower(parse_url($iconUrl, PHP_URL_PATH) ?? ''), '.svg')
            ? 'image/svg+xml'
            : 'image/png';

        $icons ??= [
            [
                'src' => $iconUrl,
                'sizes' => '192x192',
                'type' => $iconType,
                'purpose' => 'any',
            ],
            [
                'src' => $iconUrl,
                'sizes' => '512x512',
                'type' => $iconType,
                'purpose' => 'any',
            ],
        ];

        $themeColor = PropertyBrandPalette::color(
            PropertyBrandPalette::resolve($usePublicSiteBranding ? 'public' : 'portal'),
            $usePublicSiteBranding ? 'cta' : 'primary'
        );

        return response()->json([
            'id' => $startUrl,
            'name' => $appName ?: ($companyName.($shortNameSuffix !== '' ? ' — Property Portal' : '')),
            'short_name' => $appName ? 'Meters' : $shortName,
            'description' => $companyName.' — '.$descriptionSuffix,
            'start_url' => $startUrl,
            'scope' => $scope,
            'display' => 'standalone',
            'display_override' => ['standalone', 'browser'],
            'background_color' => '#ffffff',
            'theme_color' => $themeColor,
            'lang' => str_replace('_', '-', app()->getLocale()),
            'categories' => ['business', 'productivity'],
            'icons' => $icons,
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
