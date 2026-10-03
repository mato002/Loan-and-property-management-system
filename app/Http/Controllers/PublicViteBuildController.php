<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve Vite CSS/JS from the Laravel public/build folder when cPanel's
 * public_html does not have a copied build (app lives in a sibling folder).
 */
class PublicViteBuildController extends Controller
{
    private const ALLOWED_EXTENSIONS = [
        'css', 'js', 'map', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg',
        'woff', 'woff2', 'ttf', 'eot', 'ico',
    ];

    public function show(string $path): BinaryFileResponse
    {
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            abort(404);
        }

        $base = public_path('build');
        $realBase = realpath($base);
        $candidate = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $realFile = realpath($candidate);
        if ($realBase === false || $realFile === false || ! is_file($realFile)) {
            abort(404);
        }

        $prefix = $realBase.DIRECTORY_SEPARATOR;
        if (! str_starts_with($realFile, $prefix)) {
            abort(404);
        }

        $mime = match ($extension) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'text/javascript; charset=UTF-8',
            'json', 'map' => 'application/json',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => mime_content_type($realFile) ?: 'application/octet-stream',
        };

        return response()->file($realFile, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
