<?php

namespace App\Services\Property;

use Illuminate\Support\Str;
use RuntimeException;

final class EzenRentalInvoiceScheduleParser
{
    private const MEMO_PREFIXES = [
        'WATER Meter Reading',
        'ELECTRICITY DEPOSIT',
        'WATER DEPOSIT',
        'RENT DEPOSIT',
        'Lease fee',
        'GARBAGE for',
        'Electricity for',
        'ELECTRICITY for',
        'SERVICE CHARGE for',
        'Rent for',
        'Water for',
    ];

    /**
     * @return list<array{
     *     ezen_invoice_no:string,
     *     property_code:string,
     *     header_tail:string,
     *     memo:string,
     *     issue_date:string,
     *     amount:float,
     *     paid:float,
     *     due:float
     * }>
     */
    public function parsePath(string $path): array
    {
        return $this->parseText($this->readText($path));
    }

    /**
     * @return list<array{
     *     ezen_invoice_no:string,
     *     property_code:string,
     *     header_tail:string,
     *     memo:string,
     *     issue_date:string,
     *     amount:float,
     *     paid:float,
     *     due:float
     * }>
     */
    public function parseText(string $text): array
    {
        $rows = [];
        $buffer = null;

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $rawLine) {
            $line = trim(preg_replace('/\s+/', ' ', $rawLine) ?? '');
            $line = trim((string) preg_replace('/Printed:.*$/i', '', $line));
            $line = trim((string) preg_replace('/Powered by EZEN.*$/i', '', $line));
            if ($line === '' || $this->isNoiseLine($line)) {
                continue;
            }

            if (preg_match('/^(INV\d+)\s+([A-Z0-9]+)\s+~\s+(.+)\s+(\d{2}\/\d{2}\/\d{4})\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s*$/i', $line, $single) === 1) {
                $split = $this->splitHeaderAndMemo((string) $single[3]);
                $rows[] = $this->buildRow(
                    strtoupper($single[1]),
                    strtoupper($single[2]),
                    $split['header_tail'],
                    $split['memo'],
                    (string) $single[4],
                    (string) $single[5],
                    (string) $single[6],
                    (string) $single[7],
                );
                $buffer = null;

                continue;
            }

            if (preg_match('/^(INV\d+)\s+([A-Z0-9]+)\s+~\s+(.+)$/i', $line, $start) === 1) {
                if ($buffer !== null) {
                    $rows = array_merge($rows, $this->completeBuffer($buffer));
                }
                $buffer = [
                    'ezen_invoice_no' => strtoupper($start[1]),
                    'property_code' => strtoupper($start[2]),
                    'parts' => [trim((string) $start[3])],
                ];

                continue;
            }

            if ($buffer !== null) {
                if ($this->isTaxPinOnlyLine($line)) {
                    $buffer['parts'][] = $line;

                    continue;
                }

                $finish = $this->matchFinishLine($line);
                if ($finish !== null) {
                    $row = $this->tryCompleteBufferedRow(
                        $buffer,
                        trim((string) $finish['memo_tail']),
                        (string) $finish['date'],
                        (string) $finish['amount'],
                        (string) $finish['paid'],
                        (string) $finish['due'],
                    );
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                    $buffer = null;

                    continue;
                }

                if (preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s*$/', $line, $dateOnly) === 1) {
                    $row = $this->tryCompleteBufferedRow(
                        $buffer,
                        null,
                        (string) $dateOnly[1],
                        (string) $dateOnly[2],
                        (string) $dateOnly[3],
                        (string) $dateOnly[4],
                    );
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                    $buffer = null;

                    continue;
                }

                $buffer['parts'][] = $line;
            }
        }

        if ($buffer !== null) {
            $rows = array_merge($rows, $this->completeBuffer($buffer));
        }

