<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures @vite does not point other devices at 127.0.0.1:5173 (npm run dev hot file).
 * Also disables the hot file in production if it was accidentally deployed.
 */
class ConfigureViteHotRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useHotFile(null);

        $defaultHotPath = public_path('hot');

        if (! is_file($defaultHotPath)) {
            return $next($request);
        }

        $fakeHotPath = storage_path('framework/vite-hot-disabled');
        $hotUrl = trim((string) file_get_contents($defaultHotPath));

        if (app()->environment('production')) {
            Vite::useHotFile($fakeHotPath);

            return $next($request);
        }

        $loopbackHot = $hotUrl !== '' && (bool) preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?(/|$)#i', $hotUrl);

        if ($loopbackHot) {
            $host = strtolower($request->getHost());
            $localClient = in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
                || str_ends_with($host, '.localhost');

            if (! $localClient) {
                Vite::useHotFile($fakeHotPath);
            }
        }

        return $next($request);
    }
}
