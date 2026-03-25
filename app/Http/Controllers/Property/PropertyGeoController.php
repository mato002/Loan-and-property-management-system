<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PropertyGeoController extends Controller
{
    public function suggestKenyaAddresses(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $city = trim((string) $request->query('city', ''));
        $debug = (string) $request->query('debug', '') === '1';
        if ($q === '' || mb_strlen($q) < 3) {
            return response()->json(['items' => []]);
        }

        $cacheKey = 'ke_addr_suggest:'.md5(mb_strtolower($q.'|'.$city));

        $meta = [
            'source' => 'nominatim',
            'q' => $q,
            'city' => $city,
        ];

        $items = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($q, $city, &$meta) {
            $q2 = $q;
            if ($city !== '') {
                $q2 = $q2.', '.$city.', Kenya';
            }

            $headers = [
                // Nominatim asks for a valid UA identifying the application.
                'User-Agent' => 'LoanAndPropertyManagementSystem/1.0 (address autocomplete; Kenya)',
                'Accept' => 'application/json',
            ];

            $params = [
                'q' => $q2,
                'countrycodes' => 'ke',
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 6,
            ];

            $response = null;
            try {
                $response = Http::timeout(10)->withHeaders($headers)->get('https://nominatim.openstreetmap.org/search', $params);
            } catch (Throwable $e) {
                // Common on local Windows/XAMPP: missing CA bundle causes SSL verification to fail.
                // Retry once with SSL verification disabled to keep the feature usable in dev.
                $meta['error'] = $e->getMessage();
                try {
                    $response = Http::timeout(10)
                        ->withOptions(['verify' => false])
                        ->withHeaders($headers)
                        ->get('https://nominatim.openstreetmap.org/search', $params);
                    $meta['ssl_verify_disabled'] = true;
                } catch (Throwable $e2) {
                    $meta['error_retry'] = $e2->getMessage();
                    return [];
                }
            }

            if (!$response->ok()) {
                $meta['http_status'] = $response->status();
                return [];
            }

            $raw = $response->json();
            if (!is_array($raw)) {
                return [];
            }

            $out = [];
            foreach ($raw as $row) {
                $label = $row['display_name'] ?? null;
                if (!is_string($label) || trim($label) === '') {
                    continue;
                }
                $out[] = [
                    'label' => $label,
                ];
            }

            return $out;
        });

        return response()->json($debug ? ['items' => $items, 'debug' => $meta] : ['items' => $items]);
    }
}

