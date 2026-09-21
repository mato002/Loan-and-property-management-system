<?php

namespace App\Services\Property;

use RuntimeException;

/**
 * Parse Safaricom Paybill / Till C2B statement CSV (Receipt No, Paid In, Details, …).
 * Mirrors the MFI SafaricomC2BStatementParser column aliases, without PhpSpreadsheet.
 */
final class SafaricomC2bCsvStatementParser
{
    /**
     * @return array{
     *     bank_name:string,
     *     account_no:string,
     *     account_name:string,
     *     currency:string,
     *     period_from:?string,
     *     period_to:?string,
     *     opening_balance:float,
     *     closing_balance:float,
     *     total_debit:float,
     *     total_credit:float,
     *     source_filename:string,
     *     lines:list<array<string, mixed>>
     * }
     */
    public function parsePath(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Statement file is not readable.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open statement file.');
        }

        $header = null;
        $map = [];
        $lines = [];
        $lineNumber = 0;
        $creditTotal = 0.0;
        $debitTotal = 0.0;
        $minDate = null;
        $maxDate = null;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                if ($header === null) {
                    $header = $row;
                    $map = $this->buildColumnMap($header);
                    if (! isset($map['reference']) || ! isset($map['paid_in'])) {
                        throw new RuntimeException(
                            'CSV must include Receipt/Trans ID and Paid In columns (Safaricom C2B export).'
                        );
                    }

                    continue;
                }

                $parsed = $this->mapRow($row, $map, $lineNumber);
                if ($parsed === null) {
                    continue;
                }

                $lines[] = $parsed;
                if ($parsed['direction'] === 'credit') {
                    $creditTotal += (float) $parsed['amount'];
                } else {
                    $debitTotal += (float) $parsed['amount'];
                }

                $d = (string) ($parsed['txn_date'] ?? '');
                if ($d !== '') {
                    $minDate = $minDate === null || $d < $minDate ? $d : $minDate;
                    $maxDate = $maxDate === null || $d > $maxDate ? $d : $maxDate;
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'bank_name' => 'Safaricom M-Pesa',
            'account_no' => '',
            'account_name' => 'C2B collections',
            'currency' => 'KES',
            'period_from' => $minDate,
            'period_to' => $maxDate,
            'opening_balance' => 0.0,
            'closing_balance' => 0.0,
            'total_debit' => round($debitTotal, 2),
            'total_credit' => round($creditTotal, 2),
            'source_filename' => basename($path),
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function buildColumnMap(array $header): array
    {
        $aliases = [
            'reference' => ['receipt no', 'receipt no.', 'receipt', 'transaction id', 'trans id', 'transid', 'mpesa receipt'],
            'txn_at' => ['completion time', 'transaction date', 'date', 'trans time', 'trans time'],
            'details' => ['details', 'description', 'narration', 'transaction type'],
            'paid_in' => ['paid in', 'credit', 'money in'],
            'paid_out' => ['withdrawn', 'paid out', 'debit', 'money out'],
            'balance' => ['balance', 'running balance', 'ledger balance'],
            'counterparty' => ['opposite party', 'phone number', 'msisdn', 'party'],
            'account_reference' => ['account number', 'bill ref number', 'billrefnumber', 'account ref', 'bill ref'],
        ];

        $map = [];
        foreach ($header as $index => $label) {
            $norm = $this->normalizeHeader((string) $label);
            if ($norm === '') {
                continue;
            }
            foreach ($aliases as $logical => $names) {
                if (isset($map[$logical])) {
                    continue;
                }
                if (in_array($norm, $names, true)) {
                    $map[$logical] = $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $map
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row, array $map, int $lineNumber): ?array
    {
        $reference = strtoupper(trim((string) ($row[$map['reference']] ?? '')));
        if ($reference === '' || ! preg_match('/^[A-Z0-9]{8,20}$/', $reference)) {
            return null;
        }

        $paidIn = $this->parseAmount((string) ($row[$map['paid_in']] ?? '0'));
        $paidOut = isset($map['paid_out']) ? $this->parseAmount((string) ($row[$map['paid_out']] ?? '0')) : 0.0;
        $amount = $paidIn > 0 ? $paidIn : $paidOut;
        if ($amount < 0.01) {
            return null;
        }

        $direction = $paidIn >= $paidOut ? 'credit' : 'debit';
        $details = isset($map['details']) ? trim((string) ($row[$map['details']] ?? '')) : '';
        $extracted = $this->extractFromDetails($details);

        $accountRef = isset($map['account_reference'])
            ? trim((string) ($row[$map['account_reference']] ?? ''))
            : '';
        if ($accountRef === '') {
            $accountRef = (string) ($extracted['account_reference'] ?? '');
        }

        $counterparty = isset($map['counterparty'])
            ? trim((string) ($row[$map['counterparty']] ?? ''))
            : '';
        if ($counterparty === '') {
            $counterparty = (string) ($extracted['payer_name'] ?? '');
        }

        $phone = $extracted['phone'] ?? null;
        if ($phone === null && preg_match('/254\d{9}/', $counterparty, $m) === 1) {
            $phone = $m[0];
        }

        $txnRaw = isset($map['txn_at']) ? (string) ($row[$map['txn_at']] ?? '') : '';
        $txnDate = $this->parseDate($txnRaw) ?? now()->toDateString();
        $balance = isset($map['balance']) ? $this->parseAmount((string) ($row[$map['balance']] ?? '0')) : 0.0;

        $narration = trim(implode(' · ', array_filter([
            $details !== '' ? $details : null,
            $accountRef !== '' ? 'Acc '.$accountRef : null,
        ])));

        return [
            'source_key' => sha1(implode('|', [
                CoopBankAccountStatementParser::TYPE_MPESA,
                $reference,
                $txnDate,
                number_format($amount, 2, '.', ''),
            ])),
            'line_type' => CoopBankAccountStatementParser::TYPE_MPESA,
            'direction' => $direction,
            'reference' => $reference,
            'txn_date' => $txnDate,
            'amount' => $amount,
            'running_balance' => $balance,
            'counterparty' => $counterparty,
            'phone' => $phone,
            'narration' => $narration !== '' ? $narration : 'Safaricom C2B '.$reference,
            'account_reference' => $accountRef !== '' ? $accountRef : null,
            '_line_number' => $lineNumber,
        ];
    }

    /**
     * @return array{account_reference?:string, payer_name?:string, phone?:string}
     */
    private function extractFromDetails(string $details): array
    {
        $out = [];
        if (preg_match('/Acc(?:ount)?\.?\s*([A-Za-z0-9\-\/]+)/i', $details, $m) === 1) {
            $out['account_reference'] = trim($m[1]);
        }
        if (preg_match('/\b(254\d{9})\b/', $details, $m) === 1) {
            $out['phone'] = $m[1];
        }
        if (preg_match('/from\s+([A-Za-z][A-Za-z .\'-]{1,60})/i', $details, $m) === 1) {
            $out['payer_name'] = trim($m[1]);
        }

        return $out;
    }

    private function parseAmount(string $raw): float
    {
        $clean = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $raw)) ?? '0';

        return round((float) $clean, 2);
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function normalizeHeader(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;

        return $label;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
