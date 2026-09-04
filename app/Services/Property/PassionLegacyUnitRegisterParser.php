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
                if ($buffer !== '' && $this->bufferLooksComplete($buffer)) {
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

            if ($buffer !== '' && ! $this->bufferLooksComplete($buffer) && $this->looksLikeRegisterAmountContinuation($line)) {
                $buffer .= ' '.$line;
                if ($this->bufferLooksComplete($buffer)) {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                continue;
            }

            if ($buffer !== '' && $this->looksLikeStatusTail($line)) {
                $buffer .= ' '.$line;
                if ($this->bufferLooksComplete($buffer)) {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                continue;
            }

            if ($buffer !== '' && $this->looksLikeTenantAmountLine($line)) {
                $buffer .= ' '.$line;
                if ($this->bufferLooksComplete($buffer)) {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                continue;
            }

            if ($buffer !== '' && $this->looksLikePropertyNameContinuation($line)) {
                $buffer .= ' '.$line;
                continue;
            }

            if ($this->looksLikeUnitStart($line)) {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                }
                $buffer = $line;
                continue;
            }

            if ($buffer !== '' && $this->looksLikeUnitTypeContinuation($line)) {
                $buffer .= ' '.$line;
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
                if ($this->bufferLooksComplete($buffer)) {
                    $merged[] = $buffer;
                    $buffer = '';
                }
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

        if (! preg_match('/^(.+?)\s+([\d,]+\.\d{2})\s+([\d,]+\.\d{2})(?:\s+([\d,]+\.\d{2}))?\s+(.*?)\s+(\d+)\s+'.$this->legacyStatusPattern().'\s*(\d{2}\/\d{2}\/\d{4})?\s*$/i', $rest, $tail)) {
            return null;
        }

        $beforeAmounts = trim($tail[1]);
        $propertyName = $fallbackProperty;
        $tenantName = null;
        $floor = null;

        if (preg_match('/^(.+?\([^)]+\))\s+(\d+)$/', trim($beforeAmounts), $split)) {
            $propertyName = trim($split[1]);
            $floor = $split[2];
        } elseif (preg_match('/^(.+?\([^)]+\))$/', trim($beforeAmounts))) {
            $propertyName = trim($beforeAmounts);
        } elseif (preg_match('/^(.+?\([^)]+\))\s+(.+)$/', $beforeAmounts, $split)) {
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

        $amounts = $this->parseRegisterAmounts($tail);
        $meta = trim($tail[5]);
        $bedrooms = (int) $tail[6];
        $typeText = $meta;
        if ($floor === null && preg_match('/^(\d+)\s+(.*)$/', $meta, $metaMatch)) {
            $floor = $metaMatch[1];
            $typeText = trim($metaMatch[2]);
        } elseif ($floor === null && preg_match('/^\d+$/', $meta)) {
            $floor = $meta;
            $typeText = '';
        }

        if (preg_match('/owner/i', (string) $tail[7])) {
            $tenantName = null;
        }

        return [
            'property_name' => trim($propertyName),
            'unit_label' => $unitLabel,
            'tenant_name' => $tenantName !== '' ? $tenantName : null,
            'legacy_area' => $amounts['legacy_area'],
            'market_rent' => $amounts['market_rent'],
            'current_rent' => $amounts['current_rent'],
            'floor' => $floor !== '' ? $floor : null,
            'unit_type_text' => $typeText !== '' ? $typeText : null,
            'bedrooms' => $bedrooms,
            'furnished' => false,
            'status' => PassionLegacyTextNormalizer::mapUnitStatus($tail[7]),
            'available_from' => PassionLegacyTextNormalizer::parseLegacyDate($tail[8] ?? null),
        ];
    }

    /**
     * @param  array<int, string>  $tail
     * @return array{legacy_area: ?float, market_rent: ?float, current_rent: ?float}
     */
    private function parseRegisterAmounts(array $tail): array
    {
        $first = PassionLegacyTextNormalizer::parseMoney($tail[2]);
        $second = PassionLegacyTextNormalizer::parseMoney($tail[3]);
        $third = isset($tail[4]) && preg_match('/^[\d,]+\.\d{2}$/', trim((string) $tail[4])) === 1
            ? PassionLegacyTextNormalizer::parseMoney($tail[4])
            : null;

        if ($third !== null) {
            return [
                'legacy_area' => $first,
                'market_rent' => $second,
                'current_rent' => $third,
            ];
        }

        if ($first === 0.0) {
            return [
                'legacy_area' => 0.0,
                'market_rent' => $second,
                'current_rent' => $second,
            ];
        }

        if ($first === $second) {
            return [
                'legacy_area' => $first,
                'market_rent' => $second,
                'current_rent' => $second,
            ];
        }

        if ($first > $second) {
            return [
                'legacy_area' => 0.0,
                'market_rent' => $first,
                'current_rent' => $second,
            ];
        }

        return [
            'legacy_area' => $first,
            'market_rent' => $second,
            'current_rent' => $second,
        ];
    }

    private function looksLikeUnitStart(string $line): bool
    {
        if ($this->looksLikeStatusTail($line)) {
            return false;
        }

        return (bool) preg_match('/^'.$this->unitLabelPattern().'\s+/i', $line);
    }

    private function unitLabelPattern(): string
    {
        return '(?:CARWASH|SHOP(?:\s+(?:[A-Z]?\d+(?:\s*&\s*\d+)?|[A-Z]\d+))|RENTAL\s+HOUSE(?:\s+[A-Z]+)?|HSE\s+[A-Z]?\d+[A-Z]?(?:\s*&\s*\d+)?(?:\s*\(\d+BR\))?|HSE\s+[A-Z]\d+|[A-Z]\s+\d+(?:\s*\(\d+BR\))?|[A-Z]\d+(?:\s*\(\d+BR\))?|SINGLE|\d+)';
    }

    private function legacyStatusPattern(): string
    {
        return '(Owner(?:\s+Occupied)?|Occupied|Vacant)';
    }

    private function legacyStatusTailPattern(): string
    {
        return $this->legacyStatusPattern();
    }

    private function looksLikeTenantTail(string $line): bool
    {
        return (bool) preg_match('/[\d,]+\.\d{2}\s+[\d,]+\.\d{2}\s+.*\s+'.$this->legacyStatusTailPattern().'/i', $line);
    }

    private function looksLikeStatusTail(string $line): bool
    {
        return (bool) preg_match('/^(?:\d+\s+)?'.$this->legacyStatusTailPattern().'\b/i', $line);
    }

    private function looksLikeUnitTypeContinuation(string $line): bool
    {
        return (bool) preg_match('/^(?:Sitter|Bed\s+Sitter|Studio\/Bed\s+Sitter|Retail\/Shop)$/i', $line);
    }

    private function looksLikeTenantAmountLine(string $line): bool
    {
        return (bool) preg_match('/^\d+\s+[A-Z].+[\d,]+\.\d{2}\s+[\d,]+\.\d{2}/i', $line)
            || $this->looksLikeRegisterAmountContinuation($line);
    }

    private function looksLikeRegisterAmountContinuation(string $line): bool
    {
        return (bool) preg_match('/^(?:\d+\s+)?[\d,]+\.\d{2}\s+[\d,]+\.\d{2}/', trim($line))
            && (bool) preg_match('/\b(?:Vacant|Occupied|Owner(?:\s+Occupied)?)\b/i', $line);
    }

    private function looksLikePropertyNameContinuation(string $line): bool
    {
        return (bool) preg_match('/^[A-Z0-9][A-Z0-9 ()-]*\)?$/i', $line)
            && ! preg_match('/\b(HSE|SHOP|CARWASH|RENTAL)\b/i', $line);
    }

    private function bufferLooksComplete(string $buffer): bool
    {
        return (bool) preg_match('/\s+'.$this->legacyStatusTailPattern().'\b/i', $buffer);
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
