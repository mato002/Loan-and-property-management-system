<?php

namespace App\Services\Property;

final class PassionLegacyLeasesRegisterParser
{
    /**
     * @return list<array{
     *     property_code: string,
     *     unit_label: string,
     *     account_number: string,
     *     tenant_name: string,
     *     phone: ?string,
     *     account_balance: ?float,
     *     monthly_rent: ?float,
     *     lease_start: ?string,
     *     lease_end: ?string,
     *     lease_period_days: ?int,
     *     days_to_expire: ?int,
     *     lease_variation_type: ?string,
     *     escalation_review_start: ?string
     * }>
     */
    public function parse(string $text): array
    {
        $records = [];
        $currentPropertyCode = '';

        foreach ($this->logicalLines(PassionLegacyTextNormalizer::stripRegisterNoise($text)) as $line) {
            if (preg_match('/^\[([A-Z]\d{5}[A-Z]?)\]/', $line, $codeMatch)) {
                $currentPropertyCode = $codeMatch[1];
            }

            if ($this->isFooterTotal($line)) {
                continue;
            }

            $linePropertyCode = $currentPropertyCode;
            if (preg_match('/^\[([A-Z]\d{5}[A-Z]?)\]\s*(.+)$/', $line, $inlineMatch)) {
                $linePropertyCode = $inlineMatch[1];
                $currentPropertyCode = $inlineMatch[1];
                $line = trim($inlineMatch[2]);
            }

            $record = $this->parseLeaseLine($line, $linePropertyCode);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    private function logicalLines(string $text): array
    {
        $text = preg_replace('/(HSE\s+[A-Z]?\d+)\s*\n\s*(\(\d+BR\))/i', '$1 $2', $text) ?? $text;
        $text = preg_replace('/(HSE\s+\d+)\s*\n\s*(\(\d+BR\))/i', '$1 $2', $text) ?? $text;

        $merged = [];
        $buffer = '';
        $unitPrefix = '';

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($this->isFooterTotal($line)) {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                $unitPrefix = '';
                continue;
            }

            if (preg_match('/^\[[A-Z]\d{5}[A-Z]?\]/', $line) && ! preg_match('/\bTNT\d+/i', $line)) {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                $merged[] = $line;
                continue;
            }

            if ($this->isUnitPrefixLine($line)) {
                $unitPrefix = trim($unitPrefix !== '' ? $unitPrefix.' '.$line : $line);
                continue;
            }

            if (preg_match('/^\s*TNT\d+/i', $line)) {
                $line = trim(($unitPrefix !== '' ? $unitPrefix.' ' : '').$line);
                $unitPrefix = '';
            }

            if ($this->looksLikeLeaseStart($line) || preg_match('/^\s*TNT\d+/i', $line)) {
                if ($buffer !== '') {
                    $merged[] = $buffer;
                }
                $buffer = $line;
                if ($this->rowLooksComplete($buffer)) {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                continue;
            }

            if ($buffer !== '') {
                $buffer .= ' '.$line;
                if ($this->rowLooksComplete($buffer)) {
                    $merged[] = $buffer;
                    $buffer = '';
                }
                continue;
            }

            if (preg_match('/\bTNT\d+/i', $line)) {
                $merged[] = $line;
            }
        }

        if ($buffer !== '') {
            $merged[] = $buffer;
        }

        return $merged;
    }

    private function isUnitPrefixLine(string $line): bool
    {
        return ! preg_match('/\bTNT\d+/i', $line)
            && (bool) preg_match('/^(?:HSE\s+[A-Z0-9 ()-]+|SHOP(?:\s+[A-Z0-9&]+)*|[A-Z][A-Z0-9 ()-]*|\d+)$/i', $line);
    }

    private function rowLooksComplete(string $line): bool
    {
        if (! preg_match('/\bTNT\d+/i', $line)) {
            return false;
        }

        if (preg_match('/\b0\d{9}\b/', $line)) {
            return true;
        }

        return (bool) preg_match('/TNT\d+.+\s+-?[\d,]+\s+-?[\d,]+/i', $line);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseLeaseLine(string $line, string $propertyCode): ?array
    {
        if ($propertyCode === '' || ! preg_match('/\bTNT\d+/i', $line)) {
            return null;
        }

        if (! preg_match('/\b(TNT\d+)(.*)$/i', $line, $match)) {
            return null;
        }

        $accountNumber = strtoupper($match[1]);
        $rest = trim($match[2]);
        $beforeTnt = trim(substr($line, 0, (int) strpos($line, $match[1])));

        $unitLabel = '';
        if (preg_match('/((?:HSE\s+[A-Z0-9 ()-]+|SHOP\s+[A-Z0-9]+|[A-Z][A-Z0-9 ()-]*|\d+))\s*$/i', $beforeTnt, $unitMatch)) {
            $unitLabel = PassionLegacyTextNormalizer::normalizeUnitLabel($unitMatch[1]);
        } elseif (preg_match('/^((?:HSE\s+[A-Z0-9 ()-]+|SHOP\s+[A-Z0-9]+|[A-Z][A-Z0-9 ()-]*|\d+))\s+/i', $beforeTnt, $unitMatch)) {
            $unitLabel = PassionLegacyTextNormalizer::normalizeUnitLabel($unitMatch[1]);
        }

        if ($unitLabel === '' && preg_match('/^\s*TNT\d+/i', $line)) {
            $unitLabel = 'UNIT-'.substr($accountNumber, -4);
        }

        $phone = null;
        $tenantName = '';
        $tail = $rest;

        if (preg_match('/\b(0\d{9})\b/', $rest, $phoneMatch, PREG_OFFSET_CAPTURE)) {
            $phone = $phoneMatch[1][0];
            $phonePos = (int) $phoneMatch[1][1];
            $tenantName = PassionLegacyTextNormalizer::cleanTenantName(substr($rest, 0, $phonePos));
            $tail = trim(substr($rest, $phonePos + strlen($phone)));
        } else {
            if (! preg_match('/^(.+?)\s+(-?[\d,]+)\s+(-?[\d,]+)\s*(.*)$/', $rest, $noPhoneMatch)) {
                return null;
            }
            $tenantName = PassionLegacyTextNormalizer::cleanTenantName($noPhoneMatch[1]);
            $tail = trim($noPhoneMatch[2].' '.$noPhoneMatch[3].' '.($noPhoneMatch[4] ?? ''));
        }

        if (! preg_match('/^(-?[\d,]+)\s+(-?[\d,]+)\s*(.*)$/', $tail, $amountMatch)) {
            return null;
        }

        $accountBalance = PassionLegacyTextNormalizer::parseMoney($amountMatch[1]);
        $monthlyRent = PassionLegacyTextNormalizer::parseMoney($amountMatch[2]);
        $dateTail = trim($amountMatch[3]);

        $leaseStart = null;
        $leaseEnd = null;
        $leasePeriodDays = null;
        $daysToExpire = null;
        $variationType = null;
        $escalationReviewStart = null;

        if (preg_match_all('/\d{2}\/\d{2}\/\d{4}/', $dateTail, $dateMatches)) {
            $dates = $dateMatches[0];
            $leaseStart = PassionLegacyTextNormalizer::parseLegacyDate($dates[0] ?? null);
            $leaseEnd = PassionLegacyTextNormalizer::parseLegacyDate($dates[1] ?? null);
            if (isset($dates[2])) {
                $escalationReviewStart = PassionLegacyTextNormalizer::parseLegacyDate($dates[2]);
            }
        }

        if (preg_match('/\b(Revision|Renewal|New Lease|New)\b/i', $dateTail, $variationMatch)) {
            $variationType = ucfirst(strtolower(trim($variationMatch[1])));
            if ($variationType === 'New') {
                $variationType = 'New Lease';
            }
        }

        if (preg_match('/\b(\d{1,4})\b/', preg_replace('/\d{2}\/\d{2}\/\d{4}/', '', $dateTail) ?? '', $periodMatch)) {
            $leasePeriodDays = (int) $periodMatch[1];
        }

        if ($leaseEnd !== null) {
            try {
                $daysToExpire = max(0, (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($leaseEnd), false));
            } catch (\Throwable) {
                $daysToExpire = null;
            }
        }

        if ($tenantName === '' || $unitLabel === '') {
            return null;
        }

        return [
            'property_code' => $propertyCode,
            'unit_label' => PassionLegacyTextNormalizer::canonicalizeLeaseUnitLabel($unitLabel),
            'account_number' => $accountNumber,
            'tenant_name' => $tenantName,
            'phone' => $phone,
            'account_balance' => $accountBalance,
            'monthly_rent' => $monthlyRent,
            'lease_start' => $leaseStart,
            'lease_end' => $leaseEnd,
            'lease_period_days' => $leasePeriodDays,
            'days_to_expire' => $daysToExpire,
            'lease_variation_type' => $variationType,
            'escalation_review_start' => $escalationReviewStart,
        ];
    }

    private function looksLikeLeaseStart(string $line): bool
    {
        return (bool) preg_match('/^(?:\[?[A-Z]\d{5}[A-Z]?\]?\s+)?(?:HSE\s+[A-Z0-9 ()-]+|SHOP\s+[A-Z0-9]+|[A-Z]\d+|Z\s*-\s*HOUSE\s+SHOP\s+\d+)\s+TNT/i', $line)
            || (bool) preg_match('/^\[[A-Z]\d{5}[A-Z]?\]\s+.+\s+TNT/i', $line);
    }

    private function isFooterTotal(string $line): bool
    {
        return (bool) preg_match('/^\d+\s+[\d,]+(?:\s+[\d,]+)?(?:Sep \d+, \d{4})?$/', $line);
    }
}
