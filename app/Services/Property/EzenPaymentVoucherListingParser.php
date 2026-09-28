<?php

namespace App\Services\Property;

use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

final class EzenPaymentVoucherListingParser
{
    public const CATEGORY_REMITTANCE = 'remittance';

    public const CATEGORY_COMMISSION = 'commission';

    public const CATEGORY_TAX = 'tax';

    public const CATEGORY_EXPENSE = 'expense';

    private const METHODS = [
        'Bank Deposit',
        'Bank Transfer',
        'Check-off',
        'PDQ/POS',
        'M-Pesa',
        'Mpesa',
        'Cheque',
        'Check',
        'EFT',
        'RTGS',
        'Cash',
    ];

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
        'FAMILY BANK',
        'COOPERATIVE BANK',
    ];

    /**
     * @return list<array{
     *     ezen_voucher_no:string,
     *     method:string,
     *     ref_no:string,
     *     txn_date:string,
     *     particulars:string,
     *     paid_from:string,
     *     paid_to:string,
     *     payee_name:string,
     *     property_code:string,
     *     amount:float,
     *     recorded_by:string,
     *     category:string,
     *     period_month:?string
     * }>
     */
    public function parsePath(string $path): array
    {
        return $this->parseText($this->readText($path), $path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseText(string $text, string $path = ''): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        if (in_array($extension, ['csv', 'tsv'], true) || $this->looksLikeCsv($text)) {
            $csvRows = $this->parseCsv($text);
            if ($csvRows !== []) {
                return $csvRows;
            }
        }

        return $this->parseLayout($text);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseCsv(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $rows = [];
        $header = null;
        $map = [];

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line);
            if ($this->emptyCols($cols)) {
                continue;
            }

            if ($header === null) {
                $normalized = array_map(fn ($name) => $this->normalizeHeader((string) $name), $cols);
                if (! $this->isHeaderRow($normalized)) {
                    continue;
                }
                $header = $normalized;
                foreach ($header as $i => $name) {
                    $canonical = $this->canonicalHeader($name);
                    if ($canonical !== '') {
                        $map[$canonical] = (int) $i;
                    }
                }
                continue;
            }

            $get = function (string $key) use ($cols, $map): string {
                $index = $map[$key] ?? null;

                return $index === null ? '' : trim((string) ($cols[$index] ?? ''));
            };

            $voucher = $this->normalizeVoucherNo($get('ezen_voucher_no'));
            $amount = $this->money($get('amount'));
            if ($voucher === '' || $amount <= 0) {
                continue;
            }

            try {
                $txnDate = $this->parseDate($get('txn_date'));
            } catch (RuntimeException) {
                continue;
            }

            $paidTo = trim($get('paid_to'));
            $parsedPayee = $this->splitPayee($paidTo);
            $particulars = trim($get('particulars'));

            $rows[] = $this->finalizeRow([
                'ezen_voucher_no' => $voucher,
                'method' => $this->normalizeMethod($get('method')),
                'ref_no' => $this->normalizeRef($get('ref_no')),
                'txn_date' => $txnDate,
                'particulars' => $particulars,
                'paid_from' => $this->normalizeBank($get('paid_from')),
                'paid_to' => $paidTo,
                'payee_name' => $parsedPayee['name'],
                'property_code' => $parsedPayee['code'],
                'amount' => $amount,
                'recorded_by' => trim($get('recorded_by')),
            ]);
        }

        return $this->uniqueByVoucher($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseLayout(string $text): array
    {
        $rows = [];
        $buffer = null;

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $rawLine) {
            $line = $this->cleanLine($rawLine);
            if ($line === '' || $this->isNoiseLine($line)) {
                continue;
            }

            if (preg_match('/^PM\d+\b/i', $line) === 1) {
                if ($buffer !== null) {
                    $parsed = $this->parseLayoutCombined($buffer);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                    }
                }
                $buffer = $line;

                $parsed = $this->parseLayoutCombined($buffer);
                if ($parsed !== null) {
                    $rows[] = $parsed;
                    $buffer = null;
                }

                continue;
            }

            if ($buffer !== null) {
                $buffer = trim($buffer.' '.$line);
                $parsed = $this->parseLayoutCombined($buffer);
                if ($parsed !== null) {
                    $rows[] = $parsed;
                    $buffer = null;
                }
            }
        }

        if ($buffer !== null) {
            $parsed = $this->parseLayoutCombined($buffer);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return $this->uniqueByVoucher($rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function finalizeRow(array $row): array
    {
        $particulars = trim((string) ($row['particulars'] ?? ''));
        $txnDate = (string) ($row['txn_date'] ?? '');
        $category = $this->classify($particulars);
        $propertyCode = strtoupper(trim((string) ($row['property_code'] ?? '')));
        if ($propertyCode === '' && preg_match('/\[([A-Z]\d{5}[A-Z])\]/i', $particulars, $codeMatch) === 1) {
            $propertyCode = strtoupper((string) $codeMatch[1]);
        }

        return [
            'ezen_voucher_no' => (string) $row['ezen_voucher_no'],
            'method' => (string) ($row['method'] ?? ''),
            'ref_no' => (string) ($row['ref_no'] ?? ''),
            'txn_date' => $txnDate,
            'particulars' => $particulars,
            'paid_from' => (string) ($row['paid_from'] ?? ''),
            'paid_to' => (string) ($row['paid_to'] ?? ''),
            'payee_name' => (string) ($row['payee_name'] ?? ''),
            'property_code' => $propertyCode,
            'amount' => (float) ($row['amount'] ?? 0),
            'recorded_by' => (string) ($row['recorded_by'] ?? ''),
            'category' => $category,
            'period_month' => $this->extractPeriodMonth($particulars, $txnDate),
        ];
    }

    public function classify(string $particulars): string
    {
        $text = strtoupper($particulars);
        if (preg_match('/REMITTANCE|RENT\s+REMIT|RENTAL\s+REMIT|LANDLORD\s+PAY|OWNER\s+PAYOUT/', $text) === 1) {
            return self::CATEGORY_REMITTANCE;
        }
        if (preg_match('/\bCOMMISSION\b/', $text) === 1) {
            return self::CATEGORY_COMMISSION;
        }
        if (preg_match('/\bKRA\b|\bMRI\b|WITHHOLDING|\bPAYE\b|\bNSSF\b|\bNHIF\b|\bSHIF\b|VAT\s+PAY|\bTAX\b/', $text) === 1) {
            return self::CATEGORY_TAX;
        }

        return self::CATEGORY_EXPENSE;
    }

    /**
     * @return array{code:string, name:string}
     */
    public function splitPayee(string $paidTo): array
    {
        $paidTo = trim($paidTo);
        if (preg_match('/^\[([A-Z0-9]+)\]\s*(.*)$/i', $paidTo, $match) === 1) {
            return [
                'code' => strtoupper(trim((string) $match[1])),
                'name' => trim((string) $match[2]),
            ];
        }

        if (preg_match('/^([A-Z]\d{5}[A-Z])(?:\s*[-–]\s*|\s+)(.*)$/i', $paidTo, $match) === 1) {
            return [
                'code' => strtoupper(trim((string) $match[1])),
                'name' => trim((string) $match[2]),
            ];
        }

        return ['code' => '', 'name' => $paidTo];
    }

    private function parseLayoutCombined(string $combined): ?array
    {
        $methodPattern = implode('|', array_map(static fn (string $method) => preg_quote($method, '/'), self::METHODS));
        if (preg_match('/^PM(\d+)\s+('.$methodPattern.')\s+(.+?)(?:\s+)?(\d{2}\/\d{2}\/\d{4})\s+(.+)$/i', $combined, $head) !== 1) {
            return null;
        }

        $afterDate = trim((string) $head[5]);
        $paidFrom = '';
        $particulars = $afterDate;
        $afterBank = '';

        foreach (self::BANK_LABELS as $bank) {
            $pattern = '/^(.*?)\s+('.preg_quote($bank, '/').')\s+(.+)$/i';
            if (preg_match($pattern, $afterDate, $bankMatch) !== 1) {
                continue;
            }

            $candidateAfter = trim((string) $bankMatch[3]);
            if (preg_match('/^(.+)\s+((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?)\s+(.+)$/', $candidateAfter) !== 1) {
                continue;
            }

            $particulars = trim((string) $bankMatch[1]);
            $paidFrom = $this->normalizeBank((string) $bankMatch[2]);
            $afterBank = $candidateAfter;
            break;
        }

        if ($paidFrom === '' || $afterBank === '') {
            return null;
        }

        if (preg_match('/^(.+)\s+((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?)\s+(.+)$/', $afterBank, $tail) !== 1) {
            return null;
        }

        $paidTo = trim((string) $tail[1]);
        $amount = $this->money((string) $tail[2]);
        $recordedBy = trim((string) $tail[3]);
        if ($amount <= 0 || $paidTo === '' || $recordedBy === '') {
            return null;
        }

        $parsedPayee = $this->splitPayee($paidTo);

        return $this->finalizeRow([
            'ezen_voucher_no' => $this->normalizeVoucherNo('PM'.$head[1]),
            'method' => $this->normalizeMethod((string) $head[2]),
            'ref_no' => $this->normalizeRef((string) $head[3]),
            'txn_date' => $this->parseDate((string) $head[4]),
            'particulars' => $particulars,
            'paid_from' => $paidFrom,
            'paid_to' => $paidTo,
            'payee_name' => $parsedPayee['name'],
            'property_code' => $parsedPayee['code'],
            'amount' => $amount,
            'recorded_by' => $recordedBy,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueByVoucher(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = (string) ($row['ezen_voucher_no'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    private function looksLikeCsv(string $text): bool
    {
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            return substr_count($line, ',') >= 4
                && preg_match('/voucher|paid\s*to|particulars/i', $line) === 1;
        }

        return false;
    }

    /**
     * @param  list<string>  $normalized
     */
    private function isHeaderRow(array $normalized): bool
    {
        $joined = implode(' ', $normalized);

        return str_contains($joined, 'voucher') && (
            str_contains($joined, 'amount')
            || str_contains($joined, 'paid_to')
            || str_contains($joined, 'particulars')
        );
    }

    private function normalizeHeader(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace(['#', '.', '/'], '', $name);
        $name = (string) preg_replace('/[\s-]+/', '_', $name);

        return trim($name, " \t_");
    }

    private function canonicalHeader(string $normalized): string
    {
        return match ($normalized) {
            'voucher', 'voucher_no', 'voucher_number', 'pm_no', 'pm', 'ezen_voucher_no' => 'ezen_voucher_no',
            'method', 'payment_method', 'pay_method' => 'method',
            'ref', 'ref_no', 'reference', 'reference_no', 'refno' => 'ref_no',
            'date', 'txn_date', 'payment_date', 'voucher_date' => 'txn_date',
            'particulars', 'description', 'narration', 'details' => 'particulars',
            'paid_from', 'from', 'account', 'source' => 'paid_from',
            'paid_to', 'to', 'payee', 'beneficiary', 'supplier' => 'paid_to',
            'amount', 'amt', 'value' => 'amount',
            'recorded_by', 'done_by', 'user', 'entered_by' => 'recorded_by',
            default => '',
        };
    }

    public function normalizeVoucherNo(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^PM0*(\d+)$/', $value, $match) === 1) {
            return 'PM'.str_pad((string) ((int) $match[1]), 5, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^(\d+)$/', $value, $match) === 1) {
            return 'PM'.str_pad((string) ((int) $match[1]), 5, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    private function normalizeMethod(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/check-?off/i', $value) === 1) {
            return 'Check-off';
        }
        if (preg_match('/pdq|pos/i', $value) === 1) {
            return 'PDQ/POS';
        }
        if (preg_match('/m-?pesa/i', $value) === 1) {
            return 'Mpesa';
        }
        if (preg_match('/cheque|check/i', $value) === 1) {
            return 'Cheque';
        }
        if (preg_match('/bank\s*deposit/i', $value) === 1) {
            return 'Bank Deposit';
        }
        if (preg_match('/bank\s*transfer/i', $value) === 1) {
            return 'Bank Transfer';
        }
        if (preg_match('/cash/i', $value) === 1) {
            return 'Cash';
        }

        return Str::title($value);
    }

    private function normalizeRef(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strcasecmp($value, 'CASH') === 0) {
            return 'CASH';
        }

        return $value;
    }

    private function normalizeBank(string $value): string
    {
        $value = strtoupper(trim($value));
        foreach (self::BANK_LABELS as $bank) {
            if (strcasecmp($value, $bank) === 0) {
                return $bank;
            }
        }

        return $value;
    }

    public function extractPeriodMonth(string $particulars, string $txnDate): ?string
    {
        if (preg_match('/\b(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER|JAN|FEB|MAR|APR|JUN|JUL|AUG|SEP|SEPT|OCT|NOV|DEC)[\/\s,.-]+(\d{4})\b/i', $particulars, $match) === 1) {
            try {
                return Carbon::parse('1 '.$match[1].' '.$match[2])->format('Y-m');
            } catch (\Throwable) {
                // fall through
            }
        }
        if (preg_match('/\b(0?[1-9]|1[0-2])\/(\d{4})\b/', $particulars, $match) === 1) {
            return sprintf('%04d-%02d', (int) $match[2], (int) $match[1]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $txnDate) === 1) {
            return substr($txnDate, 0, 7);
        }

        return null;
    }

    private function parseDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Missing voucher date.');
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value) === 1) {
            return $value;
        }

        throw new RuntimeException('Invalid date: '.$value);
    }

    private function money(string $value): float
    {
        $raw = str_replace([',', ' '], '', $value);

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    private function cleanLine(string $rawLine): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $rawLine) ?? '');
        $line = trim((string) preg_replace('/Printed:.*$/i', '', $line));
        $line = trim((string) preg_replace('/Powered by EZEN.*$/i', '', $line));
        $line = trim((string) preg_replace('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},\s+\d{4}(?:,\s+\d{1,2}:\d{2}\s*[AP]M)?/i', '', $line));

        return trim($line);
    }

    private function isNoiseLine(string $line): bool
    {
        if (preg_match('/^(Printed:|Powered by|Invalid date|VOUCHER|METHOD|REF NO|PARTICULARS|PAID FROM|PAID TO|AMOUNT|RECORDED BY|PASSION SHELTAZ|PAYMENT VOUCHER|Showing page|Close|FINANCIAL ACCOUNTS)/i', $line)) {
            return true;
        }

        if (preg_match('/^--\s*\d+\s+of\s+\d+\s*--/i', $line)) {
            return true;
        }

        if (preg_match('/^[A-Z][a-z]{2}\s+\d{1,2},\s+\d{4}/', $line)) {
            return true;
        }

        if (preg_match('/^\d+\s+of\s+\d+$/i', $line)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string|null>  $cols
     */
    private function emptyCols(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }

    private function readText(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        if (in_array($extension, ['txt', 'text', 'log', 'csv', 'tsv'], true)) {
            $contents = file_get_contents($path);

            return is_string($contents) ? $contents : '';
        }

        return app(PassionLegacyRegisterPdfTextExtractor::class)->extract($path);
    }
}
