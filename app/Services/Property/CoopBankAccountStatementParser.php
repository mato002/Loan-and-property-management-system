<?php

namespace App\Services\Property;

use Illuminate\Support\Str;
use RuntimeException;

final class CoopBankAccountStatementParser
{
    public const TYPE_MPESA = 'mpesa_c2b';

    public const TYPE_CHEQUE = 'cheque';

    public const TYPE_CHARGE = 'bank_charge';

    private const MONEY = '(?:\d{1,3}(?:,\d{3})+|\d+)\.\d{2}';

    private const DATE = '\d{2}/\d{2}/\d{4}';

    private const REF = '[A-Z0-9]{8,14}';

    /**
     * @return array{
     *     account_no:string,
     *     account_name:string,
     *     currency:string,
     *     period_from:?string,
     *     period_to:?string,
     *     opening_balance:float,
     *     closing_balance:float,
     *     total_debit:float,
     *     total_credit:float,
     *     lines:list<array<string, mixed>>
     * }
     */
    public function parsePath(string $path): array
    {
        $texts = $this->readTexts($path);
        if ($texts === []) {
            throw new RuntimeException('File not found, empty, or unreadable: '.$path);
        }

        $best = null;
        $bestCount = -1;
        $sample = $texts[0];
        $headerOnly = null;

        foreach ($texts as $text) {
            $sample = $text;
            if (! $this->looksLikeBankStatement($text)) {
                continue;
            }
            $parsed = $this->parseText($text, $path);
            $count = count($parsed['lines'] ?? []);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $parsed;
            }
            if ($count === 0 && $headerOnly === null) {
                $headerOnly = $parsed;
            }
        }

        if ($best !== null && $bestCount > 0) {
            return $best;
        }

        if ($headerOnly !== null) {
            $account = trim((string) ($headerOnly['account_no'] ?? ''));
            $period = trim(implode(' to ', array_filter([
                $headerOnly['period_from'] ?? null,
                $headerOnly['period_to'] ?? null,
            ])));
            $hint = $account !== '' ? ' Account '.$account.'.' : '';
            if ($period !== '') {
                $hint .= ' Period '.$period.'.';
            }

            throw new RuntimeException(
                'This looks like a Co-operative Bank statement, but no payments could be read from the PDF text.'.$hint
                .' Export or Save as TXT from the bank portal and upload that file.'
            );
        }

