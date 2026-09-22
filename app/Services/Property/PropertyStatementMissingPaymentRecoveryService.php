<?php

namespace App\Services\Property;

use App\Models\PmBankStatement;
use App\Models\PmBankStatementLine;
use App\Repositories\Equity\EquityPaymentRepository;
use Illuminate\Support\Facades\Schema;

/**
 * MFI-style recovery: statement credits missing in the system land in Unmatched
 * so the agent can assign a tenant and settle (same queue as Equity / SMS).
 */
final class PropertyStatementMissingPaymentRecoveryService
{
    public function __construct(
        private readonly EquityPaymentRepository $payments,
    ) {}

    /**
     * @return array{recovered:int, skipped:int, errors:list<string>, unassigned_payment_id:?int}
     */
    public function recoverLine(PmBankStatementLine $line, int $agentUserId): array
    {
        $summary = ['recovered' => 0, 'skipped' => 0, 'errors' => [], 'unassigned_payment_id' => null];

        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasTable('payments')) {
            $summary['errors'][] = 'Unassigned payments tables are missing.';

            return $summary;
        }

        if ((string) $line->direction !== 'credit'
            || (string) $line->match_status !== PmBankStatementLine::MATCH_UNMATCHED
            || (string) $line->line_type !== CoopBankAccountStatementParser::TYPE_MPESA
        ) {
            $summary['skipped']++;
            $summary['errors'][] = 'Only unmatched M-Pesa credit lines can be sent to Unmatched.';

            return $summary;
        }

        if ((int) ($line->unassigned_payment_id ?? 0) > 0) {
            $summary['skipped']++;
            $summary['unassigned_payment_id'] = (int) $line->unassigned_payment_id;

            return $summary;
        }

        $reference = strtoupper(trim((string) $line->reference));
        if ($reference === '' || (float) $line->amount <= 0) {
            $summary['skipped']++;
            $summary['errors'][] = 'Line has no usable M-Pesa reference or amount.';

            return $summary;
        }

        try {
            if ($this->payments->transactionExists($reference)) {
                $existingId = \App\Models\UnassignedPayment::query()
                    ->where('transaction_id', $reference)
                    ->value('id');
                if ($existingId) {
                    $line->update([
                        'match_status' => PmBankStatementLine::MATCH_MATCHED,
                        'matched_type' => 'unassigned',
                        'unassigned_payment_id' => (int) $existingId,
                    ]);
                    $summary['unassigned_payment_id'] = (int) $existingId;
                    $summary['recovered']++;

                    return $summary;
                }
                $summary['skipped']++;
                $summary['errors'][] = 'A payment with this M-Pesa code already exists.';

                return $summary;
            }

            $accountRef = null;
            if (is_string($line->narration) && preg_match('/Acc\s+([A-Za-z0-9\-\/]+)/i', $line->narration, $m) === 1) {
                $accountRef = $m[1];
            }

            $this->payments->storeUnmatched([
                'transaction_id' => $reference,
                'amount' => (float) $line->amount,
                'account_number' => $accountRef,
                'phone' => $line->phone,
                'reference' => $reference,
                'transaction_date' => $line->txn_date ?? now(),
                'raw_payload' => [
                    'source' => 'bank_statement_recovery',
                    'pm_bank_statement_id' => (int) $line->pm_bank_statement_id,
                    'pm_bank_statement_line_id' => (int) $line->id,
                    'counterparty' => $line->counterparty,
                    'narration' => $line->narration,
                ],
            ], 'Recovered from uploaded bank/M-Pesa statement — assign tenant to settle.', [
                'payment_method' => 'statement_import',
                'agent_user_id' => $agentUserId,
            ]);

            $unassignedId = \App\Models\UnassignedPayment::query()
                ->where('transaction_id', $reference)
                ->value('id');

            $line->update([
                'match_status' => PmBankStatementLine::MATCH_MATCHED,
                'matched_type' => 'unassigned',
                'unassigned_payment_id' => $unassignedId ? (int) $unassignedId : null,
            ]);

            $summary['recovered']++;
            $summary['unassigned_payment_id'] = $unassignedId ? (int) $unassignedId : null;
        } catch (\Throwable $e) {
            $summary['errors'][] = $reference.': '.$e->getMessage();
        }

        return $summary;
    }

    /**
     * @return array{recovered:int, skipped:int, errors:list<string>}
     */
    public function recoverStatement(PmBankStatement $statement, int $agentUserId): array
    {
        $summary = ['recovered' => 0, 'skipped' => 0, 'errors' => []];

        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasTable('payments')) {
            $summary['errors'][] = 'Unassigned payments tables are missing.';

            return $summary;
        }

        $lines = PmBankStatementLine::query()
            ->where('pm_bank_statement_id', $statement->id)
            ->where('direction', 'credit')
            ->where('match_status', PmBankStatementLine::MATCH_UNMATCHED)
            ->where('line_type', CoopBankAccountStatementParser::TYPE_MPESA)
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $result = $this->recoverLine($line, $agentUserId);
            $summary['recovered'] += (int) ($result['recovered'] ?? 0);
            $summary['skipped'] += (int) ($result['skipped'] ?? 0);
            $summary['errors'] = array_merge($summary['errors'], $result['errors'] ?? []);
        }

        return $summary;
    }
}
