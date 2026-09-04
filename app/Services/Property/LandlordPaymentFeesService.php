<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLandlordPayout;
use App\Models\PmLandlordPayoutItem;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LandlordPaymentFeesService
{
    public function __construct(
        private readonly AgentCommissionService $commission,
        private readonly LandlordSettlementService $settlements,
        private readonly PropertyTrustAccountingService $trustAccounting,
    ) {}

    /**
     * @param  array{property_id?: int, landlord_id?: int, month?: string, status?: string, search?: string, show_zero?: bool}  $filters
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, hint?: string}>,
     *     period_month: string,
     *     period_label: string
     * }
     */
    public function buildGrid(array $filters): array
    {
        $month = trim((string) ($filters['month'] ?? ''));
        if ($month === '') {
            $month = now()->format('Y-m');
        }

        $periodStart = Carbon::createFromFormat('Y-m', $month)?->startOfMonth() ?? now()->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $periodMonth = $periodStart->format('Y-m');

        $propertyId = (int) ($filters['property_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));
        $statusFilter = strtolower(trim((string) ($filters['status'] ?? '')));
        $showZero = (bool) ($filters['show_zero'] ?? false);

        $links = $this->commission->landlordPropertyLinks(
            $landlordId > 0 ? $landlordId : null,
            $propertyId > 0 ? $propertyId : null,
            $search !== '' ? $search : null,
        );

        if ($links->isEmpty()) {
            return [
                'rows' => [],
                'stats' => $this->emptyStats(),
                'period_month' => $periodMonth,
                'period_label' => $periodStart->format('F Y'),
            ];
        }

        $propertyIds = $links->pluck('property_id')->unique()->values()->all();
        $propertyCodes = DB::table('properties')->whereIn('id', $propertyIds)->pluck('code', 'id');
        $collectedByProperty = $this->commission->collectedByProperty($periodStart, $periodEnd);
        $ledgerBefore = $this->ledgerNetBefore($propertyIds, $periodStart);
        $ledgerCredits = $this->ledgerDirectionSum($propertyIds, PmLandlordLedgerEntry::DIRECTION_CREDIT, $periodStart, $periodEnd);
        $ledgerDebits = $this->ledgerDirectionSum($propertyIds, PmLandlordLedgerEntry::DIRECTION_DEBIT, $periodStart, $periodEnd);
        $payoutItems = $this->payoutItemsForPeriod($propertyIds, $periodMonth);
        $feesPostedKeys = $this->postedFeeKeys($propertyIds, $periodMonth);
        $openAdvanceTotals = app(LandlordAdvanceService::class)->openAdvanceTotalsByKey($propertyIds);
        $advanceService = app(LandlordAdvanceService::class);

        $rows = [];
        foreach ($links as $link) {
            $pid = (int) $link->property_id;
            $lid = (int) $link->user_id;
            $key = $pid.'|'.$lid;
            $agreedPayDay = $link->agreed_pay_day !== null ? (int) $link->agreed_pay_day : null;
            $nextAgreedPayDate = $advanceService->nextAgreedPayDate($agreedPayDay, $periodEnd);
            $ownershipFactor = (float) $link->ownership_percent / 100;
            $grossCollected = round(($collectedByProperty[$pid] ?? 0.0) * $ownershipFactor, 2);
            $commissionPct = $this->commission->commissionPercentForProperty($pid);
            $managementFee = round($grossCollected * ($commissionPct / 100), 2);
            $balanceBf = $ledgerBefore[$key] ?? 0.0;
            $periodCredits = $ledgerCredits[$key] ?? 0.0;
            $periodDebits = $ledgerDebits[$key] ?? 0.0;
            $closingBalance = round($balanceBf + $periodCredits - $periodDebits, 2);
            $amountPayable = max(0.0, $closingBalance);

            /** @var PmLandlordPayoutItem|null $payoutItem */
            $payoutItem = ($payoutItems[$key] ?? collect())->sortByDesc(fn (PmLandlordPayoutItem $item) => (int) $item->payout_id)->first();
            $payout = $payoutItem?->payout;
            $rowStatus = $this->resolveRowStatus($closingBalance, $amountPayable, $payout);
            $feesPosted = $feesPostedKeys[$key] ?? false;

            if (! $this->matchesStatusFilter($rowStatus, $statusFilter)) {
                continue;
            }

            if ($statusFilter === 'fees_posted' && ! $feesPosted) {
                continue;
            }

            if ($statusFilter === 'fees_unposted' && $feesPosted) {
                continue;
            }

            if (! $showZero && $grossCollected <= 0 && abs($closingBalance) < 0.01 && ! $payout) {
                continue;
            }

            $rows[] = [
                'property_id' => $pid,
                'landlord_id' => $lid,
                'property_code' => trim((string) ($propertyCodes[$pid] ?? '')),
                'property_name' => (string) $link->property_name,
                'landlord_name' => (string) $link->owner_name,
                'statement_type' => 'Final',
                'on' => $grossCollected > 0 ? 'RENT' : '—',
                'date_prepared' => $payout?->created_at?->format('d/m/Y') ?? $periodEnd->format('d/m/Y'),
                'period' => $periodStart->format('F/Y'),
                'period_month' => $periodMonth,
                'management_fee' => $managementFee,
                'management_fee_tax' => 0.0,
                'amount_payable' => $amountPayable,
                'closing_balance' => $closingBalance,
                'paid_posted' => $payout?->status === 'paid' ? (float) ($payoutItem?->amount ?? $payout->total_amount) : null,
                'paid_posted_on' => $payout?->paid_at?->format('d/m/Y'),
                'payout_id' => $payout?->id,
                'payout_status' => $payout?->status,
                'status' => $rowStatus,
                'collected' => $grossCollected,
                'commission_percent' => $commissionPct,
                'fees_posted' => $feesPosted,
                'agreed_pay_day' => $agreedPayDay,
                'agreed_pay_notes' => (string) ($link->agreed_pay_notes ?? ''),
                'next_agreed_pay_date' => $nextAgreedPayDate?->format('Y-m-d'),
                'next_agreed_pay_label' => $nextAgreedPayDate?->format('d/m/Y') ?? '—',
                'open_advance_total' => $openAdvanceTotals[$key] ?? 0.0,
            ];
        }

        usort($rows, static fn (array $a, array $b) => strcmp((string) $a['property_name'], (string) $b['property_name']));

        return [
            'rows' => $rows,
            'stats' => $this->buildStats($rows),
            'period_month' => $periodMonth,
            'period_label' => $periodStart->format('F Y'),
        ];
    }

    /**
     * @param  list<array{property_id: int, landlord_id: int}>  $selections
     * @return array{created: int, approved: int, paid: int, fees_posted: int, skipped: int, errors: list<string>}
     */
    public function processBatch(string $action, string $month, array $selections, User $actor): array
    {
        $periodStart = Carbon::createFromFormat('Y-m', $month)?->startOfMonth() ?? now()->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $summary = [
            'created' => 0,
            'approved' => 0,
            'paid' => 0,
            'fees_posted' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($selections as $selection) {
            $propertyId = (int) ($selection['property_id'] ?? 0);
            $landlordId = (int) ($selection['landlord_id'] ?? 0);
            if ($propertyId <= 0 || $landlordId <= 0) {
                $summary['skipped']++;

                continue;
            }

            try {
                match ($action) {
                    'create_draft' => $this->createDraftPayout($propertyId, $landlordId, $periodStart, $periodEnd, $actor, $summary),
                    'approve' => $this->approveRowPayout($propertyId, $landlordId, $month, $actor, $summary),
                    'pay_post' => $this->payPostRowPayout($propertyId, $landlordId, $month, $periodStart, $periodEnd, $actor, $summary),
                    'post_fees_only' => $this->postFeesOnly($propertyId, $landlordId, $month, $periodStart, $periodEnd, $actor, $summary),
                    default => throw new \InvalidArgumentException('Unknown batch action.'),
                };
            } catch (\Throwable $e) {
                $summary['errors'][] = "Property {$propertyId} / landlord {$landlordId}: ".$e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * @return list<array<int, string|float|null>>
     */
    public function exportRows(array $rows): array
    {
        return array_map(static fn (array $row) => [
            trim(($row['property_code'] !== '' ? '['.$row['property_code'].'] ' : '').($row['property_name'] ?? '')),
            (string) ($row['landlord_name'] ?? ''),
            (string) ($row['statement_type'] ?? 'Final'),
            (string) ($row['on'] ?? '—'),
            (string) ($row['date_prepared'] ?? '—'),
            (string) ($row['period'] ?? '—'),
            number_format((float) ($row['management_fee'] ?? 0), 2, '.', ''),
            '0.00',
            number_format((float) ($row['amount_payable'] ?? 0), 2, '.', ''),
            $row['paid_posted'] !== null ? number_format((float) $row['paid_posted'], 2, '.', '') : '',
            (string) ($row['paid_posted_on'] ?? ''),
            (string) ($row['next_agreed_pay_label'] ?? '—'),
            number_format((float) ($row['open_advance_total'] ?? 0), 2, '.', ''),
            ucfirst((string) ($row['status'] ?? '')),
            ! empty($row['fees_posted']) ? 'Yes' : 'No',
        ], $rows);
    }

    /** @return array<string, bool> */
    private function postedFeeKeys(array $propertyIds, string $periodMonth): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $keys = AccountingJournalBatch::query()
            ->where('source_type', 'landlord_settlement')
            ->where('event_type', 'period_management_fee')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->where('source_key', 'like', '%:'.$periodMonth)
            ->pluck('source_key');

        $map = [];
        foreach ($keys as $sourceKey) {
            if (! preg_match('/^landlord_fee:(\d+):(\d+):'.preg_quote($periodMonth, '/').'$/', (string) $sourceKey, $matches)) {
                continue;
            }

            $map[$matches[1].'|'.$matches[2]] = true;
        }

        return $map;
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array<string, float>
     */
    private function ledgerNetBefore(array $propertyIds, Carbon $before): array
    {
        return PmLandlordLedgerEntry::query()
            ->whereIn('property_id', $propertyIds)
            ->where('occurred_at', '<', $before)
            ->selectRaw("property_id, user_id, COALESCE(SUM(CASE WHEN direction = '".PmLandlordLedgerEntry::DIRECTION_CREDIT."' THEN amount ELSE -amount END), 0) as bal")
            ->groupBy('property_id', 'user_id')
            ->get()
            ->mapWithKeys(fn ($row) => [((int) $row->property_id).'|'.((int) $row->user_id) => round((float) $row->bal, 2)])
            ->all();
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array<string, float>
     */
    private function ledgerDirectionSum(array $propertyIds, string $direction, Carbon $start, Carbon $end): array
    {
        return PmLandlordLedgerEntry::query()
            ->whereIn('property_id', $propertyIds)
            ->where('direction', $direction)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('property_id, user_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('property_id', 'user_id')
            ->get()
            ->mapWithKeys(fn ($row) => [((int) $row->property_id).'|'.((int) $row->user_id) => round((float) $row->total, 2)])
            ->all();
    }

    /**
     * @param  list<int>  $propertyIds
     * @return array<string, Collection<int, PmLandlordPayoutItem>>
     */
    private function payoutItemsForPeriod(array $propertyIds, string $periodMonth): array
    {
        return PmLandlordPayoutItem::query()
            ->with('payout')
            ->whereIn('property_id', $propertyIds)
            ->where('period_month', $periodMonth)
            ->get()
            ->groupBy(fn (PmLandlordPayoutItem $item) => ((int) $item->property_id).'|'.((int) $item->landlord_id))
            ->all();
    }

    private function resolveRowStatus(float $closingBalance, float $amountPayable, ?PmLandlordPayout $payout): string
    {
        if ($payout?->status === 'paid') {
            return 'paid';
        }

        if ($payout?->status === 'approved') {
            return 'approved';
        }

        if ($payout?->status === 'draft') {
            return 'draft';
        }

        if ($closingBalance < -0.01) {
            return 'overdrawn';
        }

        if ($amountPayable > 0.01) {
            return 'due';
        }

        return 'settled';
    }

    private function matchesStatusFilter(string $rowStatus, string $filter): bool
    {
        if ($filter === '' || $filter === 'all') {
            return true;
        }

        return $rowStatus === $filter;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: string, hint?: string}>
     */
    private function buildStats(array $rows): array
    {
        $due = collect($rows)->where('status', 'due')->count();
        $draft = collect($rows)->where('status', 'draft')->count();
        $paid = collect($rows)->where('status', 'paid')->count();
        $feesPosted = collect($rows)->where('fees_posted', true)->count();
        $fees = collect($rows)->sum('management_fee');
        $payable = collect($rows)->whereIn('status', ['due', 'draft', 'approved'])->sum('amount_payable');

        return [
            ['label' => 'Rows', 'value' => (string) count($rows), 'hint' => 'Property × landlord for period'],
            ['label' => 'Due / draft', 'value' => (string) ($due + $draft), 'hint' => 'Awaiting payout action'],
            ['label' => 'Paid', 'value' => (string) $paid, 'hint' => 'Posted this period'],
            ['label' => 'Fees posted', 'value' => (string) $feesPosted, 'hint' => 'GL fee journals'],
            ['label' => 'Mgmt fees', 'value' => PropertyMoney::kes((float) $fees), 'hint' => 'Agency commission'],
            ['label' => 'Amt payable', 'value' => PropertyMoney::kes((float) $payable), 'hint' => 'Outstanding remittance'],
        ];
    }

    /**
     * @return list<array{label: string, value: string, hint?: string}>
     */
    private function emptyStats(): array
    {
        return [
            ['label' => 'Rows', 'value' => '0', 'hint' => 'No landlord links match filters'],
            ['label' => 'Due / draft', 'value' => '0', 'hint' => '—'],
            ['label' => 'Paid', 'value' => '0', 'hint' => '—'],
            ['label' => 'Fees posted', 'value' => '0', 'hint' => '—'],
            ['label' => 'Mgmt fees', 'value' => PropertyMoney::kes(0), 'hint' => '—'],
            ['label' => 'Amt payable', 'value' => PropertyMoney::kes(0), 'hint' => '—'],
        ];
    }

    /**
     * @param  array{created: int, approved: int, paid: int, skipped: int, errors: list<string>}  $summary
     */
    private function createDraftPayout(
        int $propertyId,
        int $landlordId,
        Carbon $periodStart,
        Carbon $periodEnd,
        User $actor,
        array &$summary,
    ): void {
        $existing = $this->findPayoutItem($propertyId, $landlordId, $periodStart->format('Y-m'));
        if ($existing?->payout) {
            $summary['skipped']++;

            return;
        }

        $settlement = $this->settlements->buildSettlement($propertyId, $landlordId, $periodStart, $periodEnd);
        if ((float) ($settlement['net_amount_due'] ?? 0) <= 0) {
            $summary['skipped']++;

            return;
        }

        $this->settlements->createPayoutFromSettlement($settlement, $actor);
        $summary['created']++;
    }

    /**
     * @param  array{created: int, approved: int, paid: int, skipped: int, errors: list<string>}  $summary
     */
    private function approveRowPayout(int $propertyId, int $landlordId, string $month, User $actor, array &$summary): void
    {
        $payout = $this->findPayoutItem($propertyId, $landlordId, $month)?->payout;
        if (! $payout) {
            $summary['skipped']++;

            return;
        }

        if ($payout->status !== 'draft') {
            $summary['skipped']++;

            return;
        }

        $this->settlements->approvePayout($payout, $actor);
        $summary['approved']++;
    }

    /**
     * @param  array{created: int, approved: int, paid: int, skipped: int, errors: list<string>}  $summary
     */
    private function payPostRowPayout(
        int $propertyId,
        int $landlordId,
        string $month,
        Carbon $periodStart,
        Carbon $periodEnd,
        User $actor,
        array &$summary,
    ): void {
        $item = $this->findPayoutItem($propertyId, $landlordId, $month);
        $payout = $item?->payout;

        if (! $payout) {
            $settlement = $this->settlements->buildSettlement($propertyId, $landlordId, $periodStart, $periodEnd);
            if ((float) ($settlement['net_amount_due'] ?? 0) <= 0) {
                $summary['skipped']++;

                return;
            }

            $payout = $this->settlements->createPayoutFromSettlement($settlement, $actor);
            $summary['created']++;
        }

        if ($payout->status === 'draft') {
            $this->settlements->approvePayout($payout, $actor);
            $summary['approved']++;
            $payout->refresh();
        }

        if ($payout->status === 'approved') {
            $this->settlements->markPayoutPaid($payout, $actor);
            $summary['paid']++;

            return;
        }

        if ($payout->status === 'paid') {
            $summary['skipped']++;

            return;
        }

        $summary['skipped']++;
    }

    /**
     * @param  array{created: int, approved: int, paid: int, fees_posted: int, skipped: int, errors: list<string>}  $summary
     */
    private function postFeesOnly(
        int $propertyId,
        int $landlordId,
        string $month,
        Carbon $periodStart,
        Carbon $periodEnd,
        User $actor,
        array &$summary,
    ): void {
        if ($this->trustAccounting->periodManagementFeePosted($propertyId, $landlordId, $month)) {
            $summary['skipped']++;

            return;
        }

        $settlement = $this->settlements->buildSettlement($propertyId, $landlordId, $periodStart, $periodEnd);
        $feeAmount = (float) ($settlement['management_fee'] ?? 0);
        if ($feeAmount <= 0) {
            $summary['skipped']++;

            return;
        }

        $property = Property::query()->find($propertyId);
        $this->trustAccounting->postPeriodManagementFee(
            $propertyId,
            $landlordId,
            $month,
            $feeAmount,
            (int) ($property?->agent_user_id ?? $actor->id),
            (int) $actor->id,
            (string) ($settlement['property_name'] ?? $property?->name ?? ''),
        );

        $summary['fees_posted']++;
    }

    private function findPayoutItem(int $propertyId, int $landlordId, string $periodMonth): ?PmLandlordPayoutItem
    {
        return PmLandlordPayoutItem::query()
            ->with('payout')
            ->where('property_id', $propertyId)
            ->where('landlord_id', $landlordId)
            ->where('period_month', $periodMonth)
            ->orderByDesc('id')
            ->first();
    }
}