        throw new RuntimeException($this->unrecognizedStatementMessage($sample));
    }

    /**
     * @return array<string, mixed>
     */
    public function parseText(string $text, string $path = ''): array
    {
        $normalized = $this->collapse($text);
        $header = $this->parseHeader($normalized);
        $lines = array_merge(
            $this->parseMpesaCredits($normalized),
            $this->parseChequeDebits($normalized),
            $this->parseBankCharges($normalized),
        );

        $header['source_filename'] = $path !== '' ? basename($path) : '';
        $header['lines'] = $this->uniqueBySourceKey($lines);

        return $header;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHeader(string $normalized): array
    {
        $accountNo = '';
        if (preg_match('/Account No\s+(\d{10,16})/i', $normalized, $match) === 1) {
            $accountNo = $match[1];
        }

        $from = null;
        $to = null;
        if (preg_match('#('.self::DATE.')\s+to\s+('.self::DATE.')#i', $normalized, $match) === 1) {
            $from = $this->toIsoDate($match[1]);
            $to = $this->toIsoDate($match[2]);
        }

        $opening = 0.0;
        $closing = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $totals = '/Opening Balance\s+Total Debit\s+Closing Balance\s+Total Credit\s+('
            .self::MONEY.')\s+('.self::MONEY.')\s+('.self::MONEY.')\s+('.self::MONEY.')/i';
        if (preg_match($totals, $normalized, $match) === 1) {
            $opening = $this->money($match[1]);
            $totalDebit = $this->money($match[2]);
            $closing = $this->money($match[3]);
            $totalCredit = $this->money($match[4]);
        }

        $accountName = 'PASSION SHELTAZ';
        if (preg_match('/STATEMENT OF ACCOUNT\s+([A-Z][A-Z ]{3,40})/i', $normalized, $match) === 1) {
            $accountName = trim($match[1]);
        }

        $currency = 'KES';
        if (preg_match('/Currency\s+([A-Z]{3})/i', $normalized, $match) === 1) {
            $currency = strtoupper($match[1]);
        }

        return [
            'bank_name' => 'Co-operative Bank',
            'account_no' => $accountNo,
            'account_name' => $accountName,
            'currency' => $currency,
            'period_from' => $from,
            'period_to' => $to,
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseMpesaCredits(string $normalized): array
    {
        $rows = [];
        $money = self::MONEY;
        $date = self::DATE;
        $ref = self::REF;

        $full = '#('.$ref.')\s+\d{4,10}\s+(254\d{9})\s+MPESAC2B[_ ]?(\d+)\s+([A-Za-z][A-Za-z .\'-]{1,80}?)\s+('
            .$date.')\s+('.$money.')\s*('.$money.')\s*('.$date.')\s+\1#i';
        if (preg_match_all($full, $normalized, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $rows[] = $this->line(
                    self::TYPE_MPESA,
                    'credit',
                    $match[1],
                    $this->toIsoDate($match[5]),
                    $this->money($match[7]),
                    $this->money($match[6]),
                    trim($match[4]),
                    $match[2],
                    'M-Pesa C2B '.$match[3],
                );
            }
        }

        $seen = array_map(fn (array $row): string => (string) $row['reference'], $rows);

        $namedWithoutPhone = '#('.$ref.')\s+\d{4,10}\s+MPESAC2B[_ ]?(\d+)\s+([A-Za-z][A-Za-z .\'-]{1,80}?)\s+('
            .$date.')\s+('.$money.')\s*('.$money.')\s*('.$date.')\s+\1#i';
        if (preg_match_all($namedWithoutPhone, $normalized, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $reference = strtoupper($match[1]);
                if (in_array($reference, $seen, true)) {
                    continue;
                }
                $rows[] = $this->line(
                    self::TYPE_MPESA,
                    'credit',
                    $reference,
                    $this->toIsoDate($match[4]),
                    $this->money($match[6]),
                    $this->money($match[5]),
                    trim($match[3]),
                    null,
                    'M-Pesa C2B '.$match[2],
                );
                $seen[] = $reference;
            }
        }

        $short = '#('.$ref.')\s+\d{4,10}\s+(254\d{9})\s*('.$date.')\s+('.$money.')\s*('.$money.')\s*('.$date.')\s+\1#i';
        if (preg_match_all($short, $normalized, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $reference = strtoupper($match[1]);
                if (in_array($reference, $seen, true)) {
                    continue;
                }
                $credit = $this->money($match[5]);
                if ($credit <= 0 || $credit > 500000) {
                    continue;
                }
                $rows[] = $this->line(
                    self::TYPE_MPESA,
                    'credit',
                    $reference,
                    $this->toIsoDate($match[3]),
                    $credit,
                    $this->money($match[4]),
                    '',
                    $match[2],
                    'M-Pesa C2B',
                );
                $seen[] = $reference;
            }
        }

        $orphan = '#('.$date.')\s+('.$money.')\s*('.$money.')\s*('.$date.')\s+('.$ref.')#';
        if (preg_match_all($orphan, $normalized, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $reference = strtoupper($match[5]);
                if (in_array($reference, $seen, true) || str_starts_with($reference, 'SYBL')) {
                    continue;
                }
                $credit = $this->money($match[3]);
                if ($credit <= 0 || $credit > 500000) {
                    continue;
                }
                $rows[] = $this->line(
                    self::TYPE_MPESA,
                    'credit',
                    $reference,
                    $this->toIsoDate($match[1]),
                    $credit,
                    $this->money($match[2]),
                    '',
                    null,
                    'M-Pesa C2B',
                );
                $seen[] = $reference;
            }
        }

        return $this->fillPayerFromDetails($normalized, $rows);
    }

    /**
     * The PDF text sometimes drops the phone, or splits the payer name onto the next page.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function fillPayerFromDetails(string $normalized, array $rows): array
    {
        $index = [];
        foreach ($rows as $i => $row) {
            if (($row['line_type'] ?? '') === self::TYPE_MPESA) {
                $index[strtoupper((string) $row['reference'])] = $i;
            }
        }

        $nameStop = '(?=\s+(?:Page\b|\d{2}/\d{2}/\d{4}|[A-Z0-9]{8,14}\b))';
        $patterns = [
            '#([A-Z0-9]{8,14})\s+\d{4,10}\s+(254\d{9})\s+MPESAC2B[_ ]?\d+\s+([A-Za-z][A-Za-z .\'-]{1,60}?)'.$nameStop.'#i',
            '#([A-Z0-9]{8,14})\s+\d{4,10}\s+(254\d{9})(?=\s+MPESAC2B|\s+\d{2}/|\s+[A-Z0-9]{8,14}\b)#i',
            '#([A-Z0-9]{8,14})\s+\d{4,10}\s+MPESAC2B[_ ]?\d+\s+([A-Za-z][A-Za-z .\'-]{1,60}?)'.$nameStop.'#i',
            '#([A-Z0-9]{8,14})\s+(?:(?!MPESAC2B|[A-Z0-9]{8,14}\s+\d{4,10}).){0,280}?MPESAC2B[_ ]?\d+\s+([A-Za-z][A-Za-z .\'-]{1,60}?)'.$nameStop.'#i',
            '#([A-Z0-9]{8,14})\s+(?:(?!MPESAC2B|[A-Z0-9]{8,14}\s+\d{4,10}).){0,280}?\b(254\d{9})\b#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($matches as $match) {
                $ref = strtoupper($match[1]);
                if (! isset($index[$ref])) {
                    continue;
                }
                $i = $index[$ref];
                $phone = null;
                $name = trim((string) ($match[2] ?? ''));
                if (isset($match[3]) && preg_match('/^254\d{9}$/', (string) $match[2]) === 1) {
                    $phone = $match[2];
                    $name = trim((string) $match[3]);
                } elseif (preg_match('/^254\d{9}$/', $name) === 1) {
                    $phone = $name;
                    $name = '';
                }
                if ($phone && empty($rows[$i]['phone'])) {
                    $rows[$i]['phone'] = $phone;
                }
                if ($name !== '' && empty($rows[$i]['counterparty']) && ! $this->isNoisePayerName($name)) {
                    $rows[$i]['counterparty'] = $name;
                }
            }
        }

        foreach ($index as $ref => $i) {
            if (! empty($rows[$i]['phone']) && ! empty($rows[$i]['counterparty'])) {
                continue;
            }
            if (preg_match('/\b'.preg_quote($ref, '/').'\b/i', $normalized, $hit, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $start = (int) $hit[0][1];
            $rest = substr($normalized, $start + strlen($ref), 320);
            if (preg_match('/\b([A-Z0-9]{8,14})\s+\d{4,10}\b/i', $rest, $next, PREG_OFFSET_CAPTURE) === 1
                && strtoupper((string) $next[1][0]) !== $ref
            ) {
                $rest = substr($rest, 0, (int) $next[0][1]);
            }
            $chunk = $ref.$rest;
            if (empty($rows[$i]['phone']) && preg_match('/\b(254\d{9})\b/', $chunk, $phoneMatch) === 1) {
                $rows[$i]['phone'] = $phoneMatch[1];
            }
            if (empty($rows[$i]['counterparty'])
                && preg_match('/MPESAC2B[_ ]?\d+\s+([A-Za-z][A-Za-z .\'-]{1,60}?)(?=\s+(?:Page\b|\d{2}\/|[A-Z0-9]{8,14}\b))/i', $chunk, $nameMatch) === 1
            ) {
                $name = trim((string) $nameMatch[1]);
                if (! $this->isNoisePayerName($name)) {
                    $rows[$i]['counterparty'] = $name;
                }
            }
        }

        return $rows;
    }

    private function isNoisePayerName(string $name): bool
    {
        if (preg_match('/\b(page|transaction|debit|credit|balance|value|date|reference|number|details|opening)\b/i', $name) === 1) {
            return true;
        }

        return preg_match('/^[A-Za-z][A-Za-z .\'-]{1,60}$/', $name) !== 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseChequeDebits(string $normalized): array
    {
        $rows = [];
        $pattern = '#CHQ\s*No\.?\s*0*(\d+)\s*('.self::DATE.')\s+('.self::MONEY.')\s+('.self::MONEY.')\s*('.self::DATE.')\s+([A-Z0-9]+)#i';
        if (preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER) === false) {
            return $rows;
        }

        foreach ($matches as $match) {
            $number = str_pad($match[1], 6, '0', STR_PAD_LEFT);
            $rows[] = $this->line(
                self::TYPE_CHEQUE,
                'debit',
                'CHQ-'.$number,
                $this->toIsoDate($match[2]),
                $this->money($match[3]),
                $this->money($match[4]),
                'Cheque '.$number,
                null,
                'CHQ '.$number.' '.$match[6],
            );
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseBankCharges(string $normalized): array
    {
        $rows = [];
        $pattern = '#Account Ledger Fee:\s*Charges\s*('.self::DATE.')\s+('.self::MONEY.')\s+('.self::MONEY.')#i';
        if (preg_match($pattern, $normalized, $match) === 1) {
            $rows[] = $this->line(
                self::TYPE_CHARGE,
                'debit',
                'LEDGER-FEE-'.$this->toIsoDate($match[1]),
                $this->toIsoDate($match[1]),
                $this->money($match[2]),
                $this->money($match[3]),
                'Account ledger fee',
                null,
                'Bank charges',
            );
        }

        $excise = '#EXCISE Charges\s*('.self::DATE.')\s+('.self::MONEY.')\s+('.self::MONEY.')#i';
        if (preg_match($excise, $normalized, $match) === 1) {
            $rows[] = $this->line(
                self::TYPE_CHARGE,
                'debit',
                'EXCISE-'.$this->toIsoDate($match[1]),
                $this->toIsoDate($match[1]),
                $this->money($match[2]),
                $this->money($match[3]),
                'Excise charges',
                null,
                'Bank charges',
            );
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        string $type,
        string $direction,
        string $reference,
        string $txnDate,
        float $amount,
        float $runningBalance,
        string $counterparty,
        ?string $phone,
        string $narration,
    ): array {
        $reference = strtoupper(trim($reference));

        return [
            'source_key' => sha1(implode('|', [$type, $reference, $txnDate, number_format($amount, 2, '.', '')])),
            'line_type' => $type,
            'direction' => $direction,
            'reference' => $reference,
            'txn_date' => $txnDate,
            'amount' => $amount,
            'running_balance' => $runningBalance,
            'counterparty' => trim($counterparty),
            'phone' => $phone,
            'narration' => $narration,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueBySourceKey(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = (string) ($row['source_key'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    private function collapse(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        $text = trim((string) preg_replace('/[ \n]+/', ' ', $text));
        // Per-glyph PDF extracts leave "U I 1 0 X 4 M P L S" and "M P E S A C 2 B".
        $text = $this->joinSpacedGlyphs($text);
        // After joining, phone digits can sit against MPESA / store number.
        $text = (string) preg_replace(
            '/\b([A-Z][A-Z0-9]{7,13})(\d{4,10})(254\d{9})(MPESA)/i',
            '$1 $2 $3 $4',
            $text
        );
        $text = (string) preg_replace('/(254\d{9})(MPESA)/i', '$1 $2', $text);
        $text = (string) preg_replace('/(\d{4,10})(254\d{9})/', '$1 $2', $text);
        $text = (string) preg_replace('/(\d\.\d{2})(\d{1,3}(?:,\d{3})*\.\d{2})/', '$1 $2', $text);
        $text = (string) preg_replace('/(\d\.\d{2})(\d{2}\/\d{2}\/\d{4})/', '$1 $2', $text);
        $text = (string) preg_replace('/(254\d{9})(\d{2}\/\d{2}\/\d{4})/', '$1 $2', $text);
        $text = (string) preg_replace('/(CHQ\s*No\.?\s*\d+)(\d{2}\/\d{2}\/\d{4})/i', '$1 $2', $text);
        $text = (string) preg_replace('/(Charges)(\d{2}\/\d{2}\/\d{4})/i', '$1 $2', $text);
        $text = (string) preg_replace('/MPESA\s*C\s*2\s*B/i', 'MPESAC2B', $text);
        $text = (string) preg_replace('/MPESAC2B[\s_-]+/', 'MPESAC2B_', $text);

        return $text;
    }

    private function joinSpacedGlyphs(string $text): string
    {
        $joined = (string) preg_replace_callback(
            '/(?:(?<=^)|(?<=\s))(?:[A-Za-z0-9]\s+){4,}[A-Za-z0-9](?=\s|$)/',
            static fn (array $match): string => str_replace(' ', '', $match[0]),
            $text
        );
        // Dates like "0 1 / 0 9 / 2 0 2 6"
        $joined = (string) preg_replace(
            '/(\d)\s+(\d)\s*\/\s*(\d)\s+(\d)\s*\/\s*(\d)\s+(\d)\s+(\d)\s+(\d)/',
            '$1$2/$3$4/$5$6$7$8',
            $joined
        );

        return $joined;
    }

    private function toIsoDate(string $value): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($value), $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }

        return trim($value);
    }

    private function money(string $value): float
    {
        $raw = str_replace([',', ' '], '', $value);

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    /**
     * @return list<string>
     */
    private function readTexts(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        if (in_array($extension, ['txt', 'text', 'log', 'csv', 'tsv'], true)) {
            $contents = file_get_contents($path);

            return is_string($contents) && trim($contents) !== '' ? [$contents] : [];
        }

        return app(PassionLegacyRegisterPdfTextExtractor::class)->extractCandidates($path);
    }

    private function readText(string $path): string
    {
        $texts = $this->readTexts($path);
        if ($texts === []) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        return $texts[0];
    }

    private function looksLikeBankStatement(string $text): bool
    {
        return preg_match('/STATEMENT OF ACCOUNT/i', $text) === 1
            || preg_match('/MPESA[\s_-]*C2B/i', $text) === 1
            || preg_match('/Account No\s+\d{10,16}/i', $text) === 1
            || preg_match('/Receipt No/i', $text) === 1;
    }

    private function unrecognizedStatementMessage(string $text): string
    {
        $blob = strtolower((string) preg_replace('/\s+/', ' ', $text));

        if (str_contains($blob, 'landlord name')
            || str_contains($blob, 'owner share')
            || str_contains($blob, 'agent earning')
            || str_contains($blob, 'report generated')) {
            return 'This PDF is a report from this system, not a bank statement. Upload the Co-operative Bank Statement of Account (AccountStatement….pdf) or a Safaricom C2B CSV.';
        }

        if (str_contains($blob, 'payment voucher')) {
            return 'This PDF is a payment voucher listing, not a bank statement. Upload the Co-operative Bank Statement of Account PDF instead.';
        }

        return 'This file is not a Co-operative Bank Statement of Account or M-Pesa C2B export. Upload the bank PDF, or save it as .txt first.';
    }
}
