<?php

namespace App\Services\Property;

final class PassionLegacyUnitRegisterParser
{
    /**
     * @return list<array{
     *     property_name: string,
     *     unit_label: string,
     *     tenant_name: ?string,
     *     legacy_area: ?float,
     *     market_rent: ?float,
     *     current_rent: ?float,
     *     floor: ?string,
     *     unit_type_text: ?string,
     *     bedrooms: int,
     *     furnished: bool,
     *     status: string,
     *     available_from: ?string
     * }>
     */
    public function parse(string $text): array
    {
        $text = $this->preprocess(PassionLegacyTextNormalizer::stripRegisterNoise($text));
        $records = [];
        $currentProperty = '';

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($this->isPropertySectionHeader($line)) {
                $currentProperty = $this->extractPropertyNameFromHeader($line);
                continue;
            }

            if ($this->isFooterTotal($line)) {
                continue;
            }

            if (preg_match('/^(\d+)\s+([A-Z]\d{5})\s+/', $line)) {
                continue;
            }

            $record = $this->parseUnitLine($line, $currentProperty);
            if ($record !== null) {
                if ($record['property_name'] === '' && $currentProperty !== '') {
                    $record['property_name'] = $currentProperty;
                }
                if ($record['property_name'] !== '') {
                    $currentProperty = $record['property_name'];
                }
                $records[] = $record;
            }
        }

