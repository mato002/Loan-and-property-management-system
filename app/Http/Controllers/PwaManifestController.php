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
            startUrl: './',
            scope: './',
            descriptionSuffix: 'browse listings and manage your property online.',
            shortNameSuffix: '',
            usePublicSiteBranding: true,
            extra: [
                'shortcuts' => [
                    [
                        'name' => 'Browse properties',
                        'short_name' => 'Properties',
                        'url' => './properties',
                    ],
                    [
                        'name' => 'Apply for a rental',
                        'short_name' => 'Apply',
                        'url' => './apply',
                    ],
                    [
                        'name' => 'Contact',
                        'short_name' => 'Contact',
                        'url' => './contact',
                    ],
                ],
            ],
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

    public function maintenanceMediaScript(): BinaryFileResponse
    {
        $path = public_path('js/maintenance-media.js');
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

    private function manifest(string $startUrl, string $scope, string $descriptionSuffix, string $shortNameSuffix, bool $usePublicSiteBranding = false, ?string $appName = null, ?array $icons = null, array $extra = []): JsonResponse
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

        $icons ??= [
            [
                'src' => asset('pwa/icon-192.png'),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => asset('pwa/icon-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => asset('pwa/icon-maskable-192.png'),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => asset('pwa/icon-maskable-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ];

        $themeColor = PropertyBrandPalette::color(
            PropertyBrandPalette::resolve($usePublicSiteBranding ? 'public' : 'portal'),
            $usePublicSiteBranding ? 'cta' : 'primary'
        );

        return response()->json(array_merge([
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
        ], $extra), 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
