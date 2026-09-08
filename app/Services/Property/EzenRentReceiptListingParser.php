<?php

namespace App\Services\Property;

use Illuminate\Support\Str;
use RuntimeException;

final class EzenRentReceiptListingParser
{
    /**
     * @return list<array{
     *     ezen_receipt_no:string,
     *     property_code:string,
     *     txn_date:string,
     *     banking_date:string,
     *     ref_no:string,
     *     unit_label:string,
     *     tnt_account:string,
     *     tenant_name:string,
     *     phone:string,
     *     particulars:string,
     *     amount:float,
     *     receipted_to:string,
     *     done_by:string
     * }>
     */
    public function parsePath(string $path): array
    {
        return $this->parseText($this->readText($path));
    }

    /**
     * @return list<array{
     *     ezen_receipt_no:string,
     *     property_code:string,
     *     txn_date:string,
     *     banking_date:string,
     *     ref_no:string,
     *     unit_label:string,
     *     tnt_account:string,
     *     tenant_name:string,
     *     phone:string,
     *     particulars:string,
     *     amount:float,
     *     receipted_to:string,
     *     done_by:string
     * }>
     */
    public function parseText(string $text): array
    {
        $rows = [];
        $propertyCode = '';
        $buffer = null;

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $rawLine) {
            $line = trim(preg_replace('/\s+/', ' ', $rawLine) ?? '');
            $line = trim((string) preg_replace('/Printed:.*$/i', '', $line));
            $line = trim((string) preg_replace('/Powered by EZEN.*$/i', '', $line));
            if ($line === '' || $this->isNoiseLine($line)) {
                continue;
            }

            if (preg_match('/^([A-Z0-9]+)\s+~\s+/i', $line, $propertyHeader) === 1) {
                $propertyCode = strtoupper($propertyHeader[1]);
                continue;
            }

            if (preg_match('/^RC(\d+)\s/i', $line, $start) === 1) {
                if ($buffer !== null) {
                    $parsed = $this->tryParseBufferedRow($buffer, $propertyCode);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                    }
                }
                $buffer = ['property_code' => $propertyCode, 'parts' => [$line]];

                continue;
            }

            if ($buffer !== null) {
                $buffer['parts'][] = $line;
                $combined = trim(implode(' ', $buffer['parts']));
                $parsed = $this->tryParseReceiptLine($combined, $propertyCode);
                if ($parsed !== null) {
                    $rows[] = $parsed;
                    $buffer = null;
                }
            }
        }

        if ($buffer !== null) {
            $parsed = $this->tryParseBufferedRow($buffer, $propertyCode);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * @param  array{property_code:string, parts:list<string>}  $buffer
     * @return array<string, mixed>|null
     */
    private function tryParseBufferedRow(array $buffer, string $fallbackPropertyCode): ?array
    {
        return $this->tryParseReceiptLine(trim(implode(' ', $buffer['parts'])), $buffer['property_code'] ?: $fallbackPropertyCode);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryParseReceiptLine(string $line, string $propertyCode): ?array
    {
        if (preg_match('/^RC(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})\s+(\S+)\s+(.+)$/i', $line, $head) !== 1) {
            return null;
        }

        $tail = trim((string) $head[5]);
        if (preg_match('/^(.+)\s+([\d,]+)\s+(CO-OPERATIVE BANK|CO-OP BANK|M-PESA|MPESA|EQUITY BANK|KCB BANK|STANBIC BANK|NCBA BANK|ABSA BANK|DTB BANK)\s+(.+)$/i', $tail, $end) !== 1) {
            return null;
        }

        $middle = trim((string) $end[1]);
        $amount = $this->money((string) $end[2]);
        $receiptedTo = trim((string) $end[3]);
        $doneBy = trim((string) $end[4]);

        if (! preg_match('/\b(TNT\d+)\b/i', $middle, $tntMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $tntAccount = strtoupper($tntMatch[1][0]);
        $tntPos = (int) $tntMatch[0][1];
        $unitLabel = trim(substr($middle, 0, $tntPos));
        $afterTnt = trim(substr($middle, $tntPos + strlen($tntAccount)));

        $phone = '';
        $tenantName = $afterTnt;
        $particulars = '';
        if (preg_match('/^(.+?)\s+(0\d{9})\s*(.*)$/s', $afterTnt, $tenantParts) === 1) {
            $tenantName = trim((string) $tenantParts[1]);
            $phone = trim((string) $tenantParts[2]);
            $particulars = trim((string) $tenantParts[3]);
        }

        if ($tenantName === '' || $amount <= 0) {
            return null;
        }

        return [
            'ezen_receipt_no' => 'RC'.strtoupper($head[1]),
            'property_code' => strtoupper(trim($propertyCode)),
            'txn_date' => $this->parseDate((string) $head[2]),
            'banking_date' => $this->parseDate((string) $head[3]),
            'ref_no' => strtoupper(trim((string) $head[4])),
            'unit_label' => $unitLabel,
            'tnt_account' => $tntAccount,
            'tenant_name' => $tenantName,
            'phone' => $phone,
            'particulars' => $particulars !== '' ? $particulars : $tenantName,
            'amount' => $amount,
            'receipted_to' => $receiptedTo,
            'done_by' => $doneBy,
        ];
    }

    private function parseDate(string $value): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($value), $m) !== 1) {
            throw new RuntimeException('Invalid date: '.$value);
        }

        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    private function money(string $value): float
    {
        $raw = str_replace([',', ' '], '', $value);

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    private function isNoiseLine(string $line): bool
    {
        if (preg_match('/^(Printed:|Powered by|RECEIPT #|TXN DATE|BANKING DATE|REF #|UNIT #|A\/C NO|TENANT|Phone No|PARTICULARS|AMOUNT|RECEIPTED TO|Done by|PASSION SHELTAZ|RENT RECEIPT)/i', $line)) {
            return true;
        }

        return preg_match('/^\d+\s+of\s*$/i', $line) === 1;
    }

    private function readText(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        if (in_array($extension, ['txt', 'text', 'log', 'csv'], true)) {
            $contents = file_get_contents($path);

            return is_string($contents) ? $contents : '';
        }

        return app(PassionLegacyRegisterPdfTextExtractor::class)->extract($path);
    }
}
