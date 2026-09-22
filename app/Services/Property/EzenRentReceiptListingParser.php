<?php

namespace App\Services\Property;

use Illuminate\Support\Str;
use RuntimeException;

final class EzenRentReceiptListingParser
{
    private const BANK_LABELS = [
        'CO-OPERATIVE BANK',
        'CO-OP BANK',
        'CASH ACCOUNT',
        'M-PESA',
        'MPESA',
        'EQUITY BANK',
        'KCB BANK',
        'STANBIC BANK',
        'NCBA BANK',
        'ABSA BANK',
        'DTB BANK',
    ];

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
            $line = $this->cleanLine($rawLine);
            if ($line === '' || $this->isNoiseLine($line)) {
                continue;
            }

            if (preg_match('/^([A-Z0-9]+)\s+~\s+/i', $line, $propertyHeader) === 1) {
                $propertyCode = strtoupper($propertyHeader[1]);
                continue;
            }

            if (preg_match('/^RC(\d+)\s/i', $line) === 1) {
                if ($buffer !== null) {
                    $parsed = $this->tryCompleteBuffer($buffer, $propertyCode);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                    }
                }
                $buffer = ['property_code' => $propertyCode, 'parts' => [$line]];

                if ($this->bufferHasBankFinish($buffer)) {
                    $parsed = $this->tryCompleteBuffer($buffer, $propertyCode);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                        $buffer = null;
                    }
                }

                continue;
            }

            if ($buffer !== null) {
                $buffer['parts'][] = $line;

                if ($this->looksLikeStandaloneFinishLine($line) || $this->bufferHasBankFinish($buffer)) {
                    $parsed = $this->tryCompleteBuffer($buffer, $propertyCode);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                        $buffer = null;
                    }
                }
            }
        }

        if ($buffer !== null) {
            $parsed = $this->tryCompleteBuffer($buffer, $propertyCode);
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
    private function tryCompleteBuffer(array $buffer, string $fallbackPropertyCode): ?array
    {
        $propertyCode = $buffer['property_code'] ?: $fallbackPropertyCode;
        $combined = trim(implode(' ', $buffer['parts']));
        if ($combined === '') {
            return null;
        }

        $peeled = $this->peelFinishFromCombined($combined);
        if ($peeled === null) {
            return null;
        }

        ['head' => $headCombined, 'amount' => $amount, 'receipted_to' => $receiptedTo, 'done_by' => $doneBy] = $peeled;
        if ($amount <= 0 || $this->looksLikePhoneAmount($amount) || $this->looksLikeYearAmount($amount)) {
            return null;
        }

        if (! preg_match('/^RC(\d+)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\/\d{2}\/\d{4})\s+(\S+)\s*(.*)$/i', $headCombined, $head)) {
            return null;
        }

        [$refNo, $middleParts] = $this->splitRefAndMiddle((string) $head[4], trim((string) $head[5]));
        if ($refNo === '') {
            return null;
        }

        $middleText = trim(implode(' ', $middleParts));
        if (! preg_match('/\b(TNT0*\d+)\b/i', $middleText, $tntMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $tntAccount = $this->normalizeTntAccount($tntMatch[1][0]);
        $tntPos = (int) $tntMatch[0][1];
        $unitLabel = trim(substr($middleText, 0, $tntPos));
        $afterTnt = trim(substr($middleText, $tntPos + strlen($tntMatch[1][0])));

        $phone = '';
        $tenantName = $afterTnt;
        $particulars = '';
        if (preg_match('/^(.+?)\s+(0\d{9}|254\d{9})\s*(.*)$/s', $afterTnt, $tenantParts) === 1) {
            $tenantName = trim((string) $tenantParts[1]);
            $phone = trim((string) $tenantParts[2]);
            $particulars = trim((string) $tenantParts[3]);
        } elseif (preg_match('/^(0\d{9}|254\d{9})\s*(.*)$/s', $afterTnt, $tenantParts) === 1) {
            $phone = trim((string) $tenantParts[1]);
            $particulars = trim((string) $tenantParts[2]);
            $tenantName = '';
        }

        // Strip a trailing lone "0" / phone fragment that PDF extraction sometimes leaves on the name.
        $tenantName = trim(preg_replace('/\s+0$/', '', $tenantName) ?? $tenantName);

        if ($tenantName === '') {
            return null;
        }

        return [
            'ezen_receipt_no' => 'RC'.strtoupper($head[1]),
            'property_code' => strtoupper(trim($propertyCode)),
            'txn_date' => $this->parseDate((string) $head[2]),
            'banking_date' => $this->parseDate((string) $head[3]),
            'ref_no' => strtoupper($refNo),
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

    /**
     * @return array{head:string, amount:float, receipted_to:string, done_by:string}|null
     */
    private function peelFinishFromCombined(string $combined): ?array
    {
        foreach (self::BANK_LABELS as $bank) {
            $lastPos = null;
            $search = 0;
            $bankLen = strlen($bank);
            while (($pos = stripos($combined, $bank, $search)) !== false) {
                // Require a digit/amount-ish token before the bank label.
                $beforeProbe = rtrim(substr($combined, 0, $pos));
                if ($beforeProbe !== '' && preg_match('/[\d,]+(?:\.\d+)?$/u', $beforeProbe) === 1) {
                    $lastPos = $pos;
                }
                $search = $pos + max(1, $bankLen);
            }
            if ($lastPos === null) {
                continue;
            }

            $before = trim(substr($combined, 0, $lastPos));
            $after = trim(substr($combined, $lastPos + strlen($bank)));
            if ($before === '' || $after === '') {
                continue;
            }

            if (preg_match_all('/\b([\d,]+(?:\.\d+)?)\b/', $before, $moneyMatches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }

            $chosen = null;
            for ($i = count($moneyMatches[1]) - 1; $i >= 0; $i--) {
                $token = (string) $moneyMatches[1][$i][0];
                $amount = $this->money($token);
                if ($amount <= 0 || $this->looksLikePhoneAmount($amount)) {
                    continue;
                }
                // Prefer the money token closest to the bank label (rightmost).
                $tokenPos = (int) $moneyMatches[1][$i][1];
                $tokenEnd = $tokenPos + strlen($token);
                $between = trim(substr($before, $tokenEnd));
                // Allow only light leftovers between amount and bank (none expected).
                if ($between !== '') {
                    continue;
                }
                $chosen = [
                    'head' => trim(substr($before, 0, $tokenPos)),
                    'amount' => $amount,
                ];
                break;
            }

            if ($chosen === null) {
                continue;
            }

            return [
                'head' => $chosen['head'],
                'amount' => $chosen['amount'],
                'receipted_to' => $bank,
                'done_by' => $after,
            ];
        }

        if (preg_match('/^(.+)\s+([\d,]+(?:\.\d+)?)\s+(.+?)\s+([A-Z0-9][A-Z0-9\s.\'-]+)$/i', $combined, $match) === 1) {
            $amount = $this->money((string) $match[2]);
            $receiptedTo = trim((string) $match[3]);
            if ($this->looksLikePhoneAmount($amount) || $this->looksLikeYearAmount($amount)) {
                return null;
            }
            // Weak fallback — only accept when receipted_to looks like a bank/ledger, not particulars text.
            if (! $this->looksLikeReceiptedToLabel($receiptedTo)) {
                return null;
            }

            return [
                'head' => trim((string) $match[1]),
                'amount' => $amount,
                'receipted_to' => $receiptedTo,
                'done_by' => trim((string) $match[4]),
            ];
        }

        return null;
    }

    private function looksLikeYearAmount(float $amount): bool
    {
        $asInt = (int) round($amount);

        return $asInt >= 2000 && $asInt <= 2099 && fmod($amount, 1.0) === 0.0;
    }

    private function looksLikeReceiptedToLabel(string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }

        // Particulars text must never land in receipted_to (e.g. "MRS … DEPOSIT, Late payment…").
        if (preg_match('/\b(DEPOSIT|LATE\s+PAYMENT|CHARGE|RENT\s+FOR|GARBAGE|WATER|METER)\b/i', $label) === 1) {
            return false;
        }
        if (str_contains($label, ',') || strlen($label) > 80) {
            return false;
        }

        foreach (self::BANK_LABELS as $bank) {
            if (strcasecmp($label, $bank) === 0) {
                return true;
            }
        }

        // Landlord / cash ledger style labels (e.g. "MR & MRS. JOSEPH THUO", "CASH ACCOUNT").
        if (preg_match('/\b(BANK|CASH|MPESA|M-PESA|ACCOUNT|LEDGER|MAKAO)\b/i', $label) === 1) {
            return true;
        }

        return strlen($label) >= 12
            && strlen($label) <= 64
            && preg_match('/\b(MR|MRS|MISS|DR)\b/i', $label) === 1
            && preg_match('/\d{4}/', $label) !== 1;
    }

    private function looksLikePhoneAmount(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $asInt = (string) (int) round($amount);
        if (strlen($asInt) >= 9 && strlen($asInt) <= 10 && fmod($amount, 1.0) === 0.0) {
            return true;
        }

        return $amount > 500000;
    }

    /**
     * @param  array{property_code:string, parts:list<string>}  $buffer
     */
    private function bufferHasBankFinish(array $buffer): bool
    {
        $combined = trim(implode(' ', $buffer['parts']));
        if ($combined === '') {
            return false;
        }

        foreach (self::BANK_LABELS as $bank) {
            if (preg_match('/\s+[\d,]+(?:\.\d+)?\s+'.preg_quote($bank, '/').'\s+.+$/i', $combined) === 1) {
                $peeled = $this->peelFinishFromCombined($combined);

                return $peeled !== null && ! $this->looksLikePhoneAmount((float) $peeled['amount']);
            }
        }

        if (preg_match('/\s+[\d,]+(?:\.\d+)?\s+[A-Z0-9][A-Z0-9\s.\'-]+\s+[A-Z0-9][A-Z0-9\s.\'-]+$/i', $combined)) {
            return true;
        }

        return false;
    }

    private function looksLikeStandaloneFinishLine(string $line): bool
    {
        if (preg_match('/^RC\d+\s/i', $line)) {
            return false;
        }

        // Only treat as a finish line when a known bank / cash ledger label is present.
        // Otherwise meter lines like "11.00 units, Late payment..." falsely close the buffer.
        foreach (self::BANK_LABELS as $bank) {
            if (preg_match('/^([\d,]+(?:\.\d+)?)\s+'.preg_quote($bank, '/').'\s+(.+)$/i', $line, $m) === 1) {
                return ! $this->looksLikePhoneAmount($this->money((string) $m[1]));
            }
        }

        return false;
    }

    /**
     * @return array{0:string, 1:list<string>}
     */
    private function splitRefAndMiddle(string $refToken, string $rest): array
    {
        $refToken = trim($refToken);
        $rest = trim($rest);

        if (preg_match('/^([A-Z0-9]+)(HSE\b.*)$/i', $refToken, $glued) === 1) {
            return [strtoupper($glued[1]), array_values(array_filter([trim($glued[2]), $rest]))];
        }

        if (preg_match('/^Cash$/i', $refToken)) {
            return ['CASH', $rest !== '' ? [$rest] : []];
        }

        return [strtoupper($refToken), $rest !== '' ? [$rest] : []];
    }

    private function cleanLine(string $rawLine): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $rawLine) ?? '');
        $line = trim((string) preg_replace('/Printed:.*$/i', '', $line));
        $line = trim((string) preg_replace('/Powered by EZEN.*$/i', '', $line));
        $line = trim((string) preg_replace('/Sep\s+\d{1,2},\s+\d{4}(?:,\s+\d{1,2}:\d{2}\s+[AP]M)?/i', '', $line));

        return trim($line);
    }

    public function normalizeTntAccount(string $account): string
    {
        $account = strtoupper(trim($account));
        if (preg_match('/^TNT0*(\d+)$/', $account, $match) !== 1) {
            return $account;
        }

        return 'TNT'.str_pad((string) ((int) $match[1]), 6, '0', STR_PAD_LEFT);
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
        if (preg_match('/^(Printed:|Powered by|Invalid date|RECEIPT #|TXN DATE|BANKING DATE|REF #|UNIT #|A\/C NO|TENANT|Phone No|PARTICULARS|AMOUNT|RECEIPTED TO|Done by|PASSION SHELTAZ|RENT RECEIPT)/i', $line)) {
            return true;
        }

        if (preg_match('/^\d+\s+of\s*$/i', $line)) {
            return true;
        }

        if (preg_match('/^\d{4}$/', $line)) {
            return true;
        }

        return false;
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
