<?php

namespace App\Services;

use Illuminate\Http\Request;

class LoanClientIdentifierNormalizer
{
    /**
     * Keys merged back onto the request before validation.
     *
     * @var list<string>
     */
    private const TRIM_NULLABLE_KEYS = [
        'phone',
        'email',
        'id_number',
        'first_name',
        'last_name',
        'address',
        'branch',
        'notes',
        'next_of_kin_name',
        'next_of_kin_contact',
        'guarantor_1_full_name',
        'guarantor_1_phone',
        'guarantor_1_id_number',
        'guarantor_1_relationship',
        'guarantor_1_address',
        'guarantor_2_full_name',
        'guarantor_2_phone',
        'guarantor_2_id_number',
        'guarantor_2_relationship',
        'guarantor_2_address',
    ];

    public function mergeNormalizedClientIdentifiers(Request $request): void
    {
        $merge = [];
        foreach (self::TRIM_NULLABLE_KEYS as $key) {
            if (! $request->has($key)) {
                continue;
            }
            $raw = $request->input($key);
            if (is_array($raw)) {
                continue;
            }
            $trimmed = trim((string) $raw);
            if ($trimmed === '') {
                $merge[$key] = null;

                continue;
            }
            if ($key === 'email') {
                $merge[$key] = strtolower($trimmed);
            } elseif ($key === 'phone' || str_ends_with($key, '_phone')) {
                $merge[$key] = $this->normalizePhone($trimmed);
            } elseif ($key === 'id_number' || str_ends_with($key, '_id_number')) {
                $merge[$key] = $trimmed;
            } else {
                $merge[$key] = $trimmed;
            }
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    /**
     * Kenya-oriented: strip separators, prefer E.164-style 254… when possible.
     */
    public function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '254')) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            return '254'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            return '254'.$digits;
        }

        return $digits;
    }

    public function phonesEquivalent(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return false;
        }

        return $this->normalizePhone($a) === $this->normalizePhone($b);
    }
}
