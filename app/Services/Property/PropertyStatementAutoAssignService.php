<?php

namespace App\Services\Property;

use App\Models\Payment;
use App\Models\PmBankStatement;
use App\Models\PmBankStatementLine;
use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\UnassignedPayment;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use Illuminate\Support\Facades\Schema;

/**
 * Posts a statement credit only when one tenant matches both the payer phone
 * and the payer name, and that M-Pesa code is not already a receipt.
 */
final class PropertyStatementAutoAssignService
{
    public function __construct(
        private readonly EquityPaymentRepository $payments,
        private readonly PaymentAuditLogRepository $auditLogs,
    ) {}

    /**
     * @return array{posted:int, linked:int, skipped:int, errors:list<string>}
     */
    public function assignStatement(PmBankStatement $statement, int $actorUserId): array
    {
        set_time_limit(0);
        $summary = ['posted' => 0, 'linked' => 0, 'skipped' => 0, 'errors' => []];
        $tenantsByPhone = $this->tenantsByPhone((int) $statement->agent_user_id);
        /** @var array<string, int> $postedByReference */
        $postedByReference = [];

        $lines = PmBankStatementLine::query()
            ->where('pm_bank_statement_id', $statement->id)
            ->where('direction', 'credit')
            ->where('line_type', CoopBankAccountStatementParser::TYPE_MPESA)
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            if ($line->isAllocatedToTenant()) {
                continue;
            }

            try {
                $outcome = $this->assignLine($line, $actorUserId, $tenantsByPhone, $postedByReference);
            } catch (\Throwable $e) {
                $summary['errors'][] = trim((string) $line->reference).': '.$e->getMessage();
                $summary['skipped']++;
                continue;
            }

            if ($outcome === 'posted') {
                $summary['posted']++;
            } elseif ($outcome === 'linked') {
                $summary['linked']++;
            } else {
                $summary['skipped']++;
            }
        }

        $summary['errors'] = array_slice($summary['errors'], 0, 8);

