<?php

namespace App\Services\Property;

use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

final class EzenBillsListingParser
{
    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_UNPAID = 'unpaid';

    /** @var list<string> */
    private const KNOWN_VENDORS = [
        'INDENTIQ GARNET VENTURES LTD',
        'SCOVITECH CLEANING SERVICES',
        'TOSHIA COMPANY LTD',
        'OSENTU VENTURES LTD',
        'ZURI WORLD',
    ];

    /**
     * @return list<array{
     *     source_key:string,
     *     ezen_bill_no:string,
     *     vendor_invoice_no:string,
     *     bill_date:string,
     *     due_date:string,
     *     vendor_name:string,
     *     memo:string,
     *     total_amount:float,
     *     total_paid:float,
     *     amount_due:float,
     *     listing_status:string,
     *     payment_status:string
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
        $listingStatus = 'closed';

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line);
            if ($this->emptyCols($cols)) {
                continue;
            }

            if (count($cols) === 1) {
                $maybeStatus = strtolower(trim((string) $cols[0]));
                if (in_array($maybeStatus, ['closed', 'open', 'overdue'], true)) {
                    $listingStatus = $maybeStatus;

                    continue;
                }
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

            $billNo = $this->normalizeBillNo($get('ezen_bill_no'));
            $total = $this->money($get('total_amount'));
            if ($billNo === '' || $total <= 0) {
                continue;
            }

            try {
                $billDate = $this->parseDate($get('bill_date'));
                $dueRaw = $get('due_date');
                $dueDate = $dueRaw !== '' ? $this->parseDate($dueRaw) : $billDate;
            } catch (RuntimeException) {
                continue;
            }

            $paid = $this->money($get('total_paid'));
            $due = $get('amount_due') !== '' ? $this->money($get('amount_due')) : round($total - $paid, 2);
            $rowListing = strtolower($get('listing_status'));
            if (! in_array($rowListing, ['closed', 'open', 'overdue'], true)) {
                $rowListing = $listingStatus;
            }

            $rows[] = $this->finalizeRow([
                'ezen_bill_no' => $billNo,
                'vendor_invoice_no' => $this->normalizeVendorInvoice($get('vendor_invoice_no')),
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'vendor_name' => $this->normalizeVendor($get('vendor_name')),
                'memo' => trim($get('memo')),
                'total_amount' => $total,
                'total_paid' => $paid,
                'amount_due' => $due,
                'listing_status' => $rowListing,
            ]);
        }

        return $this->uniqueBySourceKey($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseLayout(string $text): array
    {
        $rows = [];
        $buffer = null;
        $listingStatus = 'closed';

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $rawLine) {
            $line = $this->cleanLine($rawLine);
            if ($line === '' || $this->isNoiseLine($line)) {
                continue;
            }

            if ($this->isTotalsOnlyLine($line)) {
                if ($buffer !== null) {
                    $buffer = trim($buffer.' '.$line);
                    $parsed = $this->parseLayoutCombined($buffer, $listingStatus);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                        $buffer = null;
                    }
                }

                continue;
            }

            $statusLine = strtolower($line);
            if (in_array($statusLine, ['closed', 'open', 'overdue'], true)) {
                $listingStatus = $statusLine;
                continue;
            }

            if ($this->looksLikeBillStart($line)) {
                if ($buffer !== null) {
                    $parsed = $this->parseLayoutCombined($buffer, $listingStatus);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                    }
                }
                $buffer = $line;

                $parsed = $this->parseLayoutCombined($buffer, $listingStatus);
                if ($parsed !== null) {
                    $rows[] = $parsed;
                    $buffer = null;
                }

                continue;
            }

            if ($buffer !== null) {
                $buffer = trim($buffer.' '.$line);
                $parsed = $this->parseLayoutCombined($buffer, $listingStatus);
                if ($parsed !== null) {
                    $rows[] = $parsed;
                    $buffer = null;
                }
            }
        }

        if ($buffer !== null) {
            $parsed = $this->parseLayoutCombined($buffer, $listingStatus);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return $this->uniqueBySourceKey($rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function finalizeRow(array $row): array
    {
        $total = round((float) ($row['total_amount'] ?? 0), 2);
        $paid = round((float) ($row['total_paid'] ?? 0), 2);
        $due = round((float) ($row['amount_due'] ?? max(0, $total - $paid)), 2);
        $listing = strtolower(trim((string) ($row['listing_status'] ?? 'closed')));
        if (! in_array($listing, ['closed', 'open', 'overdue'], true)) {
            $listing = 'closed';
        }

        $billNo = $this->normalizeBillNo((string) ($row['ezen_bill_no'] ?? ''));
        $vendorInv = $this->normalizeVendorInvoice((string) ($row['vendor_invoice_no'] ?? ''));
        $vendor = $this->normalizeVendor((string) ($row['vendor_name'] ?? ''));
        $billDate = (string) ($row['bill_date'] ?? '');

        return [
            'source_key' => sha1(implode('|', [$billNo, $vendorInv, $billDate, $vendor, number_format($total, 2, '.', '')])),
            'ezen_bill_no' => $billNo,
            'vendor_invoice_no' => $vendorInv,
            'bill_date' => $billDate,
            'due_date' => (string) ($row['due_date'] ?? $billDate),
            'vendor_name' => $vendor,
            'memo' => trim((string) ($row['memo'] ?? '')),
            'total_amount' => $total,
            'total_paid' => $paid,
            'amount_due' => $due,
            'listing_status' => $listing,
            'payment_status' => $this->paymentStatus($total, $paid, $due, $listing),
        ];
    }

    public function paymentStatus(float $total, float $paid, float $due, string $listing): string
    {
        if ($due <= 0.009 && $paid >= ($total - 0.009)) {
            return self::STATUS_PAID;
        }
        if ($paid > 0.009) {
            return self::STATUS_PARTIAL;
        }
        if ($listing === 'closed' && $due <= 0.009) {
            return self::STATUS_PAID;
        }

        return self::STATUS_UNPAID;
    }

    private function parseLayoutCombined(string $combined, string $listingStatus): ?array
    {
        if (preg_match_all('/\d{2}\/\d{2}\/\d{4}/', $combined, $dateMatches, PREG_OFFSET_CAPTURE) !== 2) {
            return null;
        }

        $billDateRaw = (string) $dateMatches[0][0][0];
        $dueDateRaw = (string) $dateMatches[0][1][0];
        $firstPos = (int) $dateMatches[0][0][1];
        $secondPos = (int) $dateMatches[0][1][1];
        $before = trim(substr($combined, 0, $firstPos));
        $after = trim(substr($combined, $secondPos + 10));

        if (preg_match('/^(AP\d+|\d+)\s+(.+)$/i', $before, $head) !== 1) {
            return null;
        }

        if (preg_match('/^(.+?)\s+((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?)\s+((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?)\s+((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?)\s*$/', $after, $tail) !== 1) {
            return null;
        }

        $total = $this->money((string) $tail[2]);
        $paid = $this->money((string) $tail[3]);
        $due = $this->money((string) $tail[4]);
        if ($total <= 0) {
            return null;
        }

        [$vendor, $memo] = $this->splitVendorMemo(trim((string) $tail[1]));
        if ($vendor === '') {
            return null;
        }

        try {
            $billDate = $this->parseDate($billDateRaw);
            $dueDate = $this->parseDate($dueDateRaw);
        } catch (RuntimeException) {
            return null;
        }

        return $this->finalizeRow([
            'ezen_bill_no' => $this->normalizeBillNo((string) $head[1]),
            'vendor_invoice_no' => $this->normalizeVendorInvoice((string) $head[2]),
            'bill_date' => $billDate,
            'due_date' => $dueDate,
            'vendor_name' => $vendor,
            'memo' => $memo,
            'total_amount' => $total,
            'total_paid' => $paid,
            'amount_due' => $due,
            'listing_status' => $listingStatus,
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitVendorMemo(string $middle): array
    {
        $upper = strtoupper($middle);
        $vendors = self::KNOWN_VENDORS;
        usort($vendors, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($vendors as $vendor) {
            if (str_starts_with($upper, $vendor)) {
                return [
                    $this->normalizeVendor($vendor),
                    trim(substr($middle, strlen($vendor))),
                ];
            }
        }

        if (preg_match('/^(.+?)\s+(GARBAGE\b.*)$/i', $middle, $match) === 1) {
            return [
                $this->normalizeVendor((string) $match[1]),
                trim((string) $match[2]),
            ];
        }

        if (preg_match('/^(.+?\b(?:LTD|LIMITED|SERVICES|COMPANY))\s+(.+)$/i', $middle, $match) === 1) {
            return [
                $this->normalizeVendor((string) $match[1]),
                trim((string) $match[2]),
            ];
        }

        return [$this->normalizeVendor($middle), ''];
    }

    private function looksLikeBillStart(string $line): bool
    {
        if (preg_match('/^AP\d+\b/i', $line) === 1) {
            return true;
        }

        if ($this->isTotalsOnlyLine($line)) {
            return false;
        }

        return preg_match('/^\d{3,6}\s+\S+.*\d{2}\/\d{2}\/\d{4}/', $line) === 1;
    }

    private function isTotalsOnlyLine(string $line): bool
    {
        return preg_match('/^(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?(?:\s+(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?){1,2}$/', $line) === 1;
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

    private function looksLikeCsv(string $text): bool
    {
        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            return substr_count($line, ',') >= 5
                && preg_match('/bill|vendor|customer|memo/i', $line) === 1;
        }

        return false;
    }

    /**
     * @param  list<string>  $normalized
     */
    private function isHeaderRow(array $normalized): bool
    {
        $joined = implode(' ', $normalized);

        return (str_contains($joined, 'bill') || str_contains($joined, 'invoice'))
            && (str_contains($joined, 'customer') || str_contains($joined, 'vendor') || str_contains($joined, 'memo'));
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
            'bill', 'bill_no', 'billno', 'ezen_bill_no' => 'ezen_bill_no',
            'ven_inv', 'ven_inv_no', 'vendor_inv', 'vendor_invoice', 'vendor_invoice_no', 'inv_no' => 'vendor_invoice_no',
            'date', 'bill_date', 'invoice_date' => 'bill_date',
            'due_date', 'due' => 'due_date',
            'customer', 'vendor', 'vendor_name', 'supplier' => 'vendor_name',
            'memo', 'description', 'particulars', 'narration' => 'memo',
            'total_amt', 'total_amount', 'amount', 'total' => 'total_amount',
            'total_paid', 'paid', 'amt_paid' => 'total_paid',
            'amt_due', 'amount_due', 'balance' => 'amount_due',
            'status', 'listing_status' => 'listing_status',
            default => '',
        };
    }

    public function normalizeBillNo(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^AP0*(\d+)$/', $value, $match) === 1) {
            return 'AP'.str_pad((string) ((int) $match[1]), 4, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    private function normalizeVendorInvoice(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = (string) preg_replace('#\s*/\s*#', '/', $value);
        $value = (string) preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function normalizeVendor(string $value): string
    {
        $value = strtoupper(trim((string) preg_replace('/\s+/', ' ', $value)));

        return rtrim($value, ' .,-');
    }

    private function parseDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Missing bill date.');
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value) === 1) {
            return $value;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            throw new RuntimeException('Invalid date: '.$value);
        }
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

        return trim($line);
    }

    private function isNoiseLine(string $line): bool
    {
        if (preg_match('/^(Printed:|Powered by|BILL\.?\s*#|VEN\.?\s*INV|DATE DUE|CUSTOMER|MEMO|TOTAL AMT|PASSION SHELTAZ|BILLS LISTING|Showing page|Close|FINANCIAL ACCOUNTS|Invalid date)/i', $line) === 1) {
            return true;
        }

        if (preg_match('/^\d+\s+of\s+\d+$/i', $line) === 1) {
            return true;
        }

        if (preg_match('/^--\s*\d+\s+of\s+\d+\s*--$/i', $line) === 1) {
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
