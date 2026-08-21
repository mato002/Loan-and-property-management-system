<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicListingMediaController extends Controller
{
    /**
     * Stream listing photos/videos from the public disk.
     * Works even when public/storage is not symlinked (common on shared hosts).
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', rawurldecode($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        // Only listing media trees (uploads + demo seeds).
        $allowedPrefix = str_starts_with($path, 'public-listings/')
            || str_starts_with($path, 'demo-seed/');

        if (! $allowedPrefix) {
            abort(404);
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
