<?php

namespace App\Services\Property;

use App\Models\PmBankStatement;
use App\Models\PmBankStatementLine;
use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\UnassignedPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class CoopBankAccountStatementImportService
{
    public function __construct(
        private readonly CoopBankAccountStatementParser $parser,
    ) {}

    /**
     * @return array{
     *     parsed:int,
     *     statements:int,
     *     lines_upserted:int,
     *     matched:int,
     *     unmatched:int,
     *     bank_only:int,
     *     credit_total:float,
     *     debit_total:float,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(string $path, int $agentUserId, bool $dryRun = false): array
    {
        return $this->importParsed($this->parser->parsePath($path), $agentUserId, $dryRun);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{
     *     parsed:int,
     *     statements:int,
     *     statement_id:int|null,
     *     lines_upserted:int,
     *     matched:int,
     *     unmatched:int,
     *     bank_only:int,
     *     credit_total:float,
     *     debit_total:float,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importParsed(array $parsed, int $agentUserId, bool $dryRun = false): array
    {
        $lines = $parsed['lines'] ?? [];
        if ($lines === []) {
            throw new RuntimeException(
                'The file uploaded as a statement, but no transactions could be read from it. '
                .'For Co-operative Bank PDFs, export or Save as TXT and upload that file if this happens again.'
            );
        }

        $summary = [
            'parsed' => count($lines),
            'statements' => 0,
            'statement_id' => null,
            'lines_upserted' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'bank_only' => 0,
            'credit_total' => 0.0,
            'debit_total' => 0.0,
            'warnings' => [],
            'errors' => [],
        ];

        foreach ($lines as $line) {
            if (($line['direction'] ?? '') === 'debit') {
                $summary['debit_total'] += (float) $line['amount'];
            } else {
                $summary['credit_total'] += (float) $line['amount'];
            }
        }

        $headerCredit = round((float) ($parsed['total_credit'] ?? 0), 2);
        $headerDebit = round((float) ($parsed['total_debit'] ?? 0), 2);
        if ($headerCredit > 0 && abs(round($summary['credit_total'], 2) - $headerCredit) > 0.009) {
            $summary['warnings'][] = 'Parsed credits '.number_format($summary['credit_total'], 2)
                .' do not match statement total credit '.number_format($headerCredit, 2).'.';
        }
        if ($headerDebit > 0 && abs(round($summary['debit_total'], 2) - $headerDebit) > 0.009) {
            $summary['warnings'][] = 'Parsed debits '.number_format($summary['debit_total'], 2)
                .' do not match statement total debit '.number_format($headerDebit, 2).'.';
        }

        $process = function () use ($parsed, $lines, $agentUserId, $dryRun, &$summary): void {
            $statementId = 0;
            if (! $dryRun) {
                $statement = $this->upsertStatement($agentUserId, $parsed);
                $statementId = (int) $statement->id;
                $summary['statements'] = 1;
                $summary['statement_id'] = $statementId;
            } else {
                $summary['statements'] = 1;
            }

            $matchesByKey = [];
            foreach ($lines as $line) {
                $matchesByKey[(string) $line['source_key']] = $this->matchLine($agentUserId, $line);
            }
            $this->attachSplitMpesaSiblings($lines, $matchesByKey);

            foreach ($lines as $line) {
                $match = $matchesByKey[(string) $line['source_key']];
                if ($match['match_status'] === PmBankStatementLine::MATCH_MATCHED) {
                    $summary['matched']++;
                } elseif ($match['match_status'] === PmBankStatementLine::MATCH_BANK_ONLY) {
                    $summary['bank_only']++;
                } else {
                    $summary['unmatched']++;
                }

                if ($dryRun) {
                    $summary['lines_upserted']++;
                    continue;
                }

                $this->upsertLine($agentUserId, $statementId, $line, $match);
                $summary['lines_upserted']++;
            }
        };

        if ($dryRun) {
            $process();

            return $summary;
        }

        if (! Schema::hasTable('pm_bank_statements') || ! Schema::hasTable('pm_bank_statement_lines')) {
            throw new RuntimeException('Run migrations first (pm_bank_statements is missing).');
        }

        DB::transaction($process);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function upsertStatement(int $agentUserId, array $parsed): PmBankStatement
    {
        $sourceKey = sha1(implode('|', [
            (string) ($parsed['account_no'] ?? ''),
            (string) ($parsed['period_from'] ?? ''),
            (string) ($parsed['period_to'] ?? ''),
            number_format((float) ($parsed['opening_balance'] ?? 0), 2, '.', ''),
            number_format((float) ($parsed['closing_balance'] ?? 0), 2, '.', ''),
        ]));

        return PmBankStatement::query()->updateOrCreate(
            [
                'agent_user_id' => $agentUserId,
                'source_key' => $sourceKey,
            ],
            [
                'bank_name' => (string) ($parsed['bank_name'] ?? 'Co-operative Bank'),
                'account_no' => (string) ($parsed['account_no'] ?? ''),
                'account_name' => (string) ($parsed['account_name'] ?? ''),
                'currency' => (string) ($parsed['currency'] ?? 'KES'),
                'period_from' => $parsed['period_from'] ?? null,
                'period_to' => $parsed['period_to'] ?? null,
                'opening_balance' => (float) ($parsed['opening_balance'] ?? 0),
                'closing_balance' => (float) ($parsed['closing_balance'] ?? 0),
                'total_debit' => (float) ($parsed['total_debit'] ?? 0),
                'total_credit' => (float) ($parsed['total_credit'] ?? 0),
                'source_filename' => (string) ($parsed['source_filename'] ?? ''),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array{match_status:string, matched_type:?string, pm_payment_id:?int, pm_ezen_receipt_register_id:?int, unassigned_payment_id:?int}  $match
     */
    private function upsertLine(int $agentUserId, int $statementId, array $line, array $match): void
    {
        PmBankStatementLine::query()->updateOrCreate(
            [
                'agent_user_id' => $agentUserId,
                'source_key' => (string) $line['source_key'],
            ],
            [
                'pm_bank_statement_id' => $statementId,
                'line_type' => (string) $line['line_type'],
                'direction' => (string) $line['direction'],
                'txn_date' => $line['txn_date'] ?? null,
                'reference' => (string) $line['reference'],
                'amount' => (float) $line['amount'],
                'running_balance' => $line['running_balance'] ?? null,
                'counterparty' => $line['counterparty'] !== '' ? (string) $line['counterparty'] : null,
                'phone' => $line['phone'] ?? null,
                'narration' => $line['narration'] ?? null,
                'match_status' => $match['match_status'],
                'matched_type' => $match['matched_type'],
                'pm_payment_id' => $match['pm_payment_id'],
                'pm_ezen_receipt_register_id' => $match['pm_ezen_receipt_register_id'],
                'unassigned_payment_id' => $match['unassigned_payment_id'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{match_status:string, matched_type:?string, pm_payment_id:?int, pm_ezen_receipt_register_id:?int, unassigned_payment_id:?int}
     */
    private function matchLine(int $agentUserId, array $line): array
    {
        $empty = [
            'match_status' => PmBankStatementLine::MATCH_UNMATCHED,
            'matched_type' => null,
            'pm_payment_id' => null,
            'pm_ezen_receipt_register_id' => null,
            'unassigned_payment_id' => null,
        ];

        $type = (string) ($line['line_type'] ?? '');
        if ($type !== CoopBankAccountStatementParser::TYPE_MPESA) {
            $empty['match_status'] = PmBankStatementLine::MATCH_BANK_ONLY;

            return $empty;
        }

        $reference = strtoupper(trim((string) ($line['reference'] ?? '')));
        if ($reference === '') {
            return $empty;
        }

        if (Schema::hasTable('pm_ezen_receipt_register')) {
            $receipt = $this->findReceiptByReference($agentUserId, $reference);
            if ($receipt) {
                return [
                    'match_status' => PmBankStatementLine::MATCH_MATCHED,
                    'matched_type' => 'ezen_receipt',
                    'pm_payment_id' => $receipt->pm_payment_id ? (int) $receipt->pm_payment_id : null,
                    'pm_ezen_receipt_register_id' => (int) $receipt->id,
                    'unassigned_payment_id' => null,
                ];
            }
        }

        if (Schema::hasTable('pm_payments')) {
            $payment = $this->findPaymentByReference($agentUserId, $reference);
            if ($payment) {
                return [
                    'match_status' => PmBankStatementLine::MATCH_MATCHED,
                    'matched_type' => 'payment',
                    'pm_payment_id' => (int) $payment->id,
                    'pm_ezen_receipt_register_id' => null,
                    'unassigned_payment_id' => null,
                ];
            }
        }

        if (Schema::hasTable('unassigned_payments')) {
            $unassigned = UnassignedPayment::query()
                ->where(function ($q) use ($reference) {
                    foreach ($this->referenceCandidates($reference) as $candidate) {
                        $q->orWhere('transaction_id', $candidate);
                    }
                })
                ->when(
                    Schema::hasColumn('unassigned_payments', 'agent_user_id'),
                    fn ($q) => $q->where(function ($inner) use ($agentUserId) {
                        $inner->where('agent_user_id', $agentUserId)->orWhereNull('agent_user_id');
                    }),
                )
                ->orderByDesc('id')
                ->first();
            if ($unassigned) {
                return [
                    'match_status' => PmBankStatementLine::MATCH_MATCHED,
                    'matched_type' => 'unassigned',
                    'pm_payment_id' => null,
                    'pm_ezen_receipt_register_id' => null,
                    'unassigned_payment_id' => (int) $unassigned->id,
                ];
            }
        }

        return $empty;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, array{match_status:string, matched_type:?string, pm_payment_id:?int, pm_ezen_receipt_register_id:?int, unassigned_payment_id:?int}>  $matchesByKey
     */
    private function attachSplitMpesaSiblings(array $lines, array &$matchesByKey): void
    {
        foreach ($lines as $line) {
            $key = (string) $line['source_key'];
            $match = $matchesByKey[$key] ?? null;
            if ($match === null || ($match['match_status'] ?? '') !== PmBankStatementLine::MATCH_UNMATCHED) {
                continue;
            }
            if (($line['line_type'] ?? '') !== CoopBankAccountStatementParser::TYPE_MPESA) {
                continue;
            }
            $phone = (string) ($line['phone'] ?? '');
            $txnDate = (string) ($line['txn_date'] ?? '');
            if ($phone === '' || $txnDate === '') {
                continue;
            }
            foreach ($lines as $other) {
                if ((string) $other['source_key'] === $key) {
                    continue;
                }
                if ((string) ($other['phone'] ?? '') !== $phone || (string) ($other['txn_date'] ?? '') !== $txnDate) {
                    continue;
                }
                $otherMatch = $matchesByKey[(string) $other['source_key']] ?? null;
                if ($otherMatch && ($otherMatch['match_status'] ?? '') === PmBankStatementLine::MATCH_MATCHED) {
                    $matchesByKey[$key] = $otherMatch;
                    break;
                }
            }
        }
    }

    private function findReceiptByReference(int $agentUserId, string $reference): ?PmEzenReceiptRegister
    {
        $query = PmEzenReceiptRegister::query()->where('agent_user_id', $agentUserId);
        $this->applyReferenceMatch($query, 'ref_no', $reference);

        return $query->orderByDesc('id')->first();
    }

    private function findPaymentByReference(int $agentUserId, string $reference): ?PmPayment
    {
        $query = PmPayment::query()
            ->when(
                Schema::hasColumn('pm_payments', 'agent_user_id'),
                fn ($q) => $q->where('agent_user_id', $agentUserId),
            );
        $this->applyReferenceMatch($query, 'external_ref', $reference);

        return $query->orderByDesc('id')->first();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyReferenceMatch($query, string $column, string $reference): void
    {
        $candidates = $this->referenceCandidates($reference);
        $query->where(function ($inner) use ($column, $candidates) {
            foreach ($candidates as $candidate) {
                $inner->orWhereRaw('UPPER('.$column.') = ?', [$candidate])
                    ->orWhereRaw('UPPER('.$column.') LIKE ?', ['%'.$candidate.'%']);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function referenceCandidates(string $reference): array
    {
        $reference = strtoupper(trim($reference));
        $candidates = [$reference];
        $last = substr($reference, -1);
        if ($last === 'I') {
            $candidates[] = substr($reference, 0, -1).'1';
        } elseif ($last === '1') {
            $candidates[] = substr($reference, 0, -1).'I';
        }

        return array_values(array_unique($candidates));
    }
}
