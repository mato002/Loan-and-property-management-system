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
        }

        if ($best !== null) {
            return $best;
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

        $full = '#('.$ref.')\s+\d{5,6}\s+(254\d{9})\s+MPESAC2B[_ ]?(\d+)\s+([A-Za-z][A-Za-z .\'-]{1,80}?)\s+('
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

        $short = '#('.$ref.')\s+\d{5,6}\s+(254\d{9})\s*('.$date.')\s+('.$money.')\s*('.$money.')\s*('.$date.')\s+\1#i';
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

        return $rows;
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
        $text = (string) preg_replace('/(\d\.\d{2})(\d{1,3}(?:,\d{3})*\.\d{2})/', '$1 $2', $text);
        $text = (string) preg_replace('/(\d\.\d{2})(\d{2}\/\d{2}\/\d{4})/', '$1 $2', $text);
        $text = (string) preg_replace('/(254\d{9})(\d{2}\/\d{2}\/\d{4})/', '$1 $2', $text);
        $text = (string) preg_replace('/(CHQ\s*No\.?\s*\d+)(\d{2}\/\d{2}\/\d{4})/i', '$1 $2', $text);
        $text = (string) preg_replace('/(Charges)(\d{2}\/\d{2}\/\d{4})/i', '$1 $2', $text);

        return $text;
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
            || preg_match('/MPESAC2B_/i', $text) === 1
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
