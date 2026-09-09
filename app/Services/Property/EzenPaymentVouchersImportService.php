<?php

namespace App\Services\Property;

use App\Models\PmAccountingEntry;
use App\Models\PmEzenPaymentVoucher;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLandlordPayout;
use App\Models\PmLandlordPayoutItem;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class EzenPaymentVouchersImportService
{
    public function __construct(
        private readonly EzenPaymentVoucherListingParser $parser,
        private readonly PassionPropertyCodeResolver $codes,
    ) {}

    /**
     * @return array{
     *     parsed:int,
     *     register_upserted:int,
     *     remittances:int,
     *     expenses:int,
     *     commissions:int,
     *     taxes:int,
     *     skipped_existing:int,
     *     skipped_unmatched:int,
     *     skipped_zero:int,
     *     skipped_filtered:int,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    public function importFromPath(
        string $path,
        int $agentUserId,
        User $actor,
        bool $dryRun = false,
        bool $registerOnly = false,
        bool $remittancesOnly = false,
        bool $expensesOnly = false,
        bool $postGl = false,
        ?string $propertyCode = null,
        ?string $category = null,
        ?int $limit = null,
    ): array {
        $rows = $this->parser->parsePath($path);
        $summary = $this->emptySummary();
        $summary['parsed'] = count($rows);

        $propertyFilter = $propertyCode !== null && $propertyCode !== ''
            ? strtoupper(trim($propertyCode))
            : null;
        $categoryFilter = $category !== null && $category !== ''
            ? strtolower(trim($category))
            : null;

        $process = function () use (
            $rows,
            $agentUserId,
            $actor,
            $dryRun,
            $registerOnly,
            $remittancesOnly,
            $expensesOnly,
            $postGl,
            $propertyFilter,
            $categoryFilter,
            $limit,
            &$summary
        ): void {
            $processed = 0;
            foreach ($rows as $row) {
                if ($limit !== null && $processed >= $limit) {
                    break;
                }

                $processed++;
                $amount = (float) ($row['amount'] ?? 0);
                if ($amount <= 0) {
                    $summary['skipped_zero']++;
                    continue;
                }

                if ($propertyFilter !== null) {
                    $rowCode = strtoupper((string) ($row['property_code'] ?? ''));
                    if ($rowCode !== $propertyFilter && ! str_starts_with($rowCode, $propertyFilter)) {
                        $summary['skipped_filtered']++;
                        continue;
                    }
                }

                $rowCategory = (string) ($row['category'] ?? EzenPaymentVoucherListingParser::CATEGORY_EXPENSE);
                if ($categoryFilter !== null && $rowCategory !== $categoryFilter) {
                    $summary['skipped_filtered']++;
                    continue;
                }
                if ($remittancesOnly && $rowCategory !== EzenPaymentVoucherListingParser::CATEGORY_REMITTANCE) {
                    $summary['skipped_filtered']++;
                    continue;
                }
                if ($expensesOnly && $rowCategory === EzenPaymentVoucherListingParser::CATEGORY_REMITTANCE) {
                    $summary['skipped_filtered']++;
                    continue;
                }

                try {
                    $result = $this->importRow($row, $agentUserId, $actor, $dryRun, $registerOnly, $postGl);
                    $summary['register_upserted'] += $result['register'] ? 1 : 0;
                    $summary['remittances'] += $result['remittance'] ? 1 : 0;
                    $summary['expenses'] += $result['expense'] ? 1 : 0;
                    $summary['commissions'] += $result['commission'] ? 1 : 0;
                    $summary['taxes'] += $result['tax'] ? 1 : 0;
                    $summary['skipped_existing'] += $result['skipped_existing'] ? 1 : 0;
                    $summary['skipped_unmatched'] += $result['skipped_unmatched'] ? 1 : 0;
                    $summary['warnings'] = array_merge($summary['warnings'], $result['warnings']);
                } catch (RuntimeException $e) {
                    $summary['errors'][] = ($row['ezen_voucher_no'] ?? 'row').': '.$e->getMessage();
                }
            }
        };

        try {
            if ($dryRun) {
                $process();
            } else {
                DB::transaction(function () use ($process, &$summary): void {
                    $process();
                    if ($summary['errors'] !== []) {
                        throw new RuntimeException('Import aborted because of row errors.');
                    }
                });
            }
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'Import aborted because of row errors.') {
                throw $e;
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     register:bool,
     *     remittance:bool,
     *     expense:bool,
     *     commission:bool,
     *     tax:bool,
     *     skipped_existing:bool,
     *     skipped_unmatched:bool,
     *     warnings:list<string>
     * }
     */
    private function importRow(
        array $row,
        int $agentUserId,
        User $actor,
        bool $dryRun,
        bool $registerOnly,
        bool $postGl,
    ): array {
        $warnings = [];
        $voucherNo = (string) $row['ezen_voucher_no'];
        $category = (string) $row['category'];
        $resolved = $this->resolvePayee($row, $agentUserId);

        if ($dryRun) {
            $unmatchedRemittance = $category === EzenPaymentVoucherListingParser::CATEGORY_REMITTANCE
                && $resolved['landlord'] === null;

            return [
                'register' => true,
                'remittance' => $category === EzenPaymentVoucherListingParser::CATEGORY_REMITTANCE && ! $registerOnly && ! $unmatchedRemittance,
                'expense' => $category === EzenPaymentVoucherListingParser::CATEGORY_EXPENSE && ! $registerOnly,
                'commission' => $category === EzenPaymentVoucherListingParser::CATEGORY_COMMISSION && ! $registerOnly,
                'tax' => $category === EzenPaymentVoucherListingParser::CATEGORY_TAX && ! $registerOnly,
                'skipped_existing' => false,
                'skipped_unmatched' => $unmatchedRemittance && ! $registerOnly,
                'warnings' => $unmatchedRemittance && ! $registerOnly
                    ? [$voucherNo.': remittance payee not matched ('.($row['paid_to'] ?? '').')']
                    : [],
            ];
        }

        $existing = $this->findRegister($agentUserId, $voucherNo);
        if ($existing && ($existing->pm_landlord_payout_id || $existing->pm_accounting_entry_id)) {
            return [
                'register' => false,
                'remittance' => false,
                'expense' => false,
                'commission' => false,
                'tax' => false,
                'skipped_existing' => true,
                'skipped_unmatched' => false,
                'warnings' => [],
            ];
        }

        $linkStatus = PmEzenPaymentVoucher::LINK_IMPORTED;
        if ($category === EzenPaymentVoucherListingParser::CATEGORY_REMITTANCE && $resolved['landlord'] === null) {
            $linkStatus = PmEzenPaymentVoucher::LINK_UNMATCHED;
            $warnings[] = $voucherNo.': remittance payee not matched ('.($row['paid_to'] ?? '').')';
        }

        $register = $this->upsertRegister($existing, $row, $agentUserId, $resolved, $linkStatus);

        if ($registerOnly) {
            return [
                'register' => true,
                'remittance' => false,
                'expense' => false,
                'commission' => false,
                'tax' => false,
                'skipped_existing' => false,
                'skipped_unmatched' => $linkStatus === PmEzenPaymentVoucher::LINK_UNMATCHED,
                'warnings' => $warnings,
            ];
        }

        if ($category === EzenPaymentVoucherListingParser::CATEGORY_REMITTANCE) {
            if ($resolved['landlord'] === null) {
                return [
                    'register' => true,
                    'remittance' => false,
                    'expense' => false,
                    'commission' => false,
                    'tax' => false,
                    'skipped_existing' => false,
                    'skipped_unmatched' => true,
                    'warnings' => $warnings,
                ];
            }

            if ($this->alreadyPostedRemittance($voucherNo, $resolved['landlord'], $resolved['property'])) {
                $register->update(['link_status' => PmEzenPaymentVoucher::LINK_SKIPPED]);

                return [
                    'register' => true,
                    'remittance' => false,
                    'expense' => false,
                    'commission' => false,
                    'tax' => false,
                    'skipped_existing' => true,
                    'skipped_unmatched' => false,
                    'warnings' => [$voucherNo.': landlord ledger already has this voucher — skipped to avoid double-counting'],
                ];
            }

            $payout = $this->postRemittance($row, $actor, $resolved, $postGl);
            $register->update([
                'pm_landlord_payout_id' => $payout->id,
                'landlord_id' => $resolved['landlord']->id,
                'property_id' => $resolved['property']?->id,
                'link_status' => PmEzenPaymentVoucher::LINK_REMITTANCE,
            ]);

            return [
                'register' => true,
                'remittance' => true,
                'expense' => false,
                'commission' => false,
                'tax' => false,
                'skipped_existing' => false,
                'skipped_unmatched' => false,
                'warnings' => $warnings,
            ];
        }

        $entry = $this->postExpense($row, $actor, $resolved);
        if ($entry === null) {
            $register->update(['link_status' => PmEzenPaymentVoucher::LINK_SKIPPED]);

            return [
                'register' => true,
                'remittance' => false,
                'expense' => false,
                'commission' => false,
                'tax' => false,
                'skipped_existing' => true,
                'skipped_unmatched' => false,
                'warnings' => [$voucherNo.': expense already posted — skipped'],
            ];
        }

        $register->update([
            'pm_accounting_entry_id' => $entry->id,
            'property_id' => $resolved['property']?->id,
            'link_status' => PmEzenPaymentVoucher::LINK_EXPENSE,
        ]);

        return [
            'register' => true,
            'remittance' => false,
            'expense' => $category === EzenPaymentVoucherListingParser::CATEGORY_EXPENSE,
            'commission' => $category === EzenPaymentVoucherListingParser::CATEGORY_COMMISSION,
            'tax' => $category === EzenPaymentVoucherListingParser::CATEGORY_TAX,
            'skipped_existing' => false,
            'skipped_unmatched' => false,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{property:?Property, landlord:?User, code:string, name:string}  $resolved
     */
    private function upsertRegister(
        ?PmEzenPaymentVoucher $existing,
        array $row,
        int $agentUserId,
        array $resolved,
        string $linkStatus,
    ): PmEzenPaymentVoucher {
        $payload = [
            'agent_user_id' => $agentUserId,
            'ezen_voucher_no' => (string) $row['ezen_voucher_no'],
            'method' => (string) ($row['method'] ?? ''),
            'ref_no' => (string) ($row['ref_no'] ?? ''),
            'txn_date' => (string) $row['txn_date'],
            'particulars' => (string) ($row['particulars'] ?? ''),
            'paid_from' => (string) ($row['paid_from'] ?? ''),
            'paid_to' => (string) ($row['paid_to'] ?? ''),
            'payee_name' => $resolved['name'] !== '' ? $resolved['name'] : (string) ($row['payee_name'] ?? ''),
            'property_code' => $resolved['code'] !== '' ? $resolved['code'] : (string) ($row['property_code'] ?? ''),
            'category' => (string) $row['category'],
            'period_month' => $row['period_month'] ?? null,
            'amount' => (float) $row['amount'],
            'recorded_by' => (string) ($row['recorded_by'] ?? ''),
            'property_id' => $resolved['property']?->id,
            'landlord_id' => $resolved['landlord']?->id,
            'link_status' => $linkStatus,
        ];

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return PmEzenPaymentVoucher::query()->create($payload);
    }

    private function findRegister(int $agentUserId, string $voucherNo): ?PmEzenPaymentVoucher
    {
        if (! Schema::hasTable('pm_ezen_payment_vouchers')) {
            throw new RuntimeException('Run migrations first (pm_ezen_payment_vouchers is missing).');
        }

        return PmEzenPaymentVoucher::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->where('ezen_voucher_no', $voucherNo)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{property:?Property, landlord:?User, code:string, name:string}
     */
    private function resolvePayee(array $row, int $agentUserId): array
    {
        $code = strtoupper(trim((string) ($row['property_code'] ?? '')));
        $name = trim((string) ($row['payee_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['paid_to'] ?? ''));
        }

        $property = null;
        if ($code !== '') {
            $matches = $this->codes->resolveMany($code);
            $property = $matches->first(function (Property $candidate) use ($agentUserId): bool {
                return ! Schema::hasColumn('properties', 'agent_user_id')
                    || (int) $candidate->agent_user_id === $agentUserId;
            }) ?? $matches->first();
        }

        if ($property === null && $name !== '') {
            $property = $this->codes->resolveByName($name);
        }

        $landlord = $this->resolveLandlord($property, $name, $agentUserId, $code !== '');

        if ($property === null && $landlord) {
            $propertyIds = DB::table('property_landlord')->where('user_id', $landlord->id)->pluck('property_id');
            if ($propertyIds->count() === 1) {
                $property = Property::query()->withoutGlobalScopes()->find((int) $propertyIds->first());
            }
        }

        return [
            'property' => $property,
            'landlord' => $landlord,
            'code' => $code !== '' ? $code : (string) ($property?->code ?? ''),
            'name' => $name,
        ];
    }

    private function resolveLandlord(?Property $property, string $payeeName, int $agentUserId, bool $payeeIsPropertyCode): ?User
    {
        if ($property) {
            $property->loadMissing('landlords');
            $landlords = $property->landlords;
            if ($landlords->count() === 1) {
                return $landlords->first();
            }
            if ($landlords->isNotEmpty() && $payeeIsPropertyCode) {
                return $this->bestNameMatch($landlords->all(), $payeeName) ?? $landlords->first();
            }
            if ($landlords->isNotEmpty() && $payeeName !== '') {
                $matched = $this->bestNameMatch($landlords->all(), $payeeName);
                if ($matched) {
                    return $matched;
                }
            }
        }

        if ($payeeName === '') {
            return null;
        }

        $candidates = User::query()
            ->where('property_portal_role', 'landlord')
            ->when(
                Schema::hasColumn('users', 'agent_user_id'),
                fn ($query) => $query->where(function ($inner) use ($agentUserId): void {
                    $inner->where('agent_user_id', $agentUserId)->orWhereNull('agent_user_id');
                })
            )
            ->get(['id', 'name']);

        return $this->bestNameMatch($candidates->all(), $payeeName);
    }

    /**
     * @param  list<User>  $candidates
     */
    private function bestNameMatch(array $candidates, string $needle): ?User
    {
        $best = null;
        $bestScore = 0;
        foreach ($candidates as $candidate) {
            $score = $this->codes->scoreNameMatch($needle, (string) $candidate->name);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $bestScore >= 20 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{property:?Property, landlord:User, code:string, name:string}  $resolved
     */
    private function postRemittance(array $row, User $actor, array $resolved, bool $postGl): PmLandlordPayout
    {
        $voucherNo = (string) $row['ezen_voucher_no'];
        $amount = (float) $row['amount'];
        $txnDate = Carbon::parse((string) $row['txn_date'])->startOfDay();
        $description = $this->voucherDescription($row);

        $payout = PmLandlordPayout::query()->create([
            'agent_user_id' => (int) $actor->id,
            'total_amount' => $amount,
            'status' => 'paid',
            'created_by' => (int) $actor->id,
            'approved_by' => (int) $actor->id,
            'paid_at' => $txnDate->copy()->setTime(12, 0),
        ]);

        PmLandlordPayoutItem::query()->create([
            'payout_id' => (int) $payout->id,
            'landlord_id' => (int) $resolved['landlord']->id,
            'property_id' => $resolved['property']?->id,
            'amount' => $amount,
            'line_type' => LandlordSettlementService::LINE_REMITTANCE,
            'description' => $description,
            'period_month' => $row['period_month'] ?? $txnDate->format('Y-m'),
            'payment_reference' => (string) ($row['ref_no'] ?? ''),
        ]);

        LandlordLedger::post(
            $resolved['landlord'],
            PmLandlordLedgerEntry::DIRECTION_DEBIT,
            $amount,
            $description,
            $resolved['property'],
            'ezen_payment_voucher',
            $this->voucherNumericId($voucherNo),
            $txnDate,
            (int) $actor->id,
        );

        if ($postGl) {
            app(PropertyTrustAccountingService::class)->postLandlordPayout($payout->fresh(['items']), (int) $actor->id);
        }

        return $payout->fresh(['items']) ?? $payout;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{property:?Property, landlord:?User, code:string, name:string}  $resolved
     */
    private function postExpense(array $row, User $actor, array $resolved): ?PmAccountingEntry
    {
        $sourceKey = $this->expenseSourceKey((string) $row['ezen_voucher_no']);
        $already = PmAccountingEntry::query()
            ->withoutGlobalScopes()
            ->where('source_key', $sourceKey)
            ->first();
        if ($already) {
            return null;
        }

        $category = (string) $row['category'];
        $accountName = match ($category) {
            EzenPaymentVoucherListingParser::CATEGORY_COMMISSION => 'Commission Expense',
            EzenPaymentVoucherListingParser::CATEGORY_TAX => 'Tax / statutory',
            default => $this->expenseAccountName($row),
        };

        return PmAccountingEntry::query()->create([
            'property_id' => $resolved['property']?->id,
            'recorded_by_user_id' => (int) $actor->id,
            'entry_date' => (string) $row['txn_date'],
            'account_name' => $accountName,
            'category' => PmAccountingEntry::CATEGORY_EXPENSE,
            'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            'amount' => (float) $row['amount'],
            'reference' => (string) $row['ezen_voucher_no'],
            'description' => $this->voucherDescription($row),
            'source_key' => $sourceKey,
        ]);
    }

    private function alreadyPostedRemittance(string $voucherNo, User $landlord, ?Property $property): bool
    {
        $marker = '[EZEN '.$voucherNo.']';
        $query = PmLandlordLedgerEntry::query()
            ->withoutGlobalScopes()
            ->where('user_id', $landlord->id)
            ->where('description', 'like', $marker.'%');
        if ($property) {
            $query->where('property_id', $property->id);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function voucherDescription(array $row): string
    {
        $parts = [
            '[EZEN '.(string) $row['ezen_voucher_no'].']',
            trim((string) ($row['particulars'] ?? '')),
        ];
        $paidTo = trim((string) ($row['paid_to'] ?? ''));
        if ($paidTo !== '') {
            $parts[] = 'to '.$paidTo;
        }
        $method = trim((string) ($row['method'] ?? ''));
        $ref = trim((string) ($row['ref_no'] ?? ''));
        if ($method !== '' || $ref !== '') {
            $parts[] = trim('via '.$method.($ref !== '' ? ' ref '.$ref : ''));
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))) ?? '');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function expenseAccountName(array $row): string
    {
        $particulars = trim((string) ($row['particulars'] ?? ''));
        if ($particulars !== '') {
            return Str::limit($particulars, 80, '');
        }
        $payee = trim((string) ($row['paid_to'] ?? ''));

        return $payee !== '' ? Str::limit($payee, 80, '') : 'Operating expense';
    }

    private function expenseSourceKey(string $voucherNo): string
    {
        return 'ezen_voucher:'.$voucherNo;
    }

    private function voucherNumericId(string $voucherNo): ?int
    {
        if (preg_match('/(\d+)/', $voucherNo, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * @return array{
     *     parsed:int,
     *     register_upserted:int,
     *     remittances:int,
     *     expenses:int,
     *     commissions:int,
     *     taxes:int,
     *     skipped_existing:int,
     *     skipped_unmatched:int,
     *     skipped_zero:int,
     *     skipped_filtered:int,
     *     warnings:list<string>,
     *     errors:list<string>
     * }
     */
    private function emptySummary(): array
    {
        return [
            'parsed' => 0,
            'register_upserted' => 0,
            'remittances' => 0,
            'expenses' => 0,
            'commissions' => 0,
            'taxes' => 0,
            'skipped_existing' => 0,
            'skipped_unmatched' => 0,
            'skipped_zero' => 0,
            'skipped_filtered' => 0,
            'warnings' => [],
            'errors' => [],
        ];
    }
}
