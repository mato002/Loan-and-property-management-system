<?php

namespace App\Services\Property;

use RuntimeException;

/** Reads the text based Equity Bank account statement PDF export. */
final class EquityBankStatementParser
{
    private const DATE = '\\d{2}/\\d{2}/\\d{4}';
    private const MONEY = '(?:\\d{1,3}(?:,\\d{3})+|\\d+)\\.\\d{2}';

    /** @return array<string, mixed> */
    public function parsePath(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File not found or not readable.');
        }
        $texts = app(PassionLegacyRegisterPdfTextExtractor::class)->extractCandidates($path);
        $best = null;
        foreach ($texts as $text) {
            if (! preg_match('/Equity|Account Number|Statement Date/i', $text)) {
                continue;
            }
            $parsed = $this->parseText($text, $path);
            if (count($parsed['lines']) > count($best['lines'] ?? [])) {
                $best = $parsed;
            }
        }
        if ($best === null || $best['lines'] === []) {
            throw new RuntimeException('This does not look like a readable Equity Bank statement. Upload the original text based PDF or export it as TXT.');
        }

        return $best;
    }

    /** @return array<string, mixed> */
    public function parseText(string $text, string $path = ''): array
    {
        $rawLines = preg_split('/\r\n|\r|\n/', str_replace("\u{00A0}", ' ', $text)) ?: [];
        // Some Equity PDF exports contain duplicate invisible text layers. Remove
        // adjacent repeated lines while keeping the original visual row grouping.
        $cleanLines = [];
        foreach ($rawLines as $line) {
            $line = trim((string) preg_replace('/[\t ]+/', ' ', $line));
            if ($line === '' || ($cleanLines !== [] && end($cleanLines) === $line)) continue;
            $cleanLines[] = $line;
        }
        $flat = implode(' ', $cleanLines);
        $account = preg_match('/Account Number\\s+(\\d{8,20})/i', $flat, $m) ? $m[1] : '';
        $name = preg_match('/Period\\s+\\d{2}\/\\d{2}\/\\d{4}\\s*-\\s*\\d{2}\/\\d{2}\/\\d{4}\\s+([A-Z][A-Z ]{3,50})\\s+254\\d{9}/i', $flat, $m) ? trim($m[1]) : '';
        $from = $to = null;
        if (preg_match('/(\\d{2}\/\\d{2}\/\\d{4})\\s*-\\s*(\\d{2}\/\\d{2}\/\\d{4})/', $flat, $m)) {
            $from = $this->date($m[1]); $to = $this->date($m[2]);
        }
        $totalCredit = $totalDebit = $closing = 0.0;
        if (preg_match('/Total\\s+([\\d,]+\\.\\d{2})\\s+([\\d,]+\\.\\d{2})\\s+([\\d,]+\\.\\d{2})/i', $flat, $m)) {
            $totalCredit = $this->money($m[1]); $totalDebit = $this->money($m[2]); $closing = $this->money($m[3]);
        }

        $rows = [];
        $lastBalance = null;
        $opening = null;
        $description = [];
        for ($i = 0; $i < count($cleanLines); $i++) {
            $line = $cleanLines[$i];
            if (preg_match('~^'.self::DATE.'$~', $line) !== 1) {
                if (! preg_match('/^(?:Disclaimer|Page|\\d+\\/\\d+|Transaction Details|Payment reference|Value Date|Credit \\(Money|Debit \\(Money|Balance|Total)$/i', $line)) {
                    $description[] = $line;
                }
                continue;
            }
            if (! isset($cleanLines[$i + 1], $cleanLines[$i + 2])
                || preg_match('/^'.self::MONEY.'$/', $cleanLines[$i + 1]) !== 1
                || preg_match('/^'.self::MONEY.'$/', $cleanLines[$i + 2]) !== 1) {
                $description = [];
                continue;
            }
            $desc = implode(' ', $description);
            $description = [];
            $txnDate = $this->date($line);
            $amount = $this->money($cleanLines[++$i]);
            $balance = $this->money($cleanLines[++$i]);
            if ($amount <= 0 || $amount > 100000000 || $balance > 100000000) continue;
            if ($lastBalance === null && $totalCredit + $totalDebit > 0) {
                $opening = $closing - $totalCredit + $totalDebit;
                $lastBalance = $opening;
            }
            $direction = $lastBalance !== null && $balance < $lastBalance - 0.001 ? 'debit' : 'credit';
            if ($lastBalance !== null && abs(abs($balance - $lastBalance) - $amount) > 0.03) {
                // Fees and bank rows may have separately rounded/posted values; classify by balance movement.
                $direction = $balance < $lastBalance ? 'debit' : 'credit';
            }
            $lastBalance = $balance;
            $ref = '';
            if (preg_match('/(?:EQ)?([A-Z0-9]{8,16})\\s*$/i', $desc, $r)) $ref = strtoupper($r[1]);
            if ($ref === '' && preg_match('/\\b(S\\d{5,12})\\b/', $desc, $r)) $ref = strtoupper($r[1]);
            if ($ref === '') $ref = 'EQ'.substr(sha1($desc.'|'.$txnDate.'|'.$amount.'|'.$balance), 0, 14);
            $isMpesa = stripos($desc, 'APP/MPESA/') !== false || stripos($desc, 'EQ'.$ref) !== false || preg_match('/\\b254\\d{9}\\b/', $desc);
            $isFee = stripos($desc, 'TRANSACTION + SMS CHARGE') !== false;
            $phone = preg_match('/\\b(254\\d{9})\\b/', $desc, $p) ? $p[1] : null;
            $counterparty = '';
            if (preg_match('/APP\/MPESA\/[^ ]+\/[^ ]+\/[^ ]+\/([^ ]+)/i', $desc, $cp)) $counterparty = trim($cp[1]);
            if ($counterparty === '' && preg_match("/071\\\\d{8}\\\\s+([A-Za-z][A-Za-z .-]{1,60})/i", $desc, $cp)) $counterparty = trim($cp[1]);
            if (preg_match('/\\b(EQ[A-Z0-9]{8,16})\\b/i', $desc, $r)) {
                $reference = strtoupper($r[1]);
            } elseif (preg_match('/\\b00([A-Z0-9]{8,16})\\b/i', $desc, $r)) {
                $reference = strtoupper($r[1]);
            } else {
                $reference = $ref;
            }
            $lineType = $isFee ? CoopBankAccountStatementParser::TYPE_CHARGE : ($isMpesa ? CoopBankAccountStatementParser::TYPE_MPESA : 'bank_transaction');
            $rows[] = [
                'source_key' => sha1(implode('|', [$txnDate, $reference, number_format($amount, 2, '.', ''), number_format($balance, 2, '.', ''), $direction])),
                'line_type' => $lineType, 'direction' => $direction, 'reference' => $reference,
                'txn_date' => $txnDate, 'amount' => $amount, 'running_balance' => $balance,
                'counterparty' => $counterparty, 'phone' => $phone, 'narration' => trim($desc),
            ];
        }
        $rows = array_values(array_reduce($rows, static function (array $carry, array $row): array { $carry[$row['source_key']] = $row; return $carry; }, []));
        if ($opening === null && $totalCredit + $totalDebit > 0) $opening = $closing - $totalCredit + $totalDebit;

        return [
            'bank_name' => 'Equity Bank', 'account_no' => $account, 'account_name' => $name,
            'currency' => 'KES', 'period_from' => $from, 'period_to' => $to,
            'opening_balance' => (float) ($opening ?? 0), 'closing_balance' => $closing,
            'total_debit' => $totalDebit, 'total_credit' => $totalCredit,
            'source_filename' => $path !== '' ? basename($path) : '', 'lines' => $rows,
        ];
    }

    private function date(string $value): string { return substr($value, 6, 4).'-'.substr($value, 3, 2).'-'.substr($value, 0, 2); }
    private function money(string $value): float { return (float) str_replace(',', '', $value); }
}