        return $records;
    }

    private function preprocess(string $text): string
    {
        $text = preg_replace('/(\d{2}\/\d{2}\/\d{4})Sep[^\n]*/i', '$1', $text) ?? $text;
        $text = preg_replace('/\s*Sep \d+, \d{4}[^\n]*/mi', '', $text) ?? $text;
        $text = preg_replace('/(HSE\s+[A-Z]?\d+)\s*\n\s*(\(\d+BR\))/i', '$1 $2', $text) ?? $text;
        $text = preg_replace('/(HSE\s+\d+)\s*\n\s*(\(\d+BR\))/i', '$1 $2', $text) ?? $text;

        $merged = [];
        $buffer = '';
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                continue;
            }

            if ($this->isPropertySectionHeader($line) || $this->isFooterTotal($line)) {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                $merged[] = $line;
                continue;
            }

            if ($this->looksLikeUnitStart($line)) {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                }
                $buffer = $line;
                continue;
            }

            if ($buffer !== '' && $this->looksLikeTenantTail($line)) {
                $buffer .= ' '.$line;
                $merged[] = $buffer;
                $buffer = '';
                continue;
            }

            if ($buffer !== '') {
                $buffer .= ' '.$line;
                continue;
            }

            $merged[] = $line;
        }

        if ($buffer !== '') {
            $merged[] = $buffer;
        }

        return implode("\n", $merged);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseUnitLine(string $line, string $fallbackProperty): ?array
    {
        if (! preg_match('/^('.$this->unitLabelPattern().')\s+(.+)$/i', $line, $match)) {
            return null;
        }

        $unitLabel = PassionLegacyTextNormalizer::normalizeUnitLabel($match[1]);
        $rest = trim($match[2]);

        if (! preg_match('/^(.+?)\s+([\d,]+\.\d{2})\s+([\d,]+\.\d{2})\s+(.*?)\s+(\d+)\s+(Occupied|Vacant|Owner\s*Occupied)\s*(\d{2}\/\d{2}\/\d{4})?\s*$/i', $rest, $tail)) {
            return null;
        }

        $beforeAmounts = trim($tail[1]);
        $propertyName = $fallbackProperty;
        $tenantName = null;

        if (preg_match('/^(.+?\([^)]+\))\s+(.+)$/', $beforeAmounts, $split)) {
            $propertyName = trim($split[1]);
            $tenantName = PassionLegacyTextNormalizer::cleanTenantName($split[2]);
        } elseif (preg_match('/^(.+?APPARTMENT\s+[AB])\s+(.+)$/i', $beforeAmounts, $split)) {
            $propertyName = trim($split[1]);
            $tenantName = PassionLegacyTextNormalizer::cleanTenantName($split[2]);
        } elseif (preg_match('/^(.+?\bAPPARTMENTS?|\bAPPARTMENT\s+[AB]|\bCOMPLEX|\bGOSHEN APARTMENT|\bZ - HOUSE|\bKIAMUNYI)\s+(.+)$/i', $beforeAmounts, $split)) {
            $propertyName = trim($split[1]);
            $tenantName = PassionLegacyTextNormalizer::cleanTenantName($split[2]);
        } elseif ($fallbackProperty !== '') {
            $tenantName = PassionLegacyTextNormalizer::cleanTenantName($beforeAmounts);
        } else {
            $parts = preg_split('/\s{2,}/', $beforeAmounts) ?: [$beforeAmounts];
            $propertyName = trim($parts[0] ?? $beforeAmounts);
            $tenantName = isset($parts[1]) ? PassionLegacyTextNormalizer::cleanTenantName($parts[1]) : null;
        }

        $meta = trim($tail[4]);
        $bedrooms = (int) $tail[5];
        $floor = null;
        $typeText = $meta;
        if (preg_match('/^(\d+)\s+(.*)$/', $meta, $metaMatch)) {
            $floor = $metaMatch[1];
            $typeText = trim($metaMatch[2]);
        } elseif (preg_match('/^\d+$/', $meta)) {
            $floor = $meta;
            $typeText = '';
        }

        return [
            'property_name' => trim($propertyName),
            'unit_label' => $unitLabel,
            'tenant_name' => $tenantName !== '' ? $tenantName : null,
            'legacy_area' => PassionLegacyTextNormalizer::parseMoney($tail[2]),
            'market_rent' => PassionLegacyTextNormalizer::parseMoney($tail[3]),
            'current_rent' => PassionLegacyTextNormalizer::parseMoney($tail[3]),
            'floor' => $floor !== '' ? $floor : null,
            'unit_type_text' => $typeText !== '' ? $typeText : null,
            'bedrooms' => $bedrooms,
            'furnished' => false,
            'status' => PassionLegacyTextNormalizer::mapUnitStatus($tail[6]),
            'available_from' => PassionLegacyTextNormalizer::parseLegacyDate($tail[7] ?? null),
        ];
    }

    private function looksLikeUnitStart(string $line): bool
    {
        return (bool) preg_match('/^'.$this->unitLabelPattern().'\s+/i', $line);
    }

    private function unitLabelPattern(): string
    {
        return '(?:CARWASH|SHOP(?:\s+[A-Z0-9&]+)+|RENTAL\s+HOUSE(?:\s+[A-Z]+)?|HSE\s+[A-Z]?\d+(?:\s*\(\d+BR\))?|HSE\s+[A-Z]\d+|[A-Z]\d+(?:\s*\(\d+BR\))?|\d+)';
    }

    private function looksLikeTenantTail(string $line): bool
    {
        return (bool) preg_match('/[\d,]+\.\d{2}\s+[\d,]+\.\d{2}\s+.*\s+(Occupied|Vacant|Owner\s*Occupied)/i', $line);
    }

    private function isPropertySectionHeader(string $line): bool
    {
        return (bool) preg_match('/,\s*(?:RESIDENTIAL|Commercial|KENYA)\b/i', $line)
            || (bool) preg_match('/,\s*KENYA\b.*f\/a:/i', $line);
    }

    private function extractPropertyNameFromHeader(string $line): string
    {
        $line = preg_replace('/,\s*(?:RESIDENTIAL|Commercial).*$/i', '', $line) ?? $line;
        $line = preg_replace('/,\s*KENYA.*$/i', '', $line) ?? $line;

        return trim($line);
    }

    private function isFooterTotal(string $line): bool
    {
        return (bool) preg_match('/^\d+\s+[\d,]+(?:\s+[\d,]+)?(?:Sep \d+, \d{4})?$/', $line)
            || (bool) preg_match('/^[\d,]+\s+[\d,]+(?:Sep \d+, \d{4})?$/', $line);
    }
}