        return $summary;
    }

    /**
     * @param  array<string, list<PmTenant>>  $tenantsByPhone
     * @param  array<string, int>  $postedByReference
     */
    private function assignLine(
        PmBankStatementLine $line,
        int $actorUserId,
        array $tenantsByPhone,
        array &$postedByReference,
    ): string {
        $reference = strtoupper(trim((string) $line->reference));
        if ($reference === '') {
            return 'skipped';
        }

        $candidates = $this->referenceCandidates($reference);
        foreach ($candidates as $candidate) {
            $knownId = (int) ($postedByReference[$candidate] ?? 0);
            if ($knownId > 0) {
                $this->linkPayment($line, $knownId);

                return 'linked';
            }
        }

        $existingPaymentId = (int) ($line->pm_payment_id ?? 0);
        if ($existingPaymentId > 0) {
            $existing = PmPayment::query()->find($existingPaymentId);
            if ($existing && (int) $existing->pm_tenant_id > 0) {
                $this->linkPayment($line, (int) $existing->id);
                $this->rememberReference($postedByReference, $candidates, (int) $existing->id);

                return 'linked';
            }
        }

        $posted = $this->findPostedPayment($candidates);
        if ($posted) {
            $this->linkPayment($line, (int) $posted->id);
            $this->rememberReference($postedByReference, $candidates, (int) $posted->id);

            return 'linked';
        }

        $receipt = $this->findPostedReceipt((int) $line->agent_user_id, $candidates);
        if ($receipt) {
            $line->update([
                'match_status' => PmBankStatementLine::MATCH_MATCHED,
                'matched_type' => 'ezen_receipt',
                'pm_payment_id' => $receipt->pm_payment_id ? (int) $receipt->pm_payment_id : null,
                'pm_ezen_receipt_register_id' => (int) $receipt->id,
                'unassigned_payment_id' => null,
            ]);

            return 'linked';
        }

        if ($this->ledgerAlreadyPosted($candidates)) {
            return 'skipped';
        }

        $tenant = $this->uniqueTenant($line, $tenantsByPhone);
        if (! $tenant) {
            return 'skipped';
        }

        $unassigned = $this->findUnassigned($candidates);
        if ($unassigned === false) {
            return 'skipped';
        }

        if ($unassigned instanceof UnassignedPayment) {
            if (abs(round((float) $unassigned->amount, 2) - round((float) $line->amount, 2)) > 0.009) {
                return 'skipped';
            }

            $payment = $this->postUnassigned($line, $unassigned, $tenant, $actorUserId);
        } else {
            $payment = $this->postFresh($line, $reference, $tenant, $actorUserId);
        }

        $pmPaymentId = (int) ($payment->pm_payment_id ?? 0);
        if ($pmPaymentId <= 0) {
            return 'skipped';
        }

        $this->linkPayment($line, $pmPaymentId);
        $this->rememberReference($postedByReference, $candidates, $pmPaymentId);

        $this->auditLogs->decision('success', [
            'stage' => 'statement_auto_assign',
            'decision' => 'assigned',
            'transaction_id' => $reference,
            'tenant_id' => (int) $tenant->id,
            'payment_id' => (int) $payment->id,
            'pm_payment_id' => $pmPaymentId,
            'pm_bank_statement_line_id' => (int) $line->id,
            'matched_by' => 'phone_and_name',
        ], 'statement_auto_assign');

        return 'posted';
    }

    private function postUnassigned(
        PmBankStatementLine $line,
        UnassignedPayment $unassigned,
        PmTenant $tenant,
        int $actorUserId,
    ): Payment {
        Payment::query()
            ->where('transaction_id', (string) $unassigned->transaction_id)
            ->where('status', 'unmatched')
            ->delete();

        $payment = $this->payments->storeMatched([
            'transaction_id' => (string) $unassigned->transaction_id,
            'amount' => (float) $unassigned->amount,
            'account_number' => (string) ($unassigned->account_number ?? ''),
            'reference' => '',
            'phone' => (string) ($unassigned->phone ?: $line->phone),
            'transaction_date' => $line->txn_date ?? $unassigned->created_at ?? now(),
            'raw_payload' => [
                'auto_assigned' => true,
                'matched_by' => 'phone_and_name',
                'unassigned_payment_id' => (int) $unassigned->id,
                'pm_bank_statement_line_id' => (int) $line->id,
                'counterparty' => (string) $line->counterparty,
            ],
        ], (int) $tenant->id, 'phone_and_name', [
            'payment_method' => (string) ($unassigned->payment_method ?: 'statement_import'),
            'channel' => 'bank_statement',
            'source' => 'statement_import',
            'provider' => 'mpesa',
            'message' => 'Auto-posted from the bank statement. Phone and name matched one tenant, and this M-Pesa code had no receipt.',
            'agent_user_id' => $actorUserId,
            'skip_notification' => true,
        ]);

        $unassigned->delete();

        return $payment;
    }

    private function postFresh(PmBankStatementLine $line, string $reference, PmTenant $tenant, int $actorUserId): Payment
    {
        Payment::query()
            ->where('transaction_id', $reference)
            ->where('status', 'unmatched')
            ->delete();

        return $this->payments->storeMatched([
            'transaction_id' => $reference,
            'amount' => (float) $line->amount,
            'account_number' => '',
            'reference' => '',
            'phone' => (string) $line->phone,
            'transaction_date' => $line->txn_date ?? now(),
            'raw_payload' => [
                'auto_assigned' => true,
                'matched_by' => 'phone_and_name',
                'pm_bank_statement_line_id' => (int) $line->id,
                'counterparty' => (string) $line->counterparty,
            ],
        ], (int) $tenant->id, 'phone_and_name', [
            'payment_method' => 'statement_import',
            'channel' => 'bank_statement',
            'source' => 'statement_import',
            'provider' => 'mpesa',
            'message' => 'Auto-posted from the bank statement. Phone and name matched one tenant, and this M-Pesa code had no receipt.',
            'agent_user_id' => $actorUserId,
            'skip_notification' => true,
        ]);
    }

    private function linkPayment(PmBankStatementLine $line, int $pmPaymentId): void
    {
        $line->update([
            'match_status' => PmBankStatementLine::MATCH_MATCHED,
            'matched_type' => 'payment',
            'pm_payment_id' => $pmPaymentId,
            'unassigned_payment_id' => null,
        ]);
    }

    /**
     * @param  list<string>  $candidates
     */
    private function findPostedPayment(array $candidates): ?PmPayment
    {
        return PmPayment::query()
            ->where('pm_tenant_id', '>', 0)
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhereRaw('UPPER(TRIM(external_ref)) = ?', [$candidate]);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<string>  $candidates
     */
    private function findPostedReceipt(int $agentUserId, array $candidates): ?PmEzenReceiptRegister
    {
        if (! Schema::hasTable('pm_ezen_receipt_register') || $agentUserId <= 0) {
            return null;
        }

        return PmEzenReceiptRegister::query()
            ->where('agent_user_id', $agentUserId)
            ->where(function ($query): void {
                $query->where('pm_tenant_id', '>', 0)
                    ->orWhere(function ($name): void {
                        $name->whereNotNull('register_tenant_name')
                            ->where('register_tenant_name', '!=', '');
                    });
            })
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhereRaw('UPPER(TRIM(ref_no)) = ?', [$candidate]);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<string>  $candidates
     */
    private function ledgerAlreadyPosted(array $candidates): bool
    {
        return Payment::query()
            ->where('status', '!=', 'unmatched')
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhereRaw('UPPER(TRIM(transaction_id)) = ?', [$candidate]);
                }
            })
            ->exists();
    }

    /**
     * @param  list<string>  $candidates
     * @return UnassignedPayment|false|null null when none, false when more than one
     */
    private function findUnassigned(array $candidates): UnassignedPayment|false|null
    {
        if (! Schema::hasTable('unassigned_payments')) {
            return null;
        }

        $rows = UnassignedPayment::query()
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhereRaw('UPPER(TRIM(transaction_id)) = ?', [$candidate]);
                }
            })
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        if ($rows->count() > 1) {
            return false;
        }

        return $rows->first();
    }

    /**
     * @param  array<string, list<PmTenant>>  $tenantsByPhone
     */
    private function uniqueTenant(PmBankStatementLine $line, array $tenantsByPhone): ?PmTenant
    {
        $phones = $this->phoneCandidates($this->normalizePhone((string) $line->phone));
        $phones = array_values(array_filter($phones, static fn (string $phone): bool => strlen($phone) >= 10));
        if ($phones === []) {
            return null;
        }

        $matches = [];
        foreach ($phones as $phone) {
            foreach ($tenantsByPhone[$phone] ?? [] as $tenant) {
                $matches[(int) $tenant->id] = $tenant;
            }
        }

        $named = [];
        foreach ($matches as $tenant) {
            if ($this->namesAgree((string) $line->counterparty, $tenant)) {
                $named[(int) $tenant->id] = $tenant;
            }
        }

        if (count($named) !== 1) {
            return null;
        }

        return array_values($named)[0];
    }

    private function namesAgree(string $bankName, PmTenant $tenant): bool
    {
        $bankTokens = $this->nameTokens($bankName);
        if ($bankTokens === []) {
            return false;
        }

        $tenantTokens = $this->nameTokens(trim($tenant->name.' '.(string) ($tenant->other_names ?? '')));
        foreach ($bankTokens as $token) {
            if (! in_array($token, $tenantTokens, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $value): array
    {
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z ]+/', ' ', $value) ?? '';
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $stop = ['MR', 'MRS', 'MS', 'MISS', 'DR', 'MPESA', 'C2B'];
        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) < 3 || in_array($part, $stop, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<string, list<PmTenant>>
     */
    private function tenantsByPhone(int $agentUserId): array
    {
        $columns = ['id', 'name', 'phone'];
        if (Schema::hasColumn('pm_tenants', 'other_names')) {
            $columns[] = 'other_names';
        }

        $query = PmTenant::query();
        if ($agentUserId > 0 && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }

        $index = [];
        foreach ($query->get($columns) as $tenant) {
            foreach ($this->phoneCandidates($this->normalizePhone((string) $tenant->phone)) as $phone) {
                if (strlen($phone) < 10) {
                    continue;
                }
                $index[$phone][] = $tenant;
            }
        }

        return $index;
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }
        if ((str_starts_with($digits, '7') || str_starts_with($digits, '1')) && strlen($digits) === 9) {
            return '254'.$digits;
        }

        return $digits;
    }

    /**
     * @return list<string>
     */
    private function phoneCandidates(string $normalized): array
    {
        $clean = preg_replace('/\D+/', '', $normalized) ?? '';
        if ($clean === '') {
            return [];
        }

        $candidates = [$clean];
        if (str_starts_with($clean, '254') && strlen($clean) >= 12) {
            $candidates[] = '0'.substr($clean, 3);
        } elseif (str_starts_with($clean, '0') && strlen($clean) >= 10) {
            $candidates[] = '254'.substr($clean, 1);
        }

        return array_values(array_unique($candidates));
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

    /**
     * @param  array<string, int>  $postedByReference
     * @param  list<string>  $candidates
     */
    private function rememberReference(array &$postedByReference, array $candidates, int $pmPaymentId): void
    {
        foreach ($candidates as $candidate) {
            $postedByReference[$candidate] = $pmPaymentId;
        }
    }
}
