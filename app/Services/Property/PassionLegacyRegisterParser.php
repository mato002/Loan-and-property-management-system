<?php

namespace App\Services\Property;

use Illuminate\Support\Str;

/**
 * Parses plain text extracted from Passion Shelters legacy "Property Register" PDF exports.
 *
 * Each record is one property with occupied/vacant unit counts (not individual units).
 */
final class PassionLegacyRegisterParser
{
    /** @var list<string> */
    private const FIELD_OFFICERS = [
        'ZAKARY NGANGA (MBUI)',
        'ZAKARY NGANGA',
        'ALLAN KIMANI',
    ];

    /** @var list<string> */
    private const PROPERTY_MARKERS = [
        'APARTMENTS',
        'APARTMENT',
        'APPARTMENT',
        'HOUSE',
        'COMPLEX',
        'RENTAL',
        'MAKAO',
        'TEACHERS',
        'KIAMUNYI',
        'GOROFA',
        'SUNRISE',
        'PAZURI',
        'WINTA',
        'GOSHEN',
        'BARNABAS',
        'NAKA',
        'LAMI',
        'NATEWA',
        'FLAMINGO',
        'ETB',
        'BESAWI',
        'WORKERS',
        'ELSHADAI',
        'SHALOM',
        'VIEW',
        'LUGAS',
        'LEMAYAN',
        'MUIGAI',
        'DUNDORI',
        'MUHORO',
        'TEACHER OFF',
    ];

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     landlord_name: string,
     *     email: ?string,
     *     phone: ?string,
     *     lr_number: ?string,
     *     category: ?string,
     *     location: ?string,
     *     commission_percent: ?float,
     *     occupied_count: int,
     *     vacant_count: int,
     *     field_officer: ?string,
     *     lpf_exempted: bool,
     *     date_acquired: ?string
     * }>
     */
    public function parse(string $text): array
    {
        $normalized = $this->normalizeText($text);
        $blocks = $this->splitIntoBlocks($normalized);
        $records = [];

        foreach ($blocks as $block) {
            $record = $this->parseBlock($block);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/\t+/", ' ', $text) ?? $text;

        $skipExact = [
            '-',
            'NO.',
            'Acquired',
            'PROPERTY REGISTER',
            'Category Location Zone Mgt.',
            'Fees(%)',
            'OccupiedVacant Field Officer L.P.F Exempted Date',
        ];

        $lines = explode("\n", $text);
        $clean = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/\s*Sep \d+, \d{4}, \d{1,2}:\d{2} (AM|PM)\s*$/', '', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                continue;
            }
            if (in_array($line, $skipExact, true)) {
                continue;
            }
            if (preg_match('/^-- \d+ of \d+ --$/', $line)) {
                continue;
            }
            if (preg_match('/^Sep \d+, \d{4}, \d{1,2}:\d{2} (AM|PM)$/', $line)) {
                continue;
            }
            if (str_contains($line, 'PASSION SHELTAZ INVESTMENTS')) {
                continue;
            }
            if (str_contains($line, 'Code Name Landlord')) {
                continue;
            }
            if (preg_match('/^\d+(?:\.\d+)?\s+\d+\s+\d+$/', $line)) {
                continue;
            }

            $clean[] = $line;
        }

        return implode("\n", $clean);
    }

    /**
     * @return list<string>
     */
    private function splitIntoBlocks(string $text): array
    {
        if (! preg_match_all('/^([A-Z]\d{5}[A-Z])\b/m', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $blocks = [];
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $start = $matches[0][$i][1];
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($text);
            $blocks[] = trim(substr($text, $start, $end - $start));
        }

        return array_values(array_filter($blocks, static fn ($block) => $block !== ''));
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     landlord_name: string,
     *     email: ?string,
     *     phone: ?string,
     *     lr_number: ?string,
     *     category: ?string,
     *     location: ?string,
     *     commission_percent: ?float,
     *     occupied_count: int,
     *     vacant_count: int,
     *     field_officer: ?string,
     *     lpf_exempted: bool,
     *     date_acquired: ?string
     * }|null
     */
    private function parseBlock(string $block): ?array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static fn ($l) => $l !== ''));
        if ($lines === []) {
            return null;
        }

        $firstLine = array_shift($lines);
        if ($firstLine === null || ! preg_match('/^([A-Z]\d{5}[A-Z])\b(?:\s+(.*))?$/', $firstLine, $codeMatch)) {
            return null;
        }

        $code = $codeMatch[1];
        if (($codeMatch[2] ?? '') !== '') {
            array_unshift($lines, $codeMatch[2]);
        }

        if ($lines === []) {
            return null;
        }

        $dataLineIndex = $this->findDataLineIndex($lines);
        $headLines = array_slice($lines, 0, $dataLineIndex);
        $tailText = $this->extractTailText($lines, $dataLineIndex);

        [$name, $landlordName] = $this->extractNameAndLandlord($headLines);
        $metrics = $this->parseTailMetrics($tailText);

        return [
            'code' => $code,
            'name' => $name !== '' ? $name : $code,
            'landlord_name' => $landlordName !== '' ? $landlordName : 'Unknown landlord',
            'email' => $metrics['email'],
            'phone' => $metrics['phone'],
            'lr_number' => null,
            'category' => $metrics['category'],
            'location' => $metrics['location'],
            'commission_percent' => $metrics['commission_percent'],
            'occupied_count' => $metrics['occupied_count'],
            'vacant_count' => $metrics['vacant_count'],
            'field_officer' => $metrics['field_officer'],
            'lpf_exempted' => $metrics['lpf_exempted'],
            'date_acquired' => $metrics['date_acquired'],
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    private function findDataLineIndex(array $lines): int
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', $line)) {
                return $index;
            }
            if (preg_match('/^0[\s\d@]/', $line)) {
                return $index;
            }
            if (preg_match('/(?:Residential|Commercial)?KENYA/i', $line) && preg_match('/\d+(?:\.\d+)?\s+\d+\s+\d+/', $line)) {
                return $index;
            }
        }

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*KENYA\s*$/i', $line)) {
                return $index;
            }
            if (preg_match('/\b(ALLAN KIMANI|ZAKARY NGANGA)/', $line)) {
                return $index;
            }
            if (preg_match('/\d+(?:\.\d+)?\s+\d+\s+\d+/', $line)) {
                return $index;
            }
        }

        return max(0, count($lines) - 1);
    }

    /**
     * @param  list<string>  $lines
     */
    private function extractTailText(array $lines, int $fromIndex): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_slice($lines, $fromIndex))) ?? '');
    }

    /**
     * @param  list<string>  $headLines
     * @return array{0: string, 1: string}
     */
    private function extractNameAndLandlord(array $headLines): array
    {
        if ($headLines === []) {
            return ['', ''];
        }

        $landlordStart = count($headLines);
        for ($i = count($headLines) - 1; $i >= 0; $i--) {
            if ($this->isLikelyLandlordLine($headLines[$i])) {
                $landlordStart = $i;
            } else {
                break;
            }
        }

        $nameLines = array_slice($headLines, 0, $landlordStart);
        $landlordLines = array_slice($headLines, $landlordStart);

        if ($nameLines === [] && $headLines !== []) {
            [$namePrefix, $landlordSuffix] = $this->splitPropertyPrefixFromLine($headLines[0]);
            $name = $this->cleanPropertyName($namePrefix);
            $landlordName = trim($landlordSuffix.' '.implode(' ', array_slice($headLines, 1)));

            if ($landlordName === '' && $name !== '') {
                $landlordName = $name;
            }

            return [$name, $landlordName];
        }

        $name = $this->cleanPropertyName(trim(preg_replace('/\s+/', ' ', implode(' ', $nameLines)) ?? ''));
        $landlordName = trim(preg_replace('/\s+/', ' ', implode(' ', $landlordLines)) ?? '');

        [$namePrefix, $nameSuffix] = $this->splitPropertyPrefixFromLine($name);
        if (
            $nameSuffix !== ''
            && $this->containsPropertyMarker($namePrefix)
            && ! preg_match('/^(MR|MRS|DR|MS)\.?\s/i', $namePrefix)
        ) {
            $name = $namePrefix;
            $landlordName = trim($nameSuffix.' '.$landlordName);
        }

        $name = $this->stripDuplicateLandlordSuffix($name, $landlordName);

        if ($landlordName === '' && $name !== '') {
            $landlordName = $name;
        }

        return [$name, $landlordName];
    }

    private function isLikelyLandlordLine(string $line): bool
    {
        if ($this->containsPropertyMarker($line)) {
            return false;
        }

        if (preg_match('/\([^)]*(?:TEACHERS|APARTMENT|VIEW|WORKERS|MAKAO|HOUSE|COMPLEX)/i', $line)) {
            return false;
        }

        if (preg_match('/^(MR\.?|MRS\.?|DR\.?|MS\.|MR & MRS\.)/i', $line)) {
            return true;
        }

        return (bool) preg_match('/^[A-Z][A-Z\s\'\-\.]+$/', $line);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPropertyPrefixFromLine(string $line): array
    {
        $words = preg_split('/\s+/', trim($line)) ?: [];
        $bestEnd = 0;

        for ($i = 1; $i <= count($words); $i++) {
            $prefix = implode(' ', array_slice($words, 0, $i));
            $word = $words[$i - 1];

            if (! $this->containsPropertyMarker($prefix)) {
                continue;
            }

            if ($this->looksLikePersonNameWord($word) && $bestEnd > 0) {
                break;
            }

            $bestEnd = $i;
        }

        if ($bestEnd > 0 && $bestEnd < count($words)) {
            return [
                implode(' ', array_slice($words, 0, $bestEnd)),
                implode(' ', array_slice($words, $bestEnd)),
            ];
        }

        return [$line, ''];
    }

    private function looksLikePersonNameWord(string $word): bool
    {
        $word = trim($word);
        if ($word === '' || $this->containsPropertyMarker($word)) {
            return false;
        }

        if (preg_match('/^[\(-]/', $word) || preg_match('/[\)-]$/', $word)) {
            return false;
        }

        return (bool) preg_match('/^[A-Z]{4,}$/', $word);
    }

    private function containsPropertyMarker(string $line): bool
    {
        $upper = Str::upper($line);
        foreach (self::PROPERTY_MARKERS as $marker) {
            if (str_contains($upper, $marker)) {
                return true;
            }
        }

        return (bool) preg_match('/\([^)]*(?:APARTMENT|HOUSE|TEACHERS|VIEW|WORKERS|MAKAO)/i', $line);
    }

    private function stripDuplicateLandlordSuffix(string $name, string $landlordName): string
    {
        $landlordName = trim($landlordName);
        $name = trim($name);

        if ($landlordName === '' || strlen($landlordName) < 12 || strlen($name) <= strlen($landlordName)) {
            return $name;
        }

        if (Str::upper(substr($name, -strlen($landlordName))) === Str::upper($landlordName)) {
            return trim(substr($name, 0, -strlen($landlordName)));
        }

        return $name;
    }

    private function cleanPropertyName(string $name): string
    {
        $name = preg_replace('/\s*Code Name Landlord.*$/i', '', $name) ?? $name;
        $name = preg_replace('/\s*Sep \d+, \d{4}.*$/i', '', $name) ?? $name;
        $name = preg_replace('/\s+\d+(?:\.\d+)?\s+\d+\s+\d+\s*(?:No|Yes).*$/i', '', $name) ?? $name;
        $name = trim(preg_replace('/\s{2,}/', ' ', $name) ?? $name);

        if (substr_count($name, '(') > substr_count($name, ')')) {
            $name .= ')';
        }

        $name = $this->removeRepeatedSuffix($name);
        $name = preg_replace('/\s+MR & MRS\..*$/i', '', $name) ?? $name;

        return trim($name);
    }

    private function removeRepeatedSuffix(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        if (count($words) < 6) {
            return $name;
        }

        for ($len = min(8, (int) floor(count($words) / 2)); $len >= 3; $len--) {
            $suffix = implode(' ', array_slice($words, -$len));
            $rest = implode(' ', array_slice($words, 0, -$len));

            if ($suffix !== '' && str_contains($rest, $suffix)) {
                return trim($rest);
            }
        }

        return $name;
    }

    /**
     * @return array{
     *     email: ?string,
     *     phone: ?string,
     *     category: ?string,
     *     location: ?string,
     *     commission_percent: ?float,
     *     occupied_count: int,
     *     vacant_count: int,
     *     field_officer: ?string,
     *     lpf_exempted: bool,
     *     date_acquired: ?string
     * }
     */
    private function parseTailMetrics(string $tail): array
    {
        $tail = $this->normalizeTailText($tail);
        $tail = preg_replace('/\s*Code Name Landlord.*$/i', '', $tail) ?? $tail;
        $tail = preg_replace('/\s*Sep \d+, \d{4}.*$/i', '', $tail) ?? $tail;
        $tail = trim($tail);

        $dateAcquired = null;
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $tail, $dateMatch)) {
            $dateAcquired = $dateMatch[1];
            $tail = trim(str_replace($dateMatch[0], '', $tail));
        }

        $lpfExempted = false;
        if (preg_match('/\b(Yes|No)\b/i', $tail, $lpfMatch)) {
            $lpfExempted = Str::lower($lpfMatch[1]) === 'yes';
        }

        $fieldOfficer = null;
        foreach (self::FIELD_OFFICERS as $officer) {
            if (str_contains($tail, $officer)) {
                $fieldOfficer = $officer;
                break;
            }
        }

        $email = null;
        if (preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', $tail, $emailMatch)) {
            $email = Str::lower($emailMatch[0]);
            $tail = trim(str_replace($emailMatch[0], '', $tail));
        }

        $phone = null;
        if (preg_match('/\b(0\d{9})\b/', $tail, $phoneMatch)) {
            $phone = $phoneMatch[1];
        }

        $category = $this->extractCategory($tail);
        $location = $this->extractLocation($tail);

        [$commissionPercent, $occupiedCount, $vacantCount] = $this->extractCountTriplet($tail);

        return [
            'email' => $email,
            'phone' => $phone,
            'category' => $category,
            'location' => $location,
            'commission_percent' => $commissionPercent,
            'occupied_count' => $occupiedCount,
            'vacant_count' => $vacantCount,
            'field_officer' => $fieldOfficer,
            'lpf_exempted' => $lpfExempted,
            'date_acquired' => $dateAcquired,
        ];
    }

    /**
     * @return array{0: ?float, 1: int, 2: int}
     */
    private function extractCountTriplet(string $tail): array
    {
        if (preg_match('/(?:Residential|Commercial)?KENYA[^0-9]*(\d+(?:\.\d+)?)\s+(\d+)\s+(\d+)/i', $tail, $kenyaMatch)) {
            return $this->normalizeCountTriplet(
                (float) $kenyaMatch[1],
                (int) $kenyaMatch[2],
                (int) $kenyaMatch[3],
            );
        }

        if (preg_match_all('/(\d+(?:\.\d+)?)\s+(\d+)\s+(\d+)/', $tail, $matches, PREG_SET_ORDER)) {
            $fallback = null;

            foreach ($matches as $match) {
                $normalized = $this->normalizeCountTriplet(
                    (float) $match[1],
                    (int) $match[2],
                    (int) $match[3],
                );

                if ($normalized[1] === 0 && $normalized[2] === 0 && $normalized[0] === null) {
                    continue;
                }

                if ($normalized[0] !== null) {
                    return $normalized;
                }

                $fallback = $normalized;
            }

            if ($fallback !== null) {
                return $fallback;
            }
        }

        return [null, 0, 0];
    }

    /**
     * @return array{0: ?float, 1: int, 2: int}
     */
    private function normalizeCountTriplet(float $fee, int $occupied, int $vacant): array
    {
        if ($occupied > 500 || $vacant > 500) {
            return [null, 0, 0];
        }

        if ($fee >= 0 && $fee <= 100) {
            return [round($fee, 2), $occupied, $vacant];
        }

        return [null, $occupied, $vacant];
    }

    private function extractCategory(string $text): ?string
    {
        if (stripos($text, 'Commercial') !== false && stripos($text, 'Residential') !== false) {
            return 'commercial_residential';
        }
        if (stripos($text, 'Commercial') !== false) {
            return 'commercial';
        }
        if (stripos($text, 'Residential') !== false) {
            return 'residential';
        }

        return null;
    }

    private function extractLocation(string $text): ?string
    {
        if (preg_match('/((?:NAKURU(?:\s*-\s*DUNDORI)?|OLIVE INN)[^KENYA]*?(?:KENYA)?)/i', $text, $match)) {
            return trim(preg_replace('/\s+/', ' ', $match[1]) ?? $match[1]);
        }
        if (preg_match('/(NAKURU\s+MUNICIPALITY[^KENYA]*?(?:KENYA)?)/i', $text, $match)) {
            return trim(preg_replace('/\s+/', ' ', $match[1]) ?? $match[1]);
        }
        if (stripos($text, 'KENYA') !== false) {
            return 'KENYA';
        }

        return null;
    }

    private function normalizeTailText(string $tail): string
    {
        $tail = preg_replace('/(Residential|Commercial)(KENYA)/i', '$1 $2', $tail) ?? $tail;
        $tail = preg_replace('/(\.[A-Za-z]{2,})(0\d{9})/', '$1 $2', $tail) ?? $tail;
        $tail = preg_replace('/(@[\w.+-]+\.[A-Za-z]{2,})(0\d)/', '$1 $2', $tail) ?? $tail;
        $tail = preg_replace('/(\d)(Residential|Commercial)/i', '$1 $2', $tail) ?? $tail;

        return trim(preg_replace('/\s{2,}/', ' ', $tail) ?? $tail);
    }
}
