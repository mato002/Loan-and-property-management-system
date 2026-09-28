<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream agent branding assets from the public disk.
 * Works even when public/storage is not symlinked (common on shared hosts).
 */
class PublicBrandingMediaController extends Controller
{
    public function show(Request $request, string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        // Accept either "2/logo.jpg" or "property/branding/2/logo.jpg".
        if (! str_starts_with($path, 'property/branding/')) {
            $path = 'property/branding/'.$path;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
