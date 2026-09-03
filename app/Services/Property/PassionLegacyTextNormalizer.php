<?php

namespace App\Services\Property;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class PassionLegacyTextNormalizer
{
    public static function normalizeUnitLabel(?string $label): string
    {
        $label = Str::upper(trim((string) $label));
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        $label = preg_replace('/\s*\(\s*/', ' (', $label) ?? $label;
        $label = preg_replace('/\s*\)/', ')', $label) ?? $label;

        return trim($label);
    }

    public static function unitLabelsMatch(?string $a, ?string $b): bool
    {
        $a = self::normalizeUnitLabel($a);
        $b = self::normalizeUnitLabel($b);

        if ($a === $b) {
            return true;
        }

        $compact = static fn (string $value): string => preg_replace('/\s+/', '', $value) ?? $value;
        if ($compact($a) === $compact($b)) {
            return true;
        }

        $collapseLetterNumberGap = static fn (string $value): string => preg_replace('/(?<=[A-Z])\s+(?=\d)/', '', $value) ?? $value;

        return $collapseLetterNumberGap($a) === $collapseLetterNumberGap($b);
    }

    public static function registerUnitLabelMatch(?string $leaseLabel, ?string $registerLabel): bool
    {
        foreach (self::registerLabelParts($registerLabel) as $part) {
            if (self::registerUnitLabelMatchSingle($leaseLabel, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function registerLabelParts(?string $label): array
    {
        $label = self::normalizeUnitLabel($label);
        if ($label === '') {
            return [];
        }

        if (! str_contains($label, '&')) {
            return [$label];
        }

        return array_values(array_filter(array_map(
            static fn (string $part): string => self::normalizeUnitLabel($part),
            preg_split('/\s*&\s*/', $label) ?: [],
        )));
    }

    public static function canonicalizeLeaseUnitLabel(?string $label): string
    {
        $normalized = self::normalizeUnitLabel($label);
        if ($normalized === '') {
            return $normalized;
        }

        $core = self::extractCoreUnitToken($normalized);
        if ($core === '' || $core === $normalized) {
            return $normalized;
        }

        if (preg_match('/^(HSE|SHOP|S\d|[A-Z]\d)/i', $core)) {
            return $core;
        }

        return $normalized;
    }

    private static function registerUnitLabelMatchSingle(?string $leaseLabel, ?string $registerLabel): bool
    {
        if (self::unitLabelsMatch($leaseLabel, $registerLabel)) {
            return true;
        }

        $lease = self::normalizeUnitLabel($leaseLabel);
        $register = self::normalizeUnitLabel($registerLabel);

        if ($register !== '' && self::suffixTokenMatch($lease, $register)) {
            return true;
        }

        $leaseCore = self::extractCoreUnitToken($lease);
        $registerCore = self::extractCoreUnitToken($register);

        if ($leaseCore !== '' && $leaseCore === $registerCore) {
            return true;
        }

        $leaseTail = self::stripHousePrefix($leaseCore);
        $registerTail = self::stripHousePrefix($registerCore);

        if ($leaseTail !== '' && $leaseTail === $registerTail) {
            return true;
        }

        if ($register !== '' && str_starts_with($register, $lease) && strlen($lease) >= 4 && self::prefixMatchBoundary($register, $lease)) {
            return true;
        }

        if ($lease !== '' && str_starts_with($lease, $register) && strlen($register) >= 4 && self::prefixMatchBoundary($lease, $register)) {
            return true;
        }

        if (preg_match('/^UNIT\s+(\d+)$/i', $lease, $unitNumber)
            && preg_match('/^HSE\s+(?:M)?(\d+)$/i', $register, $registerNumber)) {
            return $unitNumber[1] === $registerNumber[1];
        }

        if (preg_match('/^HSE\s+(?:M)?(\d+)$/i', $lease, $registerNumber)
            && preg_match('/^UNIT\s+(\d+)$/i', $register, $unitNumber)) {
            return $unitNumber[1] === $registerNumber[1];
        }

        return false;
    }

    private static function suffixTokenMatch(string $haystack, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        // Bare numbers like "8" must not match prefixed labels like "SHOP 8".
        if (preg_match('/^\d+[A-Z]?$/', $token) === 1) {
            if (strcasecmp($haystack, $token) === 0) {
                return true;
            }

            return preg_match('/^(?:HSE|UNIT)\s+'.preg_quote($token, '/').'$/i', $haystack) === 1;
        }

        return preg_match('/(?:^|\s)'.preg_quote($token, '/').'$/i', $haystack) === 1;
    }

    private static function prefixMatchBoundary(string $haystack, string $prefix): bool
    {
        if (! str_starts_with($haystack, $prefix)) {
            return false;
        }

        $remainder = substr($haystack, strlen($prefix));

        return $remainder === '' || preg_match('/^\s/u', $remainder) === 1;
    }

    private static function stripHousePrefix(string $label): string
    {
        return self::normalizeUnitLabel(preg_replace('/^HSE\s+/i', '', $label) ?? $label);
    }

    public static function extractCoreUnitToken(string $label): string
    {
        if (preg_match('/\b(HSE\s+[A-Z]?\d+[A-Z]?)\b/i', $label, $match)) {
            return self::normalizeUnitLabel($match[1]);
        }

        if (preg_match('/\b(HSE\s+\d+(?:\s*&\s*\d+)?)\b/i', $label, $match)) {
            return self::normalizeUnitLabel($match[1]);
        }

        if (preg_match('/\b(SHOP\s+[A-Z]?\d+[A-Z]?)\b/i', $label, $match)) {
            return self::normalizeUnitLabel($match[1]);
        }

        if (preg_match('/\b(S\s*\d+[A-Z]?)\b/i', $label, $match)) {
            return self::normalizeUnitLabel(preg_replace('/\s+/', '', $match[1]) ?? $match[1]);
        }

        if (preg_match('/\b([A-Z]\d+[A-Z]?)\b/', $label, $match)) {
            return self::normalizeUnitLabel($match[1]);
        }

        if (preg_match('/^(\d+)$/', $label, $match)) {
            return $match[1];
        }

        return self::normalizeUnitLabel($label);
    }

    public static function parseMoney(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $clean = str_replace(',', '', trim($value));
        if (! is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 2);
    }

    public static function parseLegacyDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function stripRegisterNoise(string $text): string
    {
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^Sep \d+, \d{4}/', $line)) {
                continue;
            }
            if (preg_match('/^-- \d+ of \d+ --$/', $line)) {
                continue;
            }
            if (preg_match('/^PASSION SHELTAZ INVESTMENTS$/i', $line)) {
                continue;
            }
            if (preg_match('/^Property Register$/i', $line)) {
                continue;
            }
            if (preg_match('/^PROPERTY REGISTER$/i', $line)) {
                continue;
            }
            if (preg_match('/^PROPERTY UNITS LISTING$/i', $line)) {
                continue;
            }
            if (preg_match('/^ACTIVE TENANT & LEASES/i', $line)) {
                continue;
            }
            if (preg_match('/^Code Name/i', $line)) {
                continue;
            }
            if (preg_match('/^UNIT NO PROPERTY TENANT/i', $line)) {
                continue;
            }
            if (preg_match('/^PROPERTY UNIT # A\/C NO/i', $line)) {
                continue;
            }
            if (preg_match('/^(LEASE FROM|LEASE TO|PERIOD|DAYS TO|LEASE VARIATION|CURR\. ESC)/i', $line)) {
                continue;
            }
            if (preg_match('/^REVEIW START$/i', $line)) {
                continue;
            }
            if (preg_match('/^TYPE$/', $line)) {
                continue;
            }
            if (preg_match('/^EXPIRE$/', $line)) {
                continue;
            }
            if (preg_match('/^Pin No Address Email Phone Nos$/i', $line)) {
                continue;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    public static function cleanTenantName(?string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name) ?? '');
        $name = preg_replace('/^OCCP\s+/i', '', $name) ?? $name;
        $name = preg_replace('/\s+OCCP$/i', '', $name) ?? $name;

        return trim($name);
    }

    public static function mapUnitStatus(?string $status): string
    {
        $status = Str::lower(trim((string) $status));

        return match (true) {
            str_contains($status, 'owner') => \App\Models\PropertyUnit::STATUS_OCCUPIED,
            str_contains($status, 'occupied') => \App\Models\PropertyUnit::STATUS_OCCUPIED,
            default => \App\Models\PropertyUnit::STATUS_VACANT,
        };
    }

    public static function resolveImportedRentAmount(mixed $marketRent, mixed $currentRent, string $status): float
    {
        $market = (float) ($marketRent ?? 0);
        $current = (float) ($currentRent ?? 0);

        if ($status === \App\Models\PropertyUnit::STATUS_VACANT) {
            return $market > 0 ? $market : $current;
        }

        if ($current > 0) {
            return $current;
        }

        return $market;
    }

    public static function inferUnitType(?string $typeText, int $bedrooms): ?string
    {
        $typeText = Str::lower(trim((string) $typeText));

        return match (true) {
            str_contains($typeText, 'commercial') => \App\Models\PropertyUnit::TYPE_COMMERCIAL,
            str_contains($typeText, 'bed sitter'), str_contains($typeText, 'bedsitter'), str_contains($typeText, 'studio') => \App\Models\PropertyUnit::TYPE_BEDSITTER,
            str_contains($typeText, 'single room') => \App\Models\PropertyUnit::TYPE_SINGLE_ROOM,
            $bedrooms >= 2 => \App\Models\PropertyUnit::TYPE_APARTMENT,
            $bedrooms === 1 => \App\Models\PropertyUnit::TYPE_APARTMENT,
            default => null,
        };
    }
}