        return $rows;
    }

    /**
     * @param  array{ezen_invoice_no:string, property_code:string, parts:list<string>}  $buffer
     * @return list<array<string, mixed>>
     */
    private function completeBuffer(array $buffer): array
    {
        return [];
    }

    /**
     * @param  array{ezen_invoice_no:string, property_code:string, parts:list<string>}  $buffer
     * @return array<string, mixed>|null
     */
    private function tryCompleteBufferedRow(
        array $buffer,
        ?string $memoSuffix,
        string $date,
        string $amount,
        string $paid,
        string $due,
    ): ?array {
        try {
            return $this->completeBufferedRow($buffer, $memoSuffix, $date, $amount, $paid, $due);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @param  array{ezen_invoice_no:string, property_code:string, parts:list<string>}  $buffer
     * @return array<string, mixed>
     */
    private function completeBufferedRow(
        array $buffer,
        ?string $memoSuffix,
        string $date,
        string $amount,
        string $paid,
        string $due,
    ): array {
        $chunks = $buffer['parts'];
        if ($memoSuffix !== null && trim($memoSuffix) !== '') {
            $chunks[] = trim($memoSuffix);
        }
        $combined = trim(implode(' ', $chunks));
        $split = $this->splitHeaderAndMemo($combined);

        return $this->buildRow(
            $buffer['ezen_invoice_no'],
            $buffer['property_code'],
            $split['header_tail'],
            $split['memo'],
            $date,
            $amount,
            $paid,
            $due,
        );
    }

    /**
     * @return array{header_tail:string, memo:string}
     */
    private function splitHeaderAndMemo(string $chunk): array
    {
        $upper = strtoupper($chunk);
        $bestPos = null;
        foreach (self::MEMO_PREFIXES as $prefix) {
            $pos = strpos($upper, strtoupper($prefix));
            if ($pos === false) {
                continue;
            }
            if ($bestPos === null || $pos < $bestPos) {
                $bestPos = $pos;
            }
        }

        if ($bestPos === null) {
            return ['header_tail' => $this->stripTaxPin($chunk), 'memo' => ''];
        }

        $headerTail = trim(substr($chunk, 0, $bestPos));
        $memo = trim(substr($chunk, $bestPos));

        return [
            'header_tail' => $this->stripTaxPin($headerTail),
            'memo' => preg_replace('/^\s*(?:0|[A-Z0-9]{9,12}[A-Z]?)\s+/', '', $memo) ?? $memo,
        ];
    }

    private function stripTaxPin(string $value): string
    {
        return trim((string) preg_replace('/\s+(?:0|[A-Z0-9]{9,12}[A-Z]?)\s*$/', '', $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(
        string $invoiceNo,
        string $propertyCode,
        string $headerTail,
        string $memo,
        string $date,
        string $amount,
        string $paid,
        string $due,
    ): array {
        if ($memo === '') {
            $memo = 'Imported charge';
        }

        return [
            'ezen_invoice_no' => $invoiceNo,
            'property_code' => $propertyCode,
            'header_tail' => $headerTail,
            'memo' => $memo,
            'issue_date' => $this->parseDate($date),
            'amount' => $this->money($amount),
            'paid' => $this->money($paid),
            'due' => $this->money($due),
        ];
    }

    private function parseDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m) !== 1) {
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
        if (preg_match('/^(Printed:|Powered by|INVOICE #|PROPERTY UNIT TENANT|PASSION SHELTAZ|RENTAL INVOICING SCHEDULE)/i', $line)) {
            return true;
        }

        if (preg_match('/^\d+\s+of\s*$/i', $line)) {
            return true;
        }

        return preg_match('/^[A-Z0-9]+\s+~\s+.+(APARTMENT|APARTMENTS|HOUSE|COURT|PLAZA|HEIGHTS|MAKAO|SHOP|BLOCK|ESTATE|GARDENS|VILLAS|FLATS|COMPLEX|SUITES|INVESTMENTS)$/i', $line) === 1;
    }

    private function isTaxPinOnlyLine(string $line): bool
    {
        return preg_match('/^(?:0|[A-Z0-9]{9,12}[A-Z]?)$/i', $line) === 1;
    }

    /**
     * @return array{memo_tail:string, date:string, amount:string, paid:string, due:string}|null
     */
    private function matchFinishLine(string $line): ?array
    {
        if (preg_match('/^(.+)\s+(\d{2}\/\d{2}\/\d{4})\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s*$/', $line, $full) !== 1) {
            return null;
        }

        $memoTail = trim((string) $full[1]);
        if ($memoTail === '' || preg_match('/^\d+$/', $memoTail) === 1) {
            return null;
        }

        return [
            'memo_tail' => $memoTail,
            'date' => (string) $full[2],
            'amount' => (string) $full[3],
            'paid' => (string) $full[4],
            'due' => (string) $full[5],
        ];
    }

    private function readText(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable: '.$path);
        }

        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        if (in_array($extension, ['txt', 'text', 'log'], true)) {
            $contents = file_get_contents($path);

            return is_string($contents) ? $contents : '';
        }

        return app(PassionLegacyRegisterPdfTextExtractor::class)->extract($path);
    }
}
